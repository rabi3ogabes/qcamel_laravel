<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Part 4.5 broadcast event (dispatched after commit, no national IDs). */
class PosReceiptChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $receiptId, public string $change) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin'), new PrivateChannel('staff')];
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->receiptId, 'change' => $this->change];
    }

    public function broadcastAs(): string
    {
        return 'PosReceiptChanged';
    }
}
