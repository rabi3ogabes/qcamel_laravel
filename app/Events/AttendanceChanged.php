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
class AttendanceChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $date) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('staff')];
    }

    public function broadcastWith(): array
    {
        return ['date' => $this->date];
    }

    public function broadcastAs(): string
    {
        return 'AttendanceChanged';
    }
}
