<?php

use App\Domain\Analytics\VisitorAnalyticsQuery;
use App\Domain\Booking\Actions\OrderStatusActions;
use App\Domain\Messaging\InvoiceDispatcher;
use App\Domain\Payments\Actions\ConfirmPaymentAction;
use App\Domain\Payments\Actions\FailPaymentAction;
use App\Domain\Payments\PaymentCheckThrottle;
use App\Domain\Payments\Sadad\SadadConfig;
use App\Domain\Payments\Sadad\SadadDiagnose;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\Role;
use App\Jobs\SendWhatsAppInvoice;
use App\Models\Activity;
use App\Models\LoginAttempt;
use App\Models\Order;
use App\Models\PageView;
use App\Models\PosReceipt;
use App\Settings\AutomationSettings;
use App\Support\PublicSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
| Cross-cutting platform rules — T-020, T-059, T-073, T-084, T-092, T-093,
| T-095, T-110, T-114, T-115, T-123, T-124, T-125.
*/

it('T-020 lets the system context create orders and read secrets', function () {
    configureSadad(['checkout_secret' => 'SYS-SECRET']);
    $t = openEvent();

    expect(app(SadadConfig::class)->checkoutSecret())->toBe('SYS-SECRET')
        ->and(placeOrder([[$t['normal'], 1]], 'cash_pos', [], Actor::system())->exists)->toBeTrue();
});

it('T-059 throttles payment checks per reference', function () {
    configureSadad();
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad');
    $throttle = app(PaymentCheckThrottle::class);

    expect($throttle->claim($order->booking_reference))->toBeTrue()
        ->and($throttle->claim($order->booking_reference))->toBeFalse();

    $this->travel(11)->seconds();
    expect($throttle->claim($order->booking_reference))->toBeTrue();
});

it('T-073 maps cancelled orders to failed or cancelled for customers', function () {
    configureSadad();
    $t = openEvent();
    $failed = placeOrder([[$t['normal'], 1]], 'sadad');
    Mail::fake();
    app(FailPaymentAction::class)->execute($failed->booking_reference, 'declined');
    $staff = placeOrder([[$t['normal'], 1]]);
    $admin = staffUser(Role::Admin);
    $this->actingAs($admin);
    app(OrderStatusActions::class)->cancel($staff, 'x', Actor::user($admin));

    expect($failed->fresh()->publicStatus())->toBe('failed')
        ->and($staff->fresh()->publicStatus())->toBe('cancelled')
        ->and($staff->fresh()->isFinalCancellation())->toBeTrue();
});

it('T-084 diagnoses the Sadad setup without revealing the secret', function () {
    configureSadad(['checkout_secret' => 'DIAG-SECRET', 'website_domain' => 'https://qcamel.test/x', 'api_login_secret' => null]);
    Http::fake(['*' => Http::response(['error' => 'unauthorized'], 401)]);
    $r = app(SadadDiagnose::class)->run();

    expect($r)->toHaveKeys(['ready', 'checks', 'callbackUrl'])
        ->and(json_encode($r))->not->toContain('DIAG-SECRET')
        ->and(collect($r['checks'])->firstWhere('id', 'website')['status'] ?? null)->toBe('warn');
});

it('T-092 locks an account e-mail after 5 failed sign-ins and logs the block once', function () {
    $user = staffUser(Role::Admin, ['email' => 'lock@qcamel.test']);
    Mail::fake();
    foreach (range(1, 5) as $_) {
        $this->post('/login', ['email' => 'lock@qcamel.test', 'password' => 'wrong-password']);
    }
    $this->post('/login', ['email' => 'lock@qcamel.test', 'password' => 'password']);

    $this->assertGuest();
    expect(Activity::query()->where('log_name', 'login_blocked')->count())->toBe(1)
        ->and(LoginAttempt::query()->where('identifier', 'lock@qcamel.test')->where('success', false)->count())->toBeGreaterThanOrEqual(5);
});

it('T-093 treats only a real bearer token as a session', function () {
    $this->withHeader('Authorization', 'bearer not-a-token')->postJson('/api/v1/admin/stock/recount')->assertStatus(401);

    $admin = staffUser(Role::Admin);
    $token = $admin->createToken('t', ['*'])->plainTextToken;
    $this->withHeader('Authorization', 'BEARER '.$token)->postJson('/api/v1/admin/stock/recount')->assertOk();
});

it('T-095 maps legacy error strings to codes', function () {
    $limit = DomainError::fromLegacyMessage('TICKET_LIMIT_EXCEEDED: لا يمكن حجز أكثر من 5 تذاكر');
    expect($limit->errorCode)->toBe('ticket_limit_exceeded')->and($limit->status)->toBe(422)->and($limit->userMessage())->toContain('5 تذاكر');

    $stock = DomainError::fromLegacyMessage('insufficient_stock:vip');
    expect($stock->status)->toBe(409)->and($stock->toArray()['ticket_type'])->toBe('vip')
        ->and(DomainError::fromLegacyMessage('insufficient_stock')->status)->toBe(409)
        ->and(DomainError::fromLegacyMessage('something unexpected'))->toBeNull();
});

