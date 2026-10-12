<?php

namespace App\Http\Controllers\Payments;

use App\Domain\Payments\Sadad\NormalizedPayment;
use App\Domain\Payments\Sadad\PayloadReader;
use App\Domain\Payments\Sadad\SadadConfig;
use App\Domain\Payments\Sadad\SadadSigner;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessSadadNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F-PAY-025 server-to-server webhook (R-123, T-070): ALWAYS 200
 * {"status":"success"}; processing is queued on the high-priority
 * `payments` queue and idempotent (replays never double-notify).
 */
class SadadWebhookController extends Controller
{
    public function __invoke(Request $request, SadadConfig $config): JsonResponse
    {
        try {
            $payload = PayloadReader::read($request);
            $normalized = NormalizedPayment::from($payload);
            if ($normalized) {
                ProcessSadadNotification::dispatch($normalized->orderRef, $payload, SadadSigner::verifyChecksum($payload, $config->checkoutSecret()));
            }
        } catch (Throwable $e) {
            Log::debug('sadad webhook ignored', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'success'], 200);
    }
}
