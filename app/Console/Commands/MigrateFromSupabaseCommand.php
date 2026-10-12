<?php

namespace App\Console\Commands;

use App\Domain\Migration\LegacyImporter;
use App\Domain\Migration\SupabaseRestSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Part 4.9 — `php artisan migrate:from-supabase --dry-run` against a read-only
 * copy of the production Supabase database (connection `supabase_legacy`),
 * optionally followed by the storage copy from a downloaded bucket folder.
 */
class MigrateFromSupabaseCommand extends Command
{
    protected $signature = 'migrate:from-supabase
        {--dry-run : Run every step inside a transaction and roll it back}
        {--step=* : Only these steps (see LegacyImporter::STEPS)}
        {--source=db : db (read-only Postgres connection) or rest (Supabase URL + secret key)}
        {--connection=supabase_legacy : Read-only legacy database connection (source=db)}
        {--auth-table=auth.users : Table holding the Supabase auth users}
        {--with-secrets : Also copy Sadad secret and n8n webhook URLs (rotate them afterwards)}
        {--storage= : Folder with the downloaded Supabase buckets (qr-codes/, pos-receipts/)}
        {--report=storage/app/migration-report.md : Where to write the reconciliation report}';

    protected $description = 'Import the legacy Supabase data into the new schema (idempotent, resumable)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        if (! $dry && app()->isProduction() && ! $this->confirm('Import into the PRODUCTION database now?')) {
            return self::FAILURE;
        }

        $source = $this->option('source') === 'rest'
            ? new SupabaseRestSource((string) config('qcamelmc.legacy.supabase_url'), (string) config('qcamelmc.legacy.supabase_secret_key'))
            : DB::connection((string) $this->option('connection'));
        if ($source instanceof SupabaseRestSource) {
            $this->warn('REST source: password hashes are not available — every imported account must reset its password.');
        }
        $importer = new LegacyImporter(
            $source,
            ['auth_users' => (string) $this->option('auth-table'), 'include_secrets' => (bool) $this->option('with-secrets')],
            fn (string $m) => $this->line($m),
        );
        $started = microtime(true);
        $importer->run($dry, $this->option('step') ?: null);

        if (($path = $this->option('storage')) && ! $dry) {
            $this->copyStorage((string) $path);
        }

        $report = $importer->report($dry);
        $file = base_path((string) $this->option('report'));
        File::ensureDirectoryExists(dirname($file));
        File::put($file, $report."\n_Duration: ".round(microtime(true) - $started, 1)." s_\n");

        $this->table(['Table', 'Source', 'Imported', 'Skipped'], collect($importer->counts)->map(fn ($c, $t) => [$t, $c['source'], $c['imported'], $c['skipped']])->values());
        $this->info(($dry ? 'Dry run finished (rolled back). ' : 'Import finished. ').count($importer->fixes).' fixes, '.count($importer->warnings).' warnings. Report: '.$this->option('report'));

        return self::SUCCESS;
    }

    /** Steps 17 + 19: QR pictures to the qr disk (names kept, URLs rewritten), receipts to the private disk. */
    private function copyStorage(string $root): void
    {
        $qr = Storage::disk((string) config('qcamelmc.qr.disk', 'qr'));
        $copied = 0;
        foreach (File::glob(rtrim($root, '/\\').'/qr-codes/*.png') as $png) {
            $qr->put(basename($png), File::get($png));
            $copied++;
        }
        DB::table('ticket_holders')->whereNotNull('qr_image_url')->orderBy('id')->chunkById(500, function ($rows) use ($qr) {
            foreach ($rows as $h) {
                $name = basename(parse_url((string) $h->qr_image_url, PHP_URL_PATH) ?: '');
                if ($name !== '' && $qr->exists($name)) {
                    DB::table('ticket_holders')->where('id', $h->id)->update(['qr_image_url' => $qr->url($name)]);
                }
            }
        });
        $this->info("{$copied} QR images copied; holder URLs rewritten to ".config('app.url'));

        $receipts = Storage::disk('receipts');
        $moved = 0;
        foreach (DB::table('pos_receipts')->whereNotNull('image_url')->whereNull('image_path')->get(['id', 'image_url']) as $r) {
            $name = basename(parse_url((string) $r->image_url, PHP_URL_PATH) ?: '');
            $source = rtrim($root, '/\\').'/pos-receipts/receipts/'.$name;
            if ($name !== '' && File::exists($source)) {
                $receipts->put($name, File::get($source));
                DB::table('pos_receipts')->where('id', $r->id)->update(['image_path' => $name, 'image_url' => null]);
                $moved++;
            }
        }
        $this->info("{$moved} receipt images moved to the private disk — delete the public bucket afterwards");
    }
}
