<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentErrorCode;
use App\Enums\PaymentErrorSource;
use App\Events\PaymentErrorLogged;
use App\Http\Controllers\Controller;
use App\Models\PaymentError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** F-PAY-054 rebuild: validated, rate-limited client-side failure beacon (D-024, D-032). */
class PublicMiscController extends Controller
{
    public function paymentError(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => 'nullable|uuid|exists:events,id',
            'customer_name' => 'nullable|string|max:100',
            'customer_phone' => 'nullable|string|max:32',
            'quantity' => 'nullable|integer|min:0|max:30',
            'amount' => 'nullable|numeric|min:0|max:100000',
            'error_code' => 'required|in:CHECKOUT_FAILED,TICKETS_UNAVAILABLE,TICKET_LIMIT',
            'error_message' => 'nullable|string|max:1000',
        ]);
        $raw = ['url' => mb_substr((string) $request->headers->get('referer'), 0, 300), 'userAgent' => mb_substr((string) $request->userAgent(), 0, 300)];
        $source = $data['error_code'] === PaymentErrorCode::CheckoutFailed->value
            ? PaymentErrorSource::classify($data['error_message'] ?? '', $data['error_code'])
            : PaymentErrorSource::Site;
        $err = PaymentError::create($data + ['error_source' => $source, 'raw' => $raw]);
        event(new PaymentErrorLogged($err->id));

        return response()->json(['success' => true]);
    }
}
