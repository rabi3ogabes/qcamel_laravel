<?php

namespace App\Jobs;

use App\Domain\Messaging\TransactionalMailer;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued transactional mail (F-PAY-038 rebuild): invoice | admin_sale |
 * payment_failed. Unique per (kind, order) so replays never double-send;
 * the mailer adds DB idempotency on top.
 */
class SendOrderMail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(public string $kind, public string $orderId, public bool $force = false, public ?string $reason = null)
    {
        $this->onQueue('notifications');
    }

    public function uniqueId(): string
    {
        return $this->kind.':'.$this->orderId.($this->force ? ':'.microtime(true) : '');
    }

    public function handle(TransactionalMailer $mailer): void
    {
        $order = Order::query()->withTrashed()->find($this->orderId);
        if (! $order) {
            return;
        }
        match ($this->kind) {
            'invoice' => $mailer->sendInvoice($order, $this->force),
            'admin_sale' => $mailer->sendAdminSale($order, $this->force),
            'payment_failed' => $mailer->sendPaymentFailed($order, $this->reason, $this->force),
            default => null,
        };
    }
}
