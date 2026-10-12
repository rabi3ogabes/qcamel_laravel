<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Actions\ConfirmPaymentAction;
use App\Domain\Payments\Actions\FailPaymentAction;
use App\Domain\Payments\Sadad\ApiResult;
use App\Domain\Payments\Sadad\NormalizedPayment;
use App\Domain\Payments\Sadad\SadadApiClient;
use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F-PAY-036 processPayment (R-114, R-116, T-066): load order → (confirmed:
 * noop, no Sadad call) → merchant API lookup → decision → confirm/fail →
 * exactly one payment_events row. Logging/side-effect failures never change
 * the outcome. Side effects are domain events (PaymentConfirmed / PaymentFailed)
 * dispatched after commit by the actions.
 */
class PaymentProcessor
{
    public function __construct(
        private readonly SadadApiClient $api,
        private readonly ConfirmPaymentAction $confirm,
        private readonly FailPaymentAction $fail,
    ) {}

    /**
     * @param  array<string,mixed>|null  $rawPayload
     * @return array{outcome:string, decision:?PaymentDecision, order:?Order}
     */
    public function process(string $orderRef, ?NormalizedPayment $payload, ?array $rawPayload, bool $checksumValid, string $source, ?string $actorUserId = null): array
    {
        // Reduce duplicate API calls when callback + webhook + poller arrive together.
        $lock = Cache::lock('sadad:ref:'.$orderRef, 10);
        $locked = $lock->block(5);
        try {
            return $this->run($orderRef, $payload, $rawPayload, $checksumValid, $source, $actorUserId);
        } finally {
            if ($locked) {
                $lock->release();
            }
        }
    }

    private function run(string $orderRef, ?NormalizedPayment $payload, ?array $rawPayload, bool $checksumValid, string $source, ?string $actorUserId): array
    {
        $order = Order::query()->where('booking_reference', $orderRef)->first();
        if (! $order) {
            $this->log($orderRef, null, $source, $rawPayload, $checksumValid, null, null, 'none', null, 'order_not_found');

            return ['outcome' => 'order_not_found', 'decision' => null, 'order' => null];
        }
        if ($order->payment_status->value === 'confirmed') {
            $this->log($orderRef, $order->id, $source, $rawPayload, $checksumValid, null, null, 'noop', 'already_confirmed', 'noop');

            return ['outcome' => 'noop', 'decision' => PaymentDecision::decide($orderRef, $order->total_amount, 'confirmed', null, false, null), 'order' => $order];
        }

        try {
            $api = $this->api->lookupTransaction($order->booking_reference, $payload?->transactionNumber);
        } catch (Throwable $e) {
            $api = ApiResult::unavailable('exception_'.$e->getMessage());
        }

        $decision = PaymentDecision::decide($order->booking_reference, (string) $order->total_amount, $order->publicStatus(), $payload, $checksumValid, $api);

        $outcome = $decision->action;
        if ($decision->action === 'confirm') {
            $outcome = $this->confirm->execute($order->booking_reference, (string) $decision->transactionNumber, $actorUserId);
        } elseif ($decision->action === 'fail') {
            $outcome = $this->fail->execute($order->booking_reference, $decision->reason);
        } elseif ($decision->action === 'review') {
            if ($decision->reason === 'paid_after_staff_cancel' && $decision->transactionNumber) {
                // Records payment_id/paid_at + refund task, keeps the order cancelled.
                $this->confirm->execute($order->booking_reference, $decision->transactionNumber, $actorUserId);
            }
            Order::query()->whereKey($order->id)->update(['review_required_at' => now()]);
        }

        $this->log($orderRef, $order->id, $source, $rawPayload, $checksumValid, $api->kind, $api->error, $decision->action, $decision->reason, $outcome);

        return ['outcome' => $outcome, 'decision' => $decision, 'order' => $order->fresh()];
    }

    /** R-116 / F-PAY-037: one row per processing, payload ≤ 4 000 chars. */
    private function log(string $ref, ?string $orderId, string $source, ?array $raw, bool $checksumValid, ?string $apiResult, ?string $apiError, string $decision, ?string $reason, string $outcome): void
    {
        try {
            PaymentEvent::create([
                'booking_reference' => mb_substr($ref, 0, 60),
                'order_id' => $orderId,
                'source' => $source,
                'payload' => self::boundedPayload($raw),
                'checksum_valid' => $checksumValid,
                'api_result' => $apiResult,
                'api_error' => $apiError ? mb_substr($apiError, 0, 255) : null,
                'decision' => $decision,
                'decision_reason' => $reason ? mb_substr($reason, 0, 60) : null,
                'outcome' => $outcome,
            ]);
        } catch (Throwable $e) {
            Log::error('payment audit log failed', ['error' => $e->getMessage()]);
        }
    }

    public static function boundedPayload(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        $text = json_encode($raw, JSON_UNESCAPED_UNICODE);
        if ($text === false) {
            return ['unserialisable' => true];
        }
        $limit = (int) config('qcamelmc.payments.payload_cap', 4000);
        if (mb_strlen($text) <= $limit) {
            return is_array($raw) ? $raw : ['value' => $raw];
        }

        return ['truncated' => true, 'preview' => mb_substr($text, 0, $limit)];
    }
}
