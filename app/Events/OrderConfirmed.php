<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Part 4.5 broadcast event (dispatched after commit, no national IDs). */
class OrderConfirmed implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** Live-bookings feed: raised for POS sales AND online payment confirmations (D-052). */
    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin'), new PrivateChannel('event.'.$this->order->event_id)];
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->order->id, 'ref' => $this->order->booking_reference, 'event_id' => $this->order->event_id, 'source' => $this->order->source->value, 'quantity' => $this->order->quantity];
    }

    public function broadcastAs(): string
    {
        return 'OrderConfirmed';
    }
}
