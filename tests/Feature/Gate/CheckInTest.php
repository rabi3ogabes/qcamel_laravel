<?php

use App\Domain\Booking\Actions\ChangeOrderEventAction;
use App\Domain\Booking\Actions\OrderStatusActions;
use App\Domain\Shared\Actor;
use App\Domain\Tickets\CheckInService;
use App\Enums\Role;
use App\Models\Event;
use App\Models\ScanQueueItem;
use App\Models\TicketCheckin;
use App\Models\TicketType;
use App\Support\QatarClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/*
| Gate check-in rules — T-119…T-121 (R-004, R-005, D-005, D-030).
| The clock is frozen at 10:00 Qatar time on 2026-11-10.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 07:00:00', 'UTC'));
    $this->gate = staffUser(Role::Gatekeeper);
    $this->actingAs($this->gate);
});

function gateActor(): Actor
{
    return Actor::user(test()->gate);
}

it('T-120 admits a ticket once; the second scan is refused as already used', function () {
    $t = openEvent(date: '2026-11-10');
    $order = posOrder([[$t['normal'], 1]]);
    $code = $order->holders()->first()->qr_code;
    test()->actingAs($this->gate);

    expect(app(CheckInService::class)->admitByCode($code, gateActor())['ok'] ?? false)->toBeTrue();
    $second = app(CheckInService::class)->admitByCode($code, gateActor());
    expect($second['ok'] ?? true)->toBeFalse()->and($second['message'] ?? '')->toContain('تم استخدام التذكرة مسبقاً');
});

it('T-119 refuses a cancelled order and a code that was moved to another event', function () {
    $t = openEvent(date: '2026-11-10');
    $other = openEvent(date: '2026-11-10');
    $cancelled = posOrder([[$t['normal'], 1]]);
    app(OrderStatusActions::class)->cancel($cancelled, 'x', Actor::user(staffUser(Role::SuperAdmin)));
    $moved = posOrder([[$t['normal'], 1]]);
    $oldCode = $moved->holders()->first()->qr_code;
    app(ChangeOrderEventAction::class)->execute($moved, $other['event']->id, Actor::user(staffUser(Role::SuperAdmin)));
    test()->actingAs($this->gate);

    expect(app(CheckInService::class)->admitByCode($cancelled->holders()->first()->qr_code, gateActor())['code'] ?? null)->toBe('order_cancelled')
        ->and(app(CheckInService::class)->admitByCode($oldCode, gateActor())['code'] ?? null)->toBe('code_revoked');
});

it('T-121 refuses tickets for another day and after the check-in cut-off', function () {
    $tomorrow = openEvent(date: '2026-11-11');
    $order = posOrder([[$tomorrow['normal'], 1]]);
    test()->actingAs($this->gate);
    expect(app(CheckInService::class)->admitByCode($order->holders()->first()->qr_code, gateActor())['code'] ?? null)->toBe('wrong_day');

    $today = openEvent(date: '2026-11-10');
    $late = posOrder([[$today['normal'], 1]]);
    test()->actingAs($this->gate);
    $this->travelTo(CarbonImmutable::parse('2026-11-10 15:30:00', 'UTC')); // 18:30 Qatar
    expect(app(CheckInService::class)->admitByCode($late->holders()->first()->qr_code, gateActor())['code'] ?? null)->toBe('event_expired');
});

it('T-121 admits on day 2 of a multi-day event', function () {
    $season = Event::factory()->on('2026-11-09')->withTickets()->create(['is_active' => true, 'end_date' => QatarClock::at('2026-11-12', '23:00:00')->utc()]);
    $normal = TicketType::query()->where('event_id', $season->id)->where('type', 'normal')->first();
    $order = posOrder([[$normal, 1]]);
    test()->actingAs($this->gate);

    expect(app(CheckInService::class)->admitByCode($order->holders()->first()->qr_code, gateActor())['ok'] ?? false)->toBeTrue();
});

it('T-120 replays the offline scan queue idempotently by client id', function () {
    $t = openEvent(date: '2026-11-10');
    $order = posOrder([[$t['normal'], 1]]);
    test()->actingAs($this->gate);
    $item = ['id' => (string) Str::uuid(), 'code' => $order->holders()->first()->qr_code, 'scanned_at' => now()->toIso8601String()];

    $this->postJson('/api/v1/staff/scanner/sync', ['scans' => [$item]])->assertOk();
    $replay = $this->postJson('/api/v1/staff/scanner/sync', ['scans' => [$item]])->assertOk();

    expect(ScanQueueItem::query()->whereKey($item['id'])->count())->toBe(1)
        ->and($replay->json('results.0.result'))->toBe('admitted')
        ->and(TicketCheckin::query()->where('action', 'admit')->count())->toBe(1);
});
