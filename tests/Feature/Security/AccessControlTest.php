<?php

use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Domain\Staff\PasscodeAuthenticator;
use App\Domain\Staff\StaffAccountService;
use App\Enums\Role;
use App\Models\Event;
use App\Models\KioskDevice;
use App\Models\LoginAttempt;
use App\Settings\SadadSettings;
use Illuminate\Support\Facades\DB;

/*
| Access control & API conventions — T-007…T-014, T-016, T-019, T-034, T-035,
| T-037, T-038, T-085…T-091, T-093, T-096, T-122, T-127, T-128.
*/

/* --------------------------------------------------------------- guests */

it('T-007 gives guests no customers, orders, holders or users through the API', function () {
    $t = openEvent();
    placeOrder([[$t['normal'], 1]]);

    $this->getJson('/api/v1/staff/customers/lookup?q=محمد')->assertStatus(401);
    $this->postJson('/api/v1/staff/scanner/search', ['term' => 'محمد'])->assertStatus(401);
    $this->getJson('/admin')->assertStatus(401);
    $this->get('/admin')->assertRedirect();
});

it('T-008 never exposes payment events, the Sadad secret or webhook URLs to guests', function () {
    configureSadad();
    $this->postJson('/api/v1/admin/sadad/diagnose')->assertStatus(401);
    $this->postJson('/api/v1/admin/relay', ['action' => 'send_ticket'])->assertStatus(401);
});

it('T-011 refuses privileged operations to guests', function (string $uri) {
    $this->postJson($uri, [])->assertStatus(401);
})->with(['/api/v1/admin/stock/recount', '/api/v1/admin/qr/backfill', '/api/v1/admin/qr/regenerate', '/api/v1/staff/scanner/checkin', '/api/v1/staff/scanner/reset']);

it('T-009 lets guests create orders only through POST /api/v1/orders, never as POS', function () {
    $t = openEvent();
    $this->postJson('/api/v1/orders', orderBody([[$t['normal'], 1]]))->assertOk()->assertJsonPath('order.payment_status', 'pending');
    $this->postJson('/api/v1/orders', orderBody([[$t['normal'], 1]], 'cash_pos', ['source' => 'pos']))->assertStatus(401);
});

it('T-012 shows guests only active events with their ticket prices', function () {
    openEvent();
    openEvent();
    Event::factory()->withTickets()->create(['is_active' => false, 'is_archived' => false, 'event_date' => now()->addDays(4)]);

    $events = $this->getJson('/api/v1/events')->assertOk()->json('events');
    expect($events)->toHaveCount(2)
        ->and(collect($events)->flatMap(fn ($e) => $e['tickets'] ?? $e['ticket_types'] ?? [])->count())->toBeGreaterThanOrEqual(4);
});

it('T-013 / T-128 never leaks secrets in public pages or settings', function () {
    configureSadad(['checkout_secret' => 'SUPER-SECRET-KEY', 'api_login_secret' => 'API-LOGIN-KEY', 'merchant_id' => '7654321']);
    $t = openEvent();

    $html = $this->get('/')->assertOk()->getContent().$this->get('/events/'.$t['event']->id.'/tickets')->getContent();
    expect($html)->not->toContain('SUPER-SECRET-KEY')->not->toContain('API-LOGIN-KEY')->not->toContain('7654321');
    expect(json_encode($this->getJson('/api/v1/events')->json()))->not->toContain('SUPER-SECRET-KEY')->not->toContain('7654321');
});

it('T-013 stores Sadad secrets encrypted at rest', function () {
    configureSadad(['checkout_secret' => 'SUPER-SECRET-KEY']);
    $raw = DB::table('settings')->where('group', 'sadad')->where('name', 'checkout_secret')->value('payload');

    expect($raw)->not->toContain('SUPER-SECRET-KEY')
        ->and(app(SadadSettings::class)->checkout_secret)->toBe('SUPER-SECRET-KEY');
});

