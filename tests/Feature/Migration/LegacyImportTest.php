<?php

use App\Domain\Booking\Services\StockLedger;
use App\Domain\Migration\LegacyImporter;
use App\Models\Order;
use App\Models\TicketHolder;
use App\Models\User;
use App\Settings\AutomationSettings;
use App\Settings\BrandingSettings;
use App\Settings\SadadSettings;
use Carbon\CarbonInterval;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
| Part 4.9 data migration — T-001…T-005 and legacy fixes F-01…F-14,
| on a legacy-shaped fixture (tests/db/02-legacy-seed.sql of the original).
*/

const LEGACY_EVENT = 'f0000000-0000-4000-8000-000000000001';

function legacyDb(): Connection
{
    config(['database.connections.legacy_fixture' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
    DB::purge('legacy_fixture');
    $s = Schema::connection('legacy_fixture');
    $text = function (string $table, array $columns) use ($s) {
        $s->create($table, function (Blueprint $t) use ($columns) {
            foreach ($columns as $c) {
                $t->text($c)->nullable();
            }
        });
    };
    $text('auth_users', ['id', 'email', 'encrypted_password', 'email_confirmed_at', 'raw_user_meta_data', 'created_at']);
    $text('admin_users', ['id', 'email', 'created_at']);
    $text('user_roles', ['id', 'user_id', 'role', 'created_at']);
    $text('settings', ['id', 'logo_url', 'webhook_url', 'email_webhook_url', 'admin_phone', 'admin_email', 'sadad_merchant_id', 'sadad_api_key', 'sadad_secret', 'sadad_website_domain', 'sadad_environment', 'site_url', 'webhook_enabled', 'email_webhook_enabled', 'payment_failed_email_enabled', 'current_event_id', 'hero_text']);
    $text('events', ['id', 'title', 'event_date', 'end_date', 'location', 'is_active', 'is_archived', 'display_order', 'created_at']);
    $text('tickets', ['id', 'event_id', 'type', 'price', 'available_quantity', 'sold_quantity', 'description', 'created_at']);
    $text('pos_users', ['id', 'name', 'icon', 'is_active', 'created_at']);
    $text('customers', ['id', 'name', 'email', 'phone', 'country_code', 'nationality', 'id_number', 'created_at']);
    $text('orders', ['id', 'customer_id', 'event_id', 'ticket_type', 'quantity', 'total_amount', 'payment_method', 'payment_status', 'payment_note', 'payment_id', 'booking_reference', 'pos_user_id', 'qr_code', 'confirmed_at', 'paid_at', 'payment_expires_at', 'created_at']);
    $text('ticket_holders', ['id', 'order_id', 'name', 'phone', 'country_code', 'nationality', 'ticket_type', 'qr_code', 'qr_image_url', 'id_number', 'is_present', 'confirmed_at', 'confirmed_by', 'confirmed_by_name', 'created_at']);
    foreach (['deleted_tickets', 'expired_qr_codes', 'payment_events', 'payment_errors', 'email_delivery_events', 'staff_attendance', 'pos_receipts', 'popup_banners', 'page_views', 'activity_logs', 'login_attempts'] as $t) {
        $text($t, ['id', 'order_id', 'created_at']);
    }

    return DB::connection('legacy_fixture');
}

function seedLegacy(Connection $db): void
{
    $ago = fn (string $interval) => now()->sub(CarbonInterval::fromString($interval))->toIso8601String();
    $db->table('settings')->insert(['id' => '1', 'logo_url' => 'https://example.qa/logo.png', 'webhook_url' => 'https://n8n.example/webhook/live', 'admin_phone' => '+97455500000',
        'sadad_merchant_id' => '1664851', 'sadad_api_key' => 'pin-legacy', 'sadad_secret' => 'secret-legacy', 'sadad_website_domain' => null, 'sadad_environment' => 'auto', 'site_url' => 'https://old.example', 'webhook_enabled' => '1', 'current_event_id' => LEGACY_EVENT]);
    $db->table('events')->insert(['id' => LEGACY_EVENT, 'title' => 'Legacy Event', 'event_date' => '2026-12-25 18:00:00+03', 'location' => 'Doha', 'is_active' => null, 'is_archived' => '0']);
    $db->table('tickets')->insert([
        ['id' => 'f1000000-0000-4000-8000-000000000001', 'event_id' => LEGACY_EVENT, 'type' => 'vip', 'price' => '200', 'available_quantity' => '10', 'sold_quantity' => '0'],
        ['id' => 'f2000000-0000-4000-8000-000000000001', 'event_id' => LEGACY_EVENT, 'type' => 'normal', 'price' => '100', 'available_quantity' => '10', 'sold_quantity' => '7'],   // drifted
        ['id' => 'f3000000-0000-4000-8000-000000000001', 'event_id' => LEGACY_EVENT, 'type' => 'parking', 'price' => '10', 'available_quantity' => '10', 'sold_quantity' => '5'],   // drifted
    ]);
    $db->table('customers')->insert(['id' => 'f9000000-0000-4000-8000-000000000001', 'name' => 'Legacy Customer', 'email' => 'legacy@example.com', 'phone' => '55500000', 'country_code' => null]);
    $o = fn ($n, $type, $qty, $total, $method, $status, $ref, $created, $extra = []) => array_merge(['id' => "fa000000-0000-4000-8000-00000000000{$n}", 'customer_id' => 'f9000000-0000-4000-8000-000000000001', 'event_id' => LEGACY_EVENT,
        'ticket_type' => $type, 'quantity' => (string) $qty, 'total_amount' => (string) $total, 'payment_method' => $method, 'payment_status' => $status, 'booking_reference' => $ref, 'created_at' => $created], $extra);
    $insertEach = fn (string $table, array $rows) => array_map(fn ($r) => $db->table($table)->insert($r), $rows);
    $insertEach('orders', [
        $o(1, 'normal', 2, 200, 'sadad', 'confirmed', 'QTR-25-12-2026-L1AAAAAA', $ago('2 days'), ['payment_id' => 'SD-DUP', 'confirmed_at' => $ago('2 days')]),
        $o(2, 'vip', 1, 200, 'sadad', 'pending', 'QTR-25-12-2026-L2BBBBBB', $ago('3 days')),
        $o(3, 'normal', 1, 100, 'cash_pos', 'cancelled', 'QTR-25-12-2026-L3CCCCCC', $ago('1 day')),
        $o(4, 'normal', 1, 100, 'cash_pos', 'confirmed', 'POS-25-12-2026-L4DDDDDD', $ago('1 day')),
        $o(5, 'vip', 1, 200, 'sadad', 'pending', 'QTR-25-12-2026-L5EEEEEE', $ago('5 minutes')),
        $o(6, 'normal', 1, 100, 'sadad', 'cancelled', 'QTR-25-12-2026-L6FFFFFF', $ago('4 days'), ['payment_id' => 'SD-DUP']),          // F-04 duplicate
        $o(7, 'parking', 2, 20, 'cash_pos', null, 'QTR-25-12-2026-L7GGGGGG', $ago('6 hours')),                                       // F-08, holder-less
    ]);
    $h = fn ($order, $name, $phone, $type, $code, $id, $extra = []) => array_merge(['id' => (string) Str::uuid(), 'order_id' => "fa000000-0000-4000-8000-00000000000{$order}", 'name' => $name, 'phone' => $phone,
        'nationality' => 'x', 'ticket_type' => $type, 'qr_code' => $code, 'id_number' => $id, 'created_at' => $ago('1 day')], $extra);
    $insertEach('ticket_holders', [
        $h(1, 'A', '55500001', 'normal', 'https://abc.supabase.co/storage/v1/object/public/qr-codes/QTR-25-12-2026-L1AAAAAA-TKT01.png', '290000000001'),
        $h(1, 'B', '+971 55510002', 'normal', 'https://abc.supabase.co/storage/v1/object/public/qr-codes/QTR-25-12-2026-L1AAAAAA-TKT02.png?v=3', '290000000002'),
        $h(2, 'C', '55500003', 'vip', 'QTR-25-12-2026-L2BBBBBB-TKT01', '290000000003'),
        $h(3, 'D', '55500004', 'normal', 'QTR-25-12-2026-L3CCCCCC-TKT01', '290000000004'),
        $h(4, 'E', '55500005', 'normal', 'POS-25-12-2026-L4DDDDDD-TKT01', '290000000005', ['is_present' => '1']),       // F-06
        $h(5, 'F', '55500006', 'vip', null, '290000000006'),                                                            // F-05
        $h(6, 'G', '55500007', 'normal', 'QTR-25-12-2026-L6FFFFFF-TKT01', '290000000007'),
    ]);
    $db->table('auth_users')->insert([
        ['id' => '0a000000-0000-4000-8000-000000000001', 'email' => 'Boss@Example.qa', 'encrypted_password' => '$2a$10$abcdefghijklmnopqrstuuJ0Jx0RrW6JxJ7v4dYw1cY0xYyZ5Y7aW', 'created_at' => $ago('90 days')],
        ['id' => '0a000000-0000-4000-8000-000000000002', 'email' => 'mod@example.qa', 'encrypted_password' => '$2a$10$abcdefghijklmnopqrstuuJ0Jx0RrW6JxJ7v4dYw1cY0xYyZ5Y7aW', 'created_at' => $ago('60 days')],
        ['id' => '0a000000-0000-4000-8000-000000000003', 'email' => 'nobody@example.qa', 'encrypted_password' => '$2a$10$abcdefghijklmnopqrstuuJ0Jx0RrW6JxJ7v4dYw1cY0xYyZ5Y7aW', 'created_at' => $ago('30 days')],
    ]);
    $db->table('admin_users')->insert(['id' => 'x', 'email' => 'boss@example.qa']);
    $db->table('user_roles')->insert(['id' => 'y', 'user_id' => '0a000000-0000-4000-8000-000000000002', 'role' => 'moderator']);
}

function runImport(array $options = [], bool $dry = false): LegacyImporter
{
    $db = legacyDb();
    seedLegacy($db);

    return (new LegacyImporter($db, ['auth_users' => 'auth_users'] + $options))->run($dry);
}

it('T-001 moves settings to their new homes without copying secrets by default', function () {
    runImport();

    expect(app(BrandingSettings::class)->logo_url)->toBe('https://example.qa/logo.png')
        ->and(app(SadadSettings::class)->merchant_id)->toBe('1664851')
        ->and(app(SadadSettings::class)->environment)->toBe('auto')
        ->and(app(SadadSettings::class)->site_url)->toBeNull()
        ->and(app(SadadSettings::class)->checkout_secret)->toBeNull()          // D-002: rotated value goes in .env
        ->and(app(AutomationSettings::class)->webhook_url)->toBeNull();
});

it('T-001 keeps the webhook URL and Sadad secret unchanged when asked to copy secrets', function () {
    runImport(['include_secrets' => true]);

    expect(app(SadadSettings::class)->checkout_secret)->toBe('secret-legacy')
        ->and(app(AutomationSettings::class)->webhook_url)->toBe('https://n8n.example/webhook/live');
});

it('T-002 splits QR image URLs into code + picture and leaves plain codes alone', function () {
    runImport();
    $a = TicketHolder::query()->where('qr_code', 'QTR-25-12-2026-L1AAAAAA-TKT01')->first();
    $b = TicketHolder::query()->where('qr_code', 'QTR-25-12-2026-L1AAAAAA-TKT02')->first();
    $c = TicketHolder::query()->where('qr_code', 'QTR-25-12-2026-L2BBBBBB-TKT01')->first();

    expect($a->qr_image_url)->toEndWith('QTR-25-12-2026-L1AAAAAA-TKT01.png')
        ->and($b)->not->toBeNull()
        ->and($c->qr_image_url)->toBeNull()
        ->and(TicketHolder::query()->where('qr_code', 'like', 'http%')->count())->toBe(0);
});

it('T-003 closes stale online holds and gives fresh ones a 30-minute expiry', function () {
    runImport();
    $stale = Order::query()->where('booking_reference', 'QTR-25-12-2026-L2BBBBBB')->first();
    $fresh = Order::query()->where('booking_reference', 'QTR-25-12-2026-L5EEEEEE')->first();

    expect($stale->payment_status->value)->toBe('cancelled')->and($stale->payment_note)->toBe('expired')
        ->and($fresh->payment_status->value)->toBe('pending')
        ->and($fresh->payment_expires_at->between(now()->addMinutes(24), now()->addMinutes(26)))->toBeTrue()
        ->and(Order::query()->where('booking_reference', 'QTR-25-12-2026-L1AAAAAA')->first()->payment_status->value)->toBe('confirmed')
        ->and(Order::query()->where('booking_reference', 'QTR-25-12-2026-L3CCCCCC')->first()->payment_status->value)->toBe('cancelled');
});

it('T-004 recounts stock from live holders after import', function () {
    runImport();
    $sold = DB::table('ticket_types')->pluck('sold_quantity', 'type');

    // normal = 2 confirmed online + 1 confirmed POS (cancelled excluded); vip = 1 fresh hold; parking = 2 (holder-less pending cash order)
    expect((int) $sold['normal'])->toBe(3)->and((int) $sold['vip'])->toBe(1)->and((int) $sold['parking'])->toBe(2);
});

it('T-005 leaves stock to the ledger only and the per-person cap active after import', function () {
    runImport();

    expect(app(StockLedger::class)->recount(false))->toBe([])
        ->and((int) config('qcamelmc.orders.per_person_cap'))->toBeGreaterThan(0);
});

it('F-04 keeps a duplicated payment id on the confirmed order and opens a refund for the other', function () {
    runImport();
    $kept = Order::query()->where('booking_reference', 'QTR-25-12-2026-L1AAAAAA')->first();
    $moved = Order::query()->where('booking_reference', 'QTR-25-12-2026-L6FFFFFF')->first();

    expect($kept->payment_id)->toBe('SD-DUP')->and($moved->payment_id)->toBeNull()
        ->and($moved->payment_note)->toBe('duplicate_payment_id:SD-DUP')
        ->and(DB::table('refunds')->where('order_id', $moved->id)->where('reason', 'duplicate_payment')->exists())->toBeTrue();
});

it('F-05 F-06 F-07 F-08 F-14 repair holders, phones and statuses', function () {
    runImport();

    expect(TicketHolder::query()->where('qr_code', 'QTR-25-12-2026-L5EEEEEE-TKT01')->exists())->toBeTrue()            // F-05
        ->and(TicketHolder::query()->where('qr_code', 'POS-25-12-2026-L4DDDDDD-TKT01')->first()->is_present)->toBeFalse() // F-06
        ->and(DB::table('customers')->value('country_code'))->toBe('+974')                                              // F-07
        ->and(Order::query()->where('booking_reference', 'QTR-25-12-2026-L7GGGGGG')->first()->payment_status->value)->toBe('pending'); // F-08
    $b = TicketHolder::query()->where('qr_code', 'QTR-25-12-2026-L1AAAAAA-TKT02')->first();
    expect($b->country_code)->toBe('+971')->and($b->phone)->toBe('55510002');                                         // F-14
});

it('derives order lines from holders and creates holder rows for holder-less orders', function () {
    runImport();
    $order = Order::query()->where('booking_reference', 'QTR-25-12-2026-L1AAAAAA')->first();
    $line = DB::table('order_items')->where('order_id', $order->id)->first();
    $holderless = Order::query()->where('booking_reference', 'QTR-25-12-2026-L7GGGGGG')->first();

    expect($line->type)->toBe('normal')->and((int) $line->quantity)->toBe(2)->and((float) $line->line_total)->toBe(200.0)->and($line->price_source)->toBe('derived')
        ->and($holderless->holders()->count())->toBe(2)
        ->and((float) DB::table('order_items')->where('order_id', $holderless->id)->sum('line_total'))->toBe(20.0);
});

it('T-110 maps legacy admins and moderators, imports role-less users inactive and forces admin password reset', function () {
    runImport();
    $boss = User::query()->where('email', 'boss@example.qa')->first();
    $mod = User::query()->where('email', 'mod@example.qa')->first();
    $nobody = User::query()->where('email', 'nobody@example.qa')->first();

    expect($boss->hasRole('admin'))->toBeTrue()->and($boss->must_reset_password)->toBeTrue()
        ->and(str_starts_with($boss->getRawOriginal('password'), '$2y$'))->toBeTrue()
        ->and($mod->hasRole('supervisor'))->toBeTrue()
        ->and($nobody->is_active)->toBeFalse();
});

it('is idempotent and a dry run changes nothing', function () {
    $db = legacyDb();
    seedLegacy($db);
    (new LegacyImporter($db, ['auth_users' => 'auth_users']))->run(dryRun: true);
    expect(Order::count())->toBe(0)->and(DB::table('ticket_holders')->count())->toBe(0);

    (new LegacyImporter($db, ['auth_users' => 'auth_users']))->run();
    $counts = [Order::count(), DB::table('ticket_holders')->count(), DB::table('order_items')->count(), DB::table('users')->count()];
    (new LegacyImporter($db, ['auth_users' => 'auth_users']))->run();

    expect([Order::count(), DB::table('ticket_holders')->count(), DB::table('order_items')->count(), DB::table('users')->count()])->toBe($counts);
});

it('writes a reconciliation report with counts, fixes and revenue', function () {
    $report = runImport()->report(false);

    expect($report)->toContain('| orders | 7 | 7 | 0 |')->toContain('Confirmed revenue: legacy')->toContain('F-01');
});

it('runs as an artisan command with a dry run and writes the report', function () {
    seedLegacy(legacyDb());
    $report = 'storage/framework/testing/migration-report.md';

    $this->artisan('migrate:from-supabase', ['--dry-run' => true, '--connection' => 'legacy_fixture', '--auth-table' => 'auth_users', '--report' => $report])
        ->expectsOutputToContain('Dry run finished')
        ->assertSuccessful();

    expect(file_get_contents(base_path($report)))->toContain('dry run, rolled back')
        ->and(Order::count())->toBe(0);
    @unlink(base_path($report));
});
