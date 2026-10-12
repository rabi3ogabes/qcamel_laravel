<?php

namespace App\Console\Commands;

use App\Domain\Tickets\QrService;
use Illuminate\Console\Command;

class BackfillQrCommand extends Command
{
    protected $signature = 'tickets:backfill-qr {--batch=100}';

    protected $description = 'R-191 render missing ticket QR pictures';

    public function handle(QrService $qr): int
    {
        $r = $qr->backfill((int) $this->option('batch'));
        $this->info("Created {$r['updated']} QR images".($r['remaining'] ? ' — run it again to continue' : ''));

        return self::SUCCESS;
    }
}
