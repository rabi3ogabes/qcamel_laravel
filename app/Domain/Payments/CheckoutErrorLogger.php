<?php

namespace App\Domain\Payments;

use App\Enums\PaymentErrorSource;
use App\Events\PaymentErrorLogged;
use App\Models\PaymentError;
use App\Support\Text;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F-PUB-062 rebuild: checkout failures are logged server-side (no anonymous
 * insert endpoint). TICKETS_UNAVAILABLE / TICKET_LIMIT are site errors;
 * CHECKOUT_FAILED is classified by keyword (F-ADM-PERR). Never throws.
 */
class CheckoutErrorLogger
{
    /** @param array<string,mixed> $raw */
    public static function log(string $code, string $message, ?string $eventId, ?string $customerName, ?string $phone, int $quantity, mixed $amount, array $raw = [], ?string $classifyText = null): void
    {
        try {
            $source = in_array($code, ['TICKETS_UNAVAILABLE', 'TICKET_LIMIT'], true)
                ? PaymentErrorSource::Site
                : PaymentErrorSource::classify($classifyText ?? $message, $code);

            $row = PaymentError::query()->create([
                'event_id' => $eventId,
                'customer_name' => Text::limit($customerName, 100),
                'customer_phone' => Text::limit($phone, 32),
                'quantity' => $quantity,
                'amount' => $amount,
                'error_source' => $source,
                'error_code' => $code,
                'error_message' => Text::limit($message, 1000),
                'raw' => array_slice($raw, 0, 20) + [
                    'url' => Text::limit(request()->fullUrl(), 300),
                    'userAgent' => Text::limit((string) request()->userAgent(), 300),
                ],
            ]);
            rescue(fn () => event(new PaymentErrorLogged($row->id)), report: false);
        } catch (Throwable $e) {
            Log::warning('checkout error log failed', ['code' => $code, 'error' => $e->getMessage()]);
        }
    }
}
