<?php

namespace App\Console\Commands;

use App\Domain\Booking\Actions\ExpireHoldsAction;
use App\Settings\FeatureFlagSettings;
use Illuminate\Console\Command;

class ExpireCashHoldsCommand extends Command
{
    protected $signature = 'orders:expire-cash-holds';

    protected $description = 'R-042 (NEW): expire unpaid web cash bookings after the event cut-off when the policy says so';

    public function handle(ExpireHoldsAction $action): int
    {
        if (app(FeatureFlagSettings::class)->cash_hold_policy !== 'expire_at_event_cutoff') {
            return self::SUCCESS;
        }
        $this->info('Expired cash holds: '.$action->cashAfterCutoff());

        return self::SUCCESS;
    }
}
