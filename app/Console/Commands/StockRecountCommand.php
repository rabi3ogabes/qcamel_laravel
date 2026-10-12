<?php

namespace App\Console\Commands;

use App\Domain\Booking\Services\StockLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StockRecountCommand extends Command
{
    protected $signature = 'stock:recount';

    protected $description = 'R-050 recount every sold_quantity from live orders';

    public function handle(StockLedger $stock): int
    {
        $changed = DB::transaction(fn () => $stock->recount(true));
        $this->info(count($changed).' rows corrected');

        return self::SUCCESS;
    }
}
