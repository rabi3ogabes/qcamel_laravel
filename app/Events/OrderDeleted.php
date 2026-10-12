<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Part 4.5 broadcast event (dispatched after commit, no national IDs). */
class OrderDeleted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $orderId, public string $eventId, public string $reference) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin'), new PrivateChannel('event.'.$this->eventId)];
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->orderId, 'ref' => $this->reference, 'event_id' => $this->eventId];
    }

    public function broadcastAs(): string
    {
        return 'OrderDeleted';
    }
}
