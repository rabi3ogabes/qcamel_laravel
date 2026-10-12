<?php

namespace App\Http\Controllers\Payments;

use App\Domain\Payments\PaymentProcessor;
use App\Domain\Payments\Sadad\NormalizedPayment;
use App\Domain\Payments\Sadad\PayloadReader;
use App\Domain\Payments\Sadad\SadadConfig;
use App\Domain\Payments\Sadad\SadadSigner;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * F-PAY-024 browser return from Sadad (GET/POST, CSRF-exempt, T-069, R-121):
 * never confirms/fails from the callback alone — runs the processor (which
 * asks the merchant API) with a short budget, then 303 → result page.
 */
class SadadCallbackController extends Controller
{
    public function __invoke(Request $request, PaymentProcessor $processor, SadadConfig $config): Response
    {
        $payload = PayloadReader::read($request);
        $normalized = null;
        $returnOrigin = null;
        try {
            $normalized = NormalizedPayment::from($payload);
            if ($normalized) {
                $checksumValid = SadadSigner::verifyChecksum($payload, $config->checkoutSecret());
                $processor->process($normalized->orderRef, $normalized, $payload, $checksumValid, 'callback');
                $returnOrigin = Order::query()->where('booking_reference', $normalized->orderRef)->value('return_origin');
            }
        } catch (Throwable $e) {
            Log::warning('sadad callback processing failed', ['error' => $e->getMessage()]);
        }

        $ref = $normalized?->orderRef;
        $base = $config->returnBase($returnOrigin);
        if ($base) {
            $target = $ref ? $base.'/payment/result?ref='.rawurlencode($ref) : $base.'/';

            return redirect()->away($target, 303)->header('Cache-Control', 'no-store');
        }

        return response()->view('payments.callback-fallback', ['reference' => $ref], 200)->header('Cache-Control', 'no-store');
    }
}
