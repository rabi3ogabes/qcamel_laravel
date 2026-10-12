<?php

namespace App\Jobs;

use App\Console\Commands\SyncSadadTransactionsCommand;
use App\Domain\Payments\Sadad\SadadApiClient;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

/** F-ADM-TRX-012/013 rebuild: stored Sadad fee/net/status, throttled, token cached by the client. */
class SyncSadadTransaction implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 20;

    public function __construct(public string $orderId) {}

    public function uniqueId(): string
    {
        return $this->orderId;
    }

    public function middleware(): array
    {
        return [new RateLimited('sadad-sync')];
    }

    public function handle(SadadApiClient $api): void
    {
        $order = Order::query()->find($this->orderId);
        if ($order) {
            SyncSadadTransactionsCommand::syncOne($api, $order);
        }
    }
}
