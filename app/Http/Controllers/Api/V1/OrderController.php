<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Actions\CreateOrderAction;
use App\Domain\Booking\Data\OrderRequestValidator;
use App\Domain\Booking\Queries\PublicOrderQueries;
use App\Domain\Payments\Actions\FailPaymentAction;
use App\Domain\Payments\Sadad\SadadCheckoutBuilder;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Http\Controllers\Controller;
use App\Settings\BusinessSettings;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F-ORD-001…027 / F-PAY-018 / T-021…T-045: POST /api/v1/orders. Prices,
 * totals and statuses come only from the server (T-036).
 */
class OrderController extends Controller
{
    public function store(Request $request, OrderRequestValidator $validator, CreateOrderAction $create, SadadCheckoutBuilder $builder, FailPaymentAction $fail): JsonResponse
    {
        $body = json_decode((string) $request->getContent(), true);
        if (! is_array($body) || (array_is_list($body) && $body !== [])) {
            // T-034: null, 42, "x", [] are not objects.
            throw DomainError::validation([__('orders.v.body_object')]);
        }
        $data = $validator->validate($body);
        $actor = Actor::current();

        // T-045: a public request that claims source=pos needs a staff identity.
        if ($data->source->value === 'pos' && ! $actor->isStaff()) {
            throw DomainError::unauthorized();
        }

        $order = $create->execute($data, $actor, [
            'return_origin' => $request->headers->get('Origin'),
            'idempotency_key' => $request->header('Idempotency-Key') ? mb_substr((string) $request->header('Idempotency-Key'), 0, 100) : null,
            'locale' => app()->getLocale(),
            'terms_version' => rescue(fn () => app(BusinessSettings::class)->terms_version, null, false),
        ]);

        $response = ['success' => true, 'order' => [
            'id' => $order->id,
            'booking_reference' => $order->booking_reference,
            'total_amount' => (string) $order->total_amount,
            'payment_method' => $order->payment_method->value,
            'payment_status' => $order->payment_status->value,
            'payment_expires_at' => $order->payment_expires_at?->toIso8601String(),
        ]];

        if ($order->isSadad()) {
            try {
                $response['payment'] = $builder->forOrder($order, 'create', $request->ip());
            } catch (Throwable $e) {
                // F-PAY-019 / R-120: release the seats and answer 502.
                Log::error('sadad checkout build failed', ['ref' => $order->booking_reference, 'error' => $e->getMessage()]);
                $fail->execute($order->booking_reference, 'checkout_request_failed');
                throw new DomainError('payment_unavailable', 502, 'Online payment is not available right now');
            }
        }

        return response()->json($response, 200, ['Cache-Control' => 'no-store']);
    }

    /** F-ORD-037 / F-PAY-049 / T-060 (≤ 10 refs, invalid silently ignored). */
    public function status(Request $request, PublicOrderQueries $q): JsonResponse
    {
        $refs = $request->input('refs', $request->input('refs[]', []));
        if (is_string($refs)) {
            $refs = explode(',', $refs);
        }

        return response()->json(['success' => true, 'orders' => $q->status(is_array($refs) ? $refs : [])], 200, ['Cache-Control' => 'no-store']);
    }

    /** F-ORD-038 / T-015 / T-018: id_number only for admins. */
    public function invoice(string $reference, PublicOrderQueries $q): JsonResponse
    {
        if (Text::reference($reference) === null) {
            throw new DomainError('invalid_reference', 400, 'Invalid booking reference');
        }
        $actor = Actor::current();
        $order = $q->invoice($reference, $actor);
        if (! $order) {
            throw DomainError::notFound();
        }

        return response()->json(['success' => true, 'order' => $q->invoicePayload($order, $actor->canSeePii())], 200, ['Cache-Control' => 'no-store']);
    }
}