it('T-110 grants admins the dashboard and supervisors only the staff apps', function () {
    $admin = staffUser(Role::Admin);
    $supervisor = staffUser(Role::Supervisor);
    $panel = filament()->getPanel('admin');

    expect($admin->canAccessPanel($panel))->toBeTrue()->and($supervisor->canAccessPanel($panel))->toBeFalse()
        ->and(Actor::user($supervisor)->can('pos.sell'))->toBeTrue()->and(Actor::user($supervisor)->can('settings.manage'))->toBeFalse();
});

it('T-114 renders an auto-submitting Sadad form only for Sadad hosts, values intact', function () {
    configureSadad();
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad', ['customer' => ['email' => 'a&b"<x>@example.com']]);

    $html = $this->get('/payment/redirect/'.$order->booking_reference)->assertOk()->getContent();
    expect($html)->toContain('action="https://sadadqa.com/webpurchase"')->toContain('method="post"')
        ->toContain('name="ORDER_ID" value="'.$order->booking_reference.'"');

    $evil = ['url' => 'https://sadadqa.com.evil.example/webpurchase', 'fields' => []];
    $this->withSession(['sadad_payment.'.$order->booking_reference => $evil])->get('/payment/redirect/'.$order->booking_reference)->assertStatus(400);
});

it('T-115 remembers the last 10 bookings, newest last, without duplicates', function () {
    foreach (range(1, 12) as $i) {
        PublicSession::remember('QTR-1-1-2026-'.str_pad((string) $i, 12, '0', STR_PAD_LEFT));
    }
    PublicSession::remember('QTR-1-1-2026-000000000005');
    $list = PublicSession::bookings();

    expect($list)->toHaveCount(10)->and(end($list))->toBe('QTR-1-1-2026-000000000005')
        ->and(array_count_values($list)['QTR-1-1-2026-000000000005'])->toBe(1);

    session()->put('bookings', 'not-an-array'); // corrupt value
    expect(PublicSession::bookings())->toBeArray();
});

it('T-123 keeps receipt images private behind short-lived signed links', function () {
    $staff = staffUser(Role::Cashier);
    $receipt = PosReceipt::query()->create(['image_path' => 'x.jpg', 'created_by' => $staff->id]);
    Storage::fake('receipts');
    Storage::disk('receipts')->put('x.jpg', 'jpg');

    $this->get('/staff/receipts/'.$receipt->id.'/image')->assertRedirect(); // guest
    $this->actingAs($staff)->get('/staff/receipts/'.$receipt->id.'/image')->assertStatus(403); // unsigned
    $url = $receipt->temporaryImageUrl();
    $this->actingAs($staff)->get($url)->assertOk();
    $this->travel(6)->minutes();
    $this->actingAs($staff)->get($url)->assertStatus(403);
});

it('T-124 sends each pending invoice once even when the dispatcher runs twice', function () {
    $s = app(AutomationSettings::class);
    $s->webhook_url = 'https://n8n.qcamel.test/webhook/wa';
    $s->webhook_enabled = true;
    $s->invoice_auto_send_enabled = true;
    $s->invoice_send_delay_min = 0;
    $s->invoice_send_delay_max = 0;
    $s->save();
    Http::fake(['n8n.qcamel.test/*' => Http::response(['ok' => true])]);
    configureSadad();
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad');
    app(ConfirmPaymentAction::class)->execute($order->booking_reference, 'SD-INV');
    Bus::fake([SendWhatsAppInvoice::class]);
    Order::query()->whereKey($order->id)->update(['n8n_response_message' => null, 'send_attempt_count' => 0, 'created_at' => now()->subMinutes(5)]);

    app(InvoiceDispatcher::class)->run(manual: true);
    app(InvoiceDispatcher::class)->run(manual: true);

    Bus::assertDispatchedTimes(SendWhatsAppInvoice::class, 1);
    expect($order->fresh()->send_attempt_count)->toBe(1);
});

it('T-125 groups page views by the Qatar day', function () {
    PageView::query()->create(['session_id' => 's1', 'page_path' => '/', 'viewed_at' => CarbonImmutable::parse('2026-10-09 22:30:00', 'UTC'), 'is_new_visitor' => true, 'device_type' => 'mobile']);
    $rows = collect(app(VisitorAnalyticsQuery::class)->perDate('2026-10-09', '2026-10-10'))->keyBy('date');

    expect($rows->has('2026-10-10'))->toBeTrue()->and($rows->has('2026-10-09'))->toBeFalse();
});
