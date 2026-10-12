<?php

namespace App\Console\Commands;

use App\Domain\Booking\Services\StockLedger;
use App\Models\User;
use App\Notifications\StockDriftDetected;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class StockVerifyCommand extends Command
{
    protected $signature = 'stock:verify';

    protected $description = 'Nightly/hourly drift check: compares counters with live orders without writing';

    public function handle(StockLedger $stock): int
    {
        $drift = $stock->recount(false);
        if ($drift !== []) {
            $this->warn(count($drift).' ticket types drifted');
            $admins = User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin']))->where('is_active', true)->get();
            Notification::send($admins, new StockDriftDetected($drift));
        } else {
            $this->info('0 drift');
        }

        return self::SUCCESS;
    }
}
