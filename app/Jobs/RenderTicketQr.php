<?php

namespace App\Jobs;

use App\Domain\Tickets\QrService;
use App\Models\TicketHolder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * D-036: QR pictures rendered after commit on the `qr` queue, never before
 * the order response. A failure never fails the order (T-044).
 */
class RenderTicketQr implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 30, 120];

    public function __construct(public string $holderId)
    {
        $this->onQueue('qr');
    }

    public function uniqueId(): string
    {
        return $this->holderId;
    }

    public function handle(QrService $qr): void
    {
        $holder = TicketHolder::query()->find($this->holderId);
        if ($holder) {
            $qr->renderHolder($holder);
        }
    }
}
