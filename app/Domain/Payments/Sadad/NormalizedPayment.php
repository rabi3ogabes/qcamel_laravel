<?php

namespace App\Domain\Payments\Sadad;

/**
 * F-PAY-021/022 payload reader + normaliser (R-107, T-079).
 */
final class NormalizedPayment
{
    private const REF = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,59}$/';

    private const TXN = '/^[A-Za-z0-9_-]{1,64}$/';

    public function __construct(
        public readonly string $orderRef,
        public readonly ?string $transactionNumber,
        public readonly string $status, // success | failed | in_progress | unknown
        public readonly ?float $amount,
        public readonly ?bool $sandbox,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function from(array $payload): ?self
    {
        $pick = function (string ...$keys) use ($payload): string {
            foreach ($keys as $k) {
                $v = $payload[$k] ?? null;
                if ($v !== null && ! is_array($v) && trim(SadadSigner::phpString($v)) !== '') {
                    return trim(SadadSigner::phpString($v));
                }
            }

            return '';
        };

        $ref = $pick('ORDERID', 'ORDER_ID', 'website_ref_no', 'websiteRefNo');
        if (preg_match(self::REF, $ref) !== 1) {
            return null;
        }
        $code = $pick('transaction_status', 'transactionStatus');
        $status = $code !== '' ? match ($code) {
            '3' => 'success',
            '2', '7' => 'failed',
            '1', '5', '6' => 'in_progress',
            default => 'unknown',
        } : match ($pick('STATUS')) {
            'TXN_SUCCESS' => 'success',
            'TXN_FAILURE' => 'failed',
            default => 'unknown',
        };
        $txn = $pick('transaction_number', 'transactionNumber');
        $rawAmount = $pick('TXNAMOUNT', 'txnAmount');
        $sandboxRaw = $pick('issandboxmode', 'isTestMode');

        return new self(
            orderRef: $ref,
            transactionNumber: preg_match(self::TXN, $txn) === 1 ? $txn : null,
            status: $status,
            amount: $rawAmount !== '' && is_numeric($rawAmount) ? (float) $rawAmount : null,
            sandbox: $sandboxRaw === '' ? null : $sandboxRaw === '1',
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
