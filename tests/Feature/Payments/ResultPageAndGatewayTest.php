<?php

use App\Domain\Booking\Actions\OrderStatusActions;
use App\Domain\Payments\Actions\ConfirmPaymentAction;
use App\Domain\Payments\Sadad\ApiResult;
use App\Domain\Payments\Sadad\SadadApiClient;
use App\Domain\Shared\Actor;
use App\Enums\Role;
use App\Livewire\Public\PaymentResult;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
| Payment result page and Sadad API access — T-080, T-081, T-094, T-113.
*/

beforeEach(fn () => configureSadad(['api_login_secret' => 'API-KEY']));

it('T-113 goes straight to the confirmation for a confirmed order without verifying', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad');
    app(ConfirmPaymentAction::class)->execute($order->booking_reference, 'SD-1');
    $this->mock(SadadApiClient::class, fn ($m) => $m->shouldNotReceive('lookupTransaction'));

    Livewire::withQueryParams(['ref' => $order->booking_reference])->test(PaymentResult::class)
        ->assertRedirect(route('confirmation', ['reference' => $order->booking_reference]));
});

it('T-113 verifies a pending order immediately and keeps checking', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad');
    $this->mock(SadadApiClient::class, fn ($m) => $m->shouldReceive('lookupTransaction')->once()->andReturn(ApiResult::notFound()));

    Livewire::withQueryParams(['ref' => $order->booking_reference])->test(PaymentResult::class)->assertSet('phase', 'checking');
});

it('T-113 never verifies a staff-cancelled order and sends cash orders to the confirmation', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad');
    $admin = staffUser(Role::Admin);
    app(OrderStatusActions::class)->cancel($order, 'x', Actor::user($admin));
    $this->mock(SadadApiClient::class, fn ($m) => $m->shouldNotReceive('lookupTransaction'));

    Livewire::withQueryParams(['ref' => $order->booking_reference])->test(PaymentResult::class)->assertSet('phase', 'failed');

    $cash = placeOrder([[$t['normal'], 1]], 'cash_pos');
    Livewire::withQueryParams(['ref' => $cash->booking_reference])->test(PaymentResult::class)
        ->assertRedirect(route('confirmation', ['reference' => $cash->booking_reference]));
});

it('T-113 shows not found for an unknown reference', function () {
    Livewire::withQueryParams(['ref' => 'QTR-1-1-2026-AAAAAAAAAAAA'])->test(PaymentResult::class)->assertSet('phase', 'not_found');
});

it('T-080 logs in with the documented body and lists transactions by reference', function () {
    Http::fake([
        '*/api/userbusinesses/login' => Http::response(['accessToken' => 'TOKEN-123'], 200),
        '*/api/transactions/listTransactions*' => Http::response([['transactionstatusId' => 3, 'amount' => '100.00', 'website_ref_no' => 'QTR-1-1-2026-ABCDEFABCDEF', 'invoicenumber' => 'SD77']], 200),
    ]);
    $result = app(SadadApiClient::class)->lookupTransaction('QTR-1-1-2026-ABCDEFABCDEF');

    expect($result->kind)->toBe('success')->and($result->transactionNumber)->toBe('SD77');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/userbusinesses/login') && $r['sadadId'] === 1234567 && $r['secretKey'] === 'API-KEY' && $r['domain'] === 'qcamel.test');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'listTransactions') && $r->hasHeader('Authorization', 'TOKEN-123') && str_contains($r->url(), 'website_ref_no=QTR-1-1-2026-ABCDEFABCDEF'));
});

it('T-080 / T-081 tries live then sandbox in auto mode and reports login errors without throwing', function () {
    configureSadad(['environment' => 'auto', 'api_login_secret' => 'API-KEY']);
    Http::fake(['*' => Http::response(['error' => 'no'], 401)]);
    $result = app(SadadApiClient::class)->lookupTransaction('QTR-1-1-2026-ABCDEFABCDEF');

    expect($result->kind)->toBe('unavailable')->and($result->error)->toContain('login_live_401')->toContain('login_sandbox_401');
    expect(app(SadadApiClient::class)->lookupTransaction("x' OR 1=1")->kind)->toBe('unavailable');
});

it('T-094 answers cross-origin API calls with CORS headers but keeps the web app same-origin', function () {
    $this->withHeader('Origin', 'https://partner.example')->getJson('/api/v1/events')
        ->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');
    expect($this->withHeader('Origin', 'https://partner.example')->get('/')->headers->get('Access-Control-Allow-Origin'))->toBeNull();
});