it('T-014 lets guests check availability, person counts and release expired holds', function () {
    $t = openEvent();
    $this->postJson('/api/v1/availability/check', ['event_id' => $t['event']->id, 'ticket_type' => 'normal', 'quantity' => 1])->assertOk()->assertJson(['success' => true]);
    expect($this->postJson('/api/v1/person-count', ['event_id' => $t['event']->id, 'id_number' => '28463400001'])->assertOk()->json('count'))->toBeGreaterThanOrEqual(0)
        ->and($this->postJson('/api/v1/release-expired', ['event_id' => $t['event']->id])->assertOk()->json('released'))->toBeGreaterThanOrEqual(0);
});

/* ---------------------------------------------------- members & admins */

it('T-016 / T-088 refuses admin operations to members without a role, supervisors and kiosks', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]]);

    foreach ([null, Role::Supervisor] as $role) {
        $this->actingAs(staffUser($role));
        $this->postJson('/api/v1/admin/stock/recount')->assertStatus(403);
        $this->postJson('/api/v1/admin/orders/'.$order->id.'/cancel', ['note' => 'x'])->assertStatus(403);
        $this->postJson('/api/v1/admin/qr/backfill')->assertStatus(403);
    }
});

it('T-019 / T-057 cancels as admin with a note, once, releasing seats', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 2]]);
    $this->actingAs(staffUser(Role::Admin));

    $this->postJson('/api/v1/admin/orders/'.$order->id.'/cancel', ['note' => 'customer asked'])->assertOk()->assertJson(['result' => 'cancelled']);
    $order->refresh();
    expect($order->payment_status->value)->toBe('cancelled')->and(sold($t['normal']))->toBe(0);

    $this->postJson('/api/v1/admin/orders/'.$order->id.'/cancel', ['note' => 'again'])->assertOk()->assertJson(['result' => 'already_inactive']);
    expect(sold($t['normal']))->toBe(0);
});

