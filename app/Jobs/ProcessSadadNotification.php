<?php

namespace App\Jobs;

use App\Domain\Payments\PaymentProcessor;
use App\Domain\Payments\Sadad\NormalizedPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** F-PAY-025 rebuild: webhook processing on the `payments` queue. */
class ProcessSadadNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    /** @param array<string,mixed> $payload */
    public function __construct(public string $reference, public array $payload, public bool $checksumValid)
    {
        $this->onQueue('payments');
    }

    public function handle(PaymentProcessor $processor): void
    {
        $processor->process($this->reference, NormalizedPayment::from($this->payload), $this->payload, $this->checksumValid, 'webhook');
    }
}
