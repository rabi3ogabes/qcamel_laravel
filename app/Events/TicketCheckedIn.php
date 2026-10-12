<?php

namespace App\Events;

use App\Models\TicketHolder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Part 4.5 broadcast event (dispatched after commit, no national IDs). */
class TicketCheckedIn implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TicketHolder $holder, public string $eventId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('event.'.$this->eventId), new PrivateChannel('order.'.$this->holder->order_id), new PrivateChannel('admin')];
    }

    public function broadcastWith(): array
    {
        return ['holder_id' => $this->holder->id, 'order_id' => $this->holder->order_id, 'name' => $this->holder->name, 'type' => $this->holder->ticket_type->value, 'nationality' => $this->holder->nationality, 'label' => $this->holder->checked_in_by_name, 'at' => $this->holder->checked_in_at?->toIso8601String()];
    }

    public function broadcastAs(): string
    {
        return 'TicketCheckedIn';
    }
}
