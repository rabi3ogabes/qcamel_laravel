<?php

namespace App\Domain\Payments\Sadad;

/** F-PAY-032 merchant API verdict. */
final class ApiResult
{
    private function __construct(
        public readonly string $kind, // success | failed | in_progress | not_found | unavailable
        public readonly ?string $transactionNumber = null,
        public readonly ?float $amount = null,
        public readonly ?string $websiteRefNo = null,
        public readonly ?bool $sandbox = null,
        public readonly ?string $error = null,
    ) {}

    public static function success(string $txn, float $amount, ?string $ref, bool $sandbox): self
    {
        return new self('success', $txn, $amount, $ref, $sandbox);
    }

    public static function failed(?string $txn): self
    {
        return new self('failed', $txn);
    }

    public static function inProgress(): self
    {
        return new self('in_progress');
    }

    public static function notFound(): self
    {
        return new self('not_found');
    }

    public static function unavailable(string $error): self
    {
        return new self('unavailable', error: $error);
    }
}
