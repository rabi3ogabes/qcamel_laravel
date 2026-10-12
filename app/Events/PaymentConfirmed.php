<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised once, only by the call that flipped an order to confirmed
 * (F-PAY-038, R-125, T-067). Listeners: invoice mail, admin sale alert,
 * n8n payment_confirmed, QR rendering.
 */
class PaymentConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order, public bool $manual = false, public bool $notifyAutomation = true) {}
}
