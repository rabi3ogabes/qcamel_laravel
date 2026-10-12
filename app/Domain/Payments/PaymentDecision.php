<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Sadad\ApiResult;
use App\Domain\Payments\Sadad\NormalizedPayment;
use App\Support\Money;

/**
 * F-PAY-033 decidePayment — THE rule for marking an order paid or failed
 * (R-111…R-114, T-062…T-065). Only Sadad's own API can confirm or fail an
 * order; a callback/webhook (even with a valid checksum) is only a trigger.
 */
final class PaymentDecision
{
    private function __construct(
        public readonly string $action, // noop | confirm | fail | pending | review
        public readonly string $reason,
        public readonly ?string $transactionNumber = null,
    ) {}

    public static function decide(string $orderRef, mixed $orderTotal, string $orderStatus, ?NormalizedPayment $payload, bool $checksumValid, ?ApiResult $api): self
    {
        if ($orderStatus === 'confirmed') {
            return new self('noop', 'already_confirmed');
        }

        $claimedSuccess = $payload !== null && $payload->orderRef === $orderRef && $payload->status === 'success';

        if ($api?->kind === 'success') {
            if ($api->websiteRefNo && $api->websiteRefNo !== $orderRef) {
                return new self('pending', 'api_reference_mismatch');
            }
            if (! $api->transactionNumber) {
                return new self('review', 'missing_transaction_number');
            }
            if (! Money::amountsMatch($api->amount, $orderTotal)) {
                return new self('review', 'amount_mismatch');
            }
            // D-012 / R-058 (NEW): a final staff cancellation is never auto-confirmed.
            if ($orderStatus === 'cancelled') {
                return new self('review', 'paid_after_staff_cancel', $api->transactionNumber);
            }

            return new self('confirm', 'api_success', $api->transactionNumber);
        }

        if ($api?->kind === 'failed') {
            return new self('fail', 'sadad_reported_failure');
        }

        if ($claimedSuccess && ($api === null || $api->kind === 'unavailable')) {
            return new self('review', 'unverified_success_api_unavailable');
        }
        if ($claimedSuccess) {
            return new self('pending', 'unverified_success');
        }

        return new self('pending', $api?->kind ?? 'no_evidence');
    }
}
