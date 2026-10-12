<?php

namespace App\Listeners;

use App\Enums\PaymentErrorCode;
use App\Enums\PaymentErrorSource;
use App\Events\OrderConfirmed;
use App\Events\OrderCreated;
use App\Events\PaymentConfirmed;
use App\Events\PaymentErrorLogged;
use App\Events\PaymentFailed;
use App\Jobs\PostAutomation;
use App\Jobs\RenderTicketQr;
use App\Jobs\SendOrderMail;
use App\Models\Order;
use App\Models\PaymentError;
use App\Settings\AutomationSettings;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wires the exactly-once side effects (R-125, R-126, R-160, F-ORD-024/026,
 * F-PAY-038/039, F-POS-032). Every effect is queued and idempotent; a
 * failure never changes the business outcome.
 */
class DomainEventSubscriber
{
    public function onOrderCreated(OrderCreated $e): void
    {
        $order = $e->order;
        foreach ($order->holders()->pluck('id') as $holderId) {
            RenderTicketQr::dispatch($holderId);
        }
        if ($order->isPos()) {
            SendOrderMail::dispatch('admin_sale', $order->id);
            event(new OrderConfirmed($order));
        } elseif ($order->isCashAtVenue()) {
            if (app(AutomationSettings::class)->whatsappUrl()) {
                PostAutomation::dispatch('order_created', $order->id);
            }
        }
    }

    public function onPaymentConfirmed(PaymentConfirmed $e): void
    {
        $order = $e->order;
        foreach ($order->holders()->whereNull('qr_image_url')->pluck('id') as $holderId) {
            RenderTicketQr::dispatch($holderId);
        }
        SendOrderMail::dispatch('invoice', $order->id);
        SendOrderMail::dispatch('admin_sale', $order->id);
        if ($e->notifyAutomation && app(AutomationSettings::class)->whatsappUrl()) {
            PostAutomation::dispatch('payment_confirmed', $order->id, markSending: $e->manual);
        }
    }

    public function onPaymentFailed(PaymentFailed $e): void
    {
        $order = $e->order;
        try {
            $this->recordPaymentError($order, (string) ($e->reason ?? 'payment_failed'));
        } catch (Throwable $ex) {
            Log::warning('payment error record failed', ['error' => $ex->getMessage()]);
        }
        SendOrderMail::dispatch('payment_failed', $order->id, reason: $e->reason);
    }

    /** F-PAY-039 / T-068 / T-074: copy event, quantity, payment id, customer from the order. */
    public function recordPaymentError(Order $order, string $reason): PaymentError
    {
        $order->loadMissing('customer');
        $err = PaymentError::create([
            'order_id' => $order->id,
            'booking_reference' => $order->booking_reference,
            'event_id' => $order->event_id,
            'customer_name' => $order->customer?->name,
            'customer_phone' => $order->customer?->fullPhone(),
            'quantity' => $order->quantity,
            'amount' => (string) $order->total_amount,
            'payment_id' => $order->payment_id,
            'error_source' => PaymentErrorSource::forFailureReason($reason),
            'error_code' => PaymentErrorCode::PaymentFailed->value,
            'error_message' => mb_substr($reason, 0, 1000),
            'raw' => ['source' => 'payment-processor', 'reason' => $reason],
        ]);
        event(new PaymentErrorLogged($err->id));

        return $err;
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            OrderCreated::class => 'onOrderCreated',
            PaymentConfirmed::class => 'onPaymentConfirmed',
            PaymentFailed::class => 'onPaymentFailed',
        ];
    }
}