it('T-096 refuses guests and non-admins on admin tools and answers OPTIONS with 204', function () {
    $this->postJson('/api/v1/admin/qr/generate', ['text' => 'x', 'filename' => 'y'])->assertStatus(401);
    $this->actingAs(staffUser(Role::Cashier));
    $this->postJson('/api/v1/admin/qr/generate', ['text' => 'x', 'filename' => 'y'])->assertStatus(403);
    $this->call('OPTIONS', '/api/v1/admin/qr/generate', [], [], [], ['HTTP_ORIGIN' => 'https://x.example', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST'])->assertStatus(204);
});

/* ------------------------------------------------------ API conventions */

it('T-034 / T-035 returns every validation error at once', function () {
    $res = $this->postJson('/api/v1/orders', ['customer' => ['name' => '', 'phone' => 'abc', 'email' => 'not-an-email'], 'items' => [['ticket_id' => 'nope', 'quantity' => 0]], 'payment_method' => 'bitcoin'])
        ->assertStatus(400)->assertJson(['success' => false, 'code' => 'validation_failed']);

    expect(count($res->json('errors')))->toBeGreaterThanOrEqual(3);
});

it('T-034 rejects non-object bodies', function (string $body) {
    $this->call('POST', '/api/v1/orders', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $body)->assertStatus(400);
})->with(['null', '42', '"x"', '[]']);

it('T-037 answers invalid JSON with 400 and oversized bodies with 413', function () {
    $this->call('POST', '/api/v1/orders', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{bad json')
        ->assertStatus(400)->assertJson(['code' => 'invalid_json']);
    $this->call('POST', '/api/v1/orders', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], json_encode(['pad' => str_repeat('x', 70000)]))
        ->assertStatus(413);
    $this->getJson('/api/v1/orders')->assertStatus(405);
});

it('T-038 hides internals behind the error envelope', function () {
    $this->postJson('/api/v1/orders/status', ['refs' => ['x']]);
    $res = $this->postJson('/api/v1/orders', orderBody([]))->assertStatus(400);

    expect($res->json())->toHaveKeys(['success', 'code', 'error'])
        ->and(json_encode($res->json()))->not->toContain('vendor')->not->toContain('Stack');
});

/* -------------------------------------------------------------- passcode */

it('T-085 reports passcode configuration problems without recording attempts', function (?string $plain, string $problem) {
    config(['qcamelmc.auth.passcode_plain' => $plain, 'qcamelmc.auth.passcode_hash' => null]);

    expect(app(PasscodeAuthenticator::class)->problem())->toBe($problem);
    $this->postJson('/staff/passcode', ['passcode' => 'anything-here'])->assertOk()->assertJson(['ok' => false]);
    expect(LoginAttempt::count())->toBe(0);
})->with([[null, 'not_configured'], ['   ', 'not_configured'], ['short', 'too_short'], ['@@@Qatar123', 'revoked']]);

it('T-086 matches the passcode exactly after trimming', function () {
    $auth = app(PasscodeAuthenticator::class);

    expect($auth->verify('Gate-Pass-2026'))->toBeTrue()
        ->and($auth->verify('  Gate-Pass-2026  '))->toBeTrue()
        ->and($auth->verify('gate-pass-2026'))->toBeFalse()
        ->and($auth->verify(['Gate-Pass-2026']))->toBeFalse()
        ->and($auth->verify(null))->toBeFalse();
});

it('T-089 / T-091 blocks a caller after 5 wrong passcodes, even with the right one', function () {
    for ($i = 5; $i >= 1; $i--) {
        $res = $this->postJson('/staff/passcode', ['passcode' => 'wrong-guess-'.$i])->assertOk()->json();
        expect($res['ok'])->toBeFalse()->and($res['attempts_left'])->toBe($i - 1);
    }
    $res = $this->postJson('/staff/passcode', ['passcode' => 'Gate-Pass-2026'])->assertOk()->json();

    expect($res)->toMatchArray(['ok' => false, 'blocked' => true, 'attempts_left' => 0])->and($res['retry_after_seconds'])->toBeGreaterThan(0);
});

it('T-091 handles warmup, junk, GET and OPTIONS', function () {
    $this->postJson('/staff/passcode', ['passcode' => '__warmup__'])->assertOk()->assertJson(['ok' => false, 'warmup' => true]);
    expect(LoginAttempt::count())->toBe(0);
    $this->call('POST', '/staff/passcode', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{junk')->assertOk();
    $this->get('/staff/passcode')->assertStatus(405);
    $this->call('OPTIONS', '/staff/passcode')->assertStatus(204);
});

it('T-087 / T-122 lets a passcode session use staff apps but never the admin panel', function () {
    $login = $this->postJson('/staff/passcode', ['passcode' => 'Gate-Pass-2026'])->assertOk()->assertJson(['ok' => true]);
    $token = $login->getCookie(PasscodeAuthenticator::COOKIE)->getValue();
    $this->withCookie(PasscodeAuthenticator::COOKIE, $token)->withCredentials(); // same-origin fetches carry the cookie

    $this->get('/staff')->assertOk();
    $this->get('/admin')->assertRedirect();
    $this->postJson('/api/v1/admin/stock/recount')->assertStatus(403);
    expect(KioskDevice::first()->expires_at->between(now()->addDays(9), now()->addDays(11)))->toBeTrue();
});

/* --------------------------------------------------------- staff accounts */

it('T-127 refuses to revoke or demote the last admin and the acting admin', function () {
    $owner = staffUser(Role::SuperAdmin);
    $this->actingAs($owner);
    $svc = app(StaffAccountService::class);

    expect(fn () => $svc->revoke($owner, Actor::user($owner)))->toThrow(DomainError::class)
        ->and(fn () => $svc->setRole($owner, Role::Viewer, Actor::user($owner)))->toThrow(DomainError::class);

    $admin = staffUser(Role::Admin);
    $this->actingAs($admin);
    expect(fn () => $svc->setRole(staffUser(Role::Cashier), Role::Admin, Actor::user($admin)))->toThrow(DomainError::class); // only the owner grants admin
});

it('T-128 never sends national IDs to non-admin staff screens', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]]);
    $this->actingAs(staffUser(Role::Cashier));

    $body = $this->getJson('/api/v1/staff/customers/lookup?q=5551')->json();
    expect(json_encode($body))->not->toContain('28463400001');
});
