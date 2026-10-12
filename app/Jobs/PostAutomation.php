<?php

namespace App\Jobs;

use App\Domain\Messaging\AutomationRelay;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * System automation posts (R-160): order_created (web cash) and
 * payment_confirmed (once). Never changes a payment outcome.
 */
class PostAutomation implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public string $action, public string $orderId, public bool $markSending = false)
    {
        $this->onQueue('automation');
    }

    public function uniqueId(): string
    {
        return $this->action.':'.$this->orderId;
    }

    public function handle(AutomationRelay $relay): void
    {
        $order = Order::query()->find($this->orderId);
        if ($order) {
            $relay->send($this->action, $order, markSending: $this->markSending);
        }
    }
}
