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
class TicketHolderChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** HolderCreated / HolderDeleted / edits (Part 4.5). */
    public function __construct(public string $holderId, public string $orderId, public string $eventId, public string $change) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('event.'.$this->eventId), new PrivateChannel('order.'.$this->orderId), new PrivateChannel('admin')];
    }

    public function broadcastWith(): array
    {
        return ['holder_id' => $this->holderId, 'order_id' => $this->orderId, 'change' => $this->change];
    }

    public function broadcastAs(): string
    {
        return 'TicketHolderChanged';
    }
}
