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
class TicketCapacityIncreased implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $eventId, public string $type, public int $oldCapacity, public int $newCapacity) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('event.'.$this->eventId)];
    }

    public function broadcastWith(): array
    {
        return ['type' => $this->type, 'old' => $this->oldCapacity, 'new' => $this->newCapacity, 'increase' => $this->newCapacity - $this->oldCapacity];
    }

    public function broadcastAs(): string
    {
        return 'TicketCapacityIncreased';
    }
}
