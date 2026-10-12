<?php

namespace App\Console\Commands;

use App\Domain\Payments\Sadad\SadadDiagnose;
use Illuminate\Console\Command;

class SadadDiagnoseCommand extends Command
{
    protected $signature = 'sadad:diagnose';

    protected $description = 'F-PAY-051 readiness diagnostics';

    public function handle(SadadDiagnose $diagnose): int
    {
        $r = $diagnose->run();
        foreach ($r['checks'] as $c) {
            $this->line(strtoupper($c['status']).'  '.$c['id'].'  '.$c['message']);
        }

        return $r['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
