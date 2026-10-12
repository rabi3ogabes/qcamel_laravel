<?php

namespace App\Console\Commands;

use App\Models\ActiveVisitor;
use Illuminate\Console\Command;

class PruneVisitorsCommand extends Command
{
    protected $signature = 'visitors:prune';

    protected $description = 'R-179: delete presence rows older than 5 minutes';

    public function handle(): int
    {
        $n = ActiveVisitor::query()->where('last_seen_at', '<', now()->subMinutes((int) config('qcamelmc.visitors.prune_minutes', 5)))->delete();
        $this->info("Pruned $n");

        return self::SUCCESS;
    }
}
