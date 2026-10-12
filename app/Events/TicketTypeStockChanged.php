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
class TicketTypeStockChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** Public counts only (never holder rows, F-PUB-027). */
    public function __construct(public string $eventId, public string $type, public int $remaining, public int $sold = 0, public int $capacity = 0) {}

    public function broadcastOn(): array
    {
        return [new Channel('event.'.$this->eventId.'.availability'), new PrivateChannel('event.'.$this->eventId)];
    }

    public function broadcastWith(): array
    {
        return ['type' => $this->type, 'remaining' => $this->remaining];
    }

    public function broadcastAs(): string
    {
        return 'TicketTypeStockChanged';
    }
}
