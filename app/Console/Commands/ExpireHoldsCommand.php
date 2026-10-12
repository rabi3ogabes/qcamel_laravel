<?php

namespace App\Console\Commands;

use App\Domain\Booking\Actions\ExpireHoldsAction;
use Illuminate\Console\Command;

class ExpireHoldsCommand extends Command
{
    protected $signature = 'orders:expire-holds';

    protected $description = 'R-044: close expired Sadad holds and release their seats';

    public function handle(ExpireHoldsAction $action): int
    {
        $this->info('Expired: '.$action->all());

        return self::SUCCESS;
    }
}
