<?php

namespace App\Domain\Shared;

use RuntimeException;

/**
 * A business rule failure with a stable machine code and HTTP status
 * (F-API-007, R-082, 3.12 API error codes, T-095). Rendered as
 * {success:false, code, error, ...extra} on JSON routes and as a
 * translated toast/notification in Livewire & Filament.
 */
class DomainError extends RuntimeException
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status = 422,
        ?string $message = null,
        public readonly array $extra = [],
    ) {
        parent::__construct($message ?? $errorCode);
    }

    /** Customer-facing localized message. */
    public function userMessage(): string
    {
        // ticket_limit_exceeded carries the verbatim server Arabic text (R-022).
        if ($this->errorCode === 'ticket_limit_exceeded' && $this->getMessage() !== $this->errorCode) {
            return $this->getMessage();
        }
        $key = 'errors.'.$this->errorCode;
        $msg = __($key, $this->extra['replace'] ?? []);
        if ($msg === $key) {
            $msg = $this->getMessage() !== $this->errorCode ? $this->getMessage() : __('errors.default');
        }
        if ($this->errorCode === 'insufficient_stock' && isset($this->extra['ticket_type'])) {
            $kind = \App\Enums\TicketKind::tryFrom((string) $this->extra['ticket_type']);
            if ($kind) {
                $msg .= ' ('.$kind->apiLabel().')';
            }
        }

        return $msg;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $extra = $this->extra;
        unset($extra['replace']);

        return ['success' => false, 'code' => $this->errorCode, 'error' => $this->apiMessage()] + $extra;
    }

    public function apiMessage(): string
    {
        return $this->getMessage() !== $this->errorCode ? $this->getMessage() : $this->userMessage();
    }

    // Named constructors for the R-codes ---------------------------------

    public static function insufficientStock(?string $type = null): self
    {
        return new self('insufficient_stock', 409, $type ? "insufficient_stock:$type" : 'insufficient_stock', $type ? ['ticket_type' => $type] : []);
    }

    public static function forbidden(): self
    {
        return new self('forbidden', 403, __('errors.forbidden'));
    }

    public static function unauthorized(string $message = 'Staff only'): self
    {
        return new self('unauthorized', 401, $message);
    }

    public static function notFound(string $message = 'Booking not found'): self
    {
        return new self('order_not_found', 404, $message);
    }

    /** @param list<string> $errors */
    public static function validation(array $errors, string $message = 'The order details are invalid'): self
    {
        return new self('validation_failed', 400, $message, ['errors' => array_values(array_unique($errors))]);
    }

    /**
     * T-095 legacy error-string mapping (TICKET_LIMIT_EXCEEDED:, insufficient_stock:<type>…).
     */
    public static function fromLegacyMessage(string $message): ?self
    {
        if (str_starts_with($message, 'TICKET_LIMIT_EXCEEDED:')) {
            return new self('ticket_limit_exceeded', 422, trim(substr($message, strlen('TICKET_LIMIT_EXCEEDED:'))));
        }
        if (preg_match('/^insufficient_stock(?::(\w+))?$/', $message, $m)) {
            return self::insufficientStock($m[1] ?? null);
        }
        $map = [
            'quantity_limit_exceeded' => 422, 'below_minimum_amount' => 422, 'event_not_available' => 409,
            'invalid_tickets' => 422, 'holders_mismatch' => 422, 'invalid_request' => 400, 'forbidden' => 403,
            'invalid_source' => 400, 'too_many_pending_orders' => 429, 'payment_unavailable' => 503,
        ];
        if (isset($map[$message])) {
            return new self($message, $map[$message]);
        }

        return null;
    }
}
