<?php

namespace App\Console\Commands;

use App\Domain\Payments\Sadad\SadadApiClient;
use App\Domain\Payments\Sadad\SadadConfig;
use App\Models\Order;
use App\Models\SadadTransaction;
use Illuminate\Console\Command;

class SyncSadadTransactionsCommand extends Command
{
    protected $signature = 'sadad:sync-transactions {--order= : One order id}';

    protected $description = 'D-067: cache Sadad status/fees/net for the Transactions screen';

    public function handle(SadadApiClient $api, SadadConfig $config): int
    {
        if (! $config->isConfigured()) {
            return self::SUCCESS;
        }
        $orders = Order::query()->where('payment_method', 'sadad')->whereNotNull('payment_id')
            ->when($this->option('order'), fn ($q, $id) => $q->whereKey($id))
            ->whereDoesntHave('sadadTransaction', fn ($q) => $q->where('synced_at', '>', now()->subHours(6)))
            ->latest()->limit(100)->get();
        foreach ($orders as $order) {
            self::syncOne($api, $order);
        }

        return self::SUCCESS;
    }

    public static function syncOne(SadadApiClient $api, Order $order): ?SadadTransaction
    {
        if (! $order->payment_id) {
            return null;
        }
        $res = $api->transactionDetails($order->payment_id);
        if (! ($res['success'] ?? false)) {
            return SadadTransaction::query()->updateOrCreate(['transaction_number' => $order->payment_id], ['order_id' => $order->id, 'last_error' => mb_substr((string) ($res['error'] ?? ''), 0, 255), 'synced_at' => now()]);
        }
        $t = $res['transaction'];

        return SadadTransaction::query()->updateOrCreate(['transaction_number' => $order->payment_id], [
            'order_id' => $order->id, 'website_ref_no' => $t['websiteRefNo'], 'status' => $t['status'], 'is_refund' => $t['isRefund'],
            'amount' => $t['amount'], 'commission' => $t['commission'], 'refund_charge' => $t['refundCharge'], 'net_amount' => $t['netAmount'],
            'mode' => $t['mode'], 'entity' => $t['entity'], 'transaction_date' => $t['transactiondate'], 'environment' => $res['environment'] ?? null,
            'raw' => $t, 'last_error' => null, 'synced_at' => now(),
        ]);
    }
}
