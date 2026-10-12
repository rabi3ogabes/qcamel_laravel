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
class OrderStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order, public string $from, public string $to) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin'), new PrivateChannel('event.'.$this->order->event_id), new PrivateChannel('order.'.$this->order->id)];
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->order->id, 'ref' => $this->order->booking_reference, 'event_id' => $this->order->event_id, 'from' => $this->from, 'to' => $this->to];
    }

    public function broadcastAs(): string
    {
        return 'OrderStatusChanged';
    }
}
