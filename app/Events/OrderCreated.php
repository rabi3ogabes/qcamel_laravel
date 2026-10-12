<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Part 4.5 broadcast event (dispatched after commit, no national IDs). */
class OrderCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order, public ?string $actorUserId = null, public ?string $deviceId = null) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin'), new PrivateChannel('event.'.$this->order->event_id)];
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->order->id, 'ref' => $this->order->booking_reference, 'event_id' => $this->order->event_id, 'status' => $this->order->payment_status->value, 'method' => $this->order->payment_method->value, 'source' => $this->order->source->value, 'total' => (string) $this->order->total_amount, 'quantity' => $this->order->quantity];
    }

    public function broadcastAs(): string
    {
        return 'OrderCreated';
    }
}
