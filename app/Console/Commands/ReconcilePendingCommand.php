<?php

namespace App\Console\Commands;

use App\Domain\Booking\Actions\ExpireHoldsAction;
use App\Domain\Payments\PaymentProcessor;
use App\Domain\Payments\Sadad\SadadConfig;
use App\Enums\PaymentNote;
use App\Models\Order;
use Illuminate\Console\Command;

class ReconcilePendingCommand extends Command
{
    protected $signature = 'sadad:reconcile-pending';

    protected $description = 'F-PAY-059 rebuild: verify pending/failed Sadad orders with the merchant API (max 50 per run)';

    public function handle(PaymentProcessor $processor, SadadConfig $config): int
    {
        if (! $config->isConfigured()) {
            return self::SUCCESS;
        }
        $orders = Order::query()
            ->where('payment_method', 'sadad')
            ->where(fn ($q) => $q->where('payment_status', 'pending')
                ->orWhere(fn ($c) => $c->where('payment_status', 'cancelled')->whereIn('payment_note', PaymentNote::FAILED_SET)))
            ->where('created_at', '>', now()->subDays((int) config('qcamelmc.payments.verify_window_days', 3)))
            ->where('created_at', '<', now()->subMinutes((int) config('qcamelmc.payments.reconcile_min_age_minutes', 2)))
            ->where(fn ($q) => $q->whereNull('last_payment_check_at')->orWhere('last_payment_check_at', '<', now()->subMinutes(2)))
            ->orderBy('created_at')
            ->limit((int) config('qcamelmc.payments.reconcile_batch', 50))
            ->get();
        foreach ($orders as $order) {
            $order->forceFill(['last_payment_check_at' => now()])->saveQuietly();
            $r = $processor->process($order->booking_reference, null, null, false, 'reconcile');
            $this->line($order->booking_reference.' → '.$r['outcome']);
        }
        app(ExpireHoldsAction::class)->all();

        return self::SUCCESS;
    }
}
