<?php

namespace App\Console\Commands;

use App\Domain\Messaging\InvoiceDispatcher;
use Illuminate\Console\Command;

class DispatchInvoicesCommand extends Command
{
    protected $signature = 'invoices:dispatch {--now : Run immediately (admin "send now")}';

    protected $description = 'F-NTF-045: server-side invoice batch engine';

    public function handle(InvoiceDispatcher $dispatcher): int
    {
        $this->info(json_encode($dispatcher->run((bool) $this->option('now')), JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
