<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Raised once when an order is closed as unpaid (F-PAY-039, R-126, T-068). */
class PaymentFailed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order, public ?string $reason = null) {}
}
