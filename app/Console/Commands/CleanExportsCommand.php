<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanExportsCommand extends Command
{
    protected $signature = 'exports:clean';

    protected $description = 'Delete generated exports older than 24 hours (Part 4.6)';

    public function handle(): int
    {
        $disk = Storage::disk('exports');
        foreach ($disk->allFiles() as $f) {
            if ($disk->lastModified($f) < now()->subDay()->getTimestamp()) {
                $disk->delete($f);
            }
        }

        return self::SUCCESS;
    }
}
