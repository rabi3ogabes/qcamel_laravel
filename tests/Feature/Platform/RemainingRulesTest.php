<?php

use App\Domain\Booking\Actions\CreateOrderAction;
use App\Domain\Booking\Data\OrderRequestValidator;
use App\Domain\Messaging\N8nPayloads;
use App\Domain\Payments\Actions\ConfirmPaymentAction;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Domain\Staff\PasscodeAuthenticator;
use App\Enums\Role;
use App\Models\LoginAttempt;
use App\Models\PosUser;
use Illuminate\Support\Facades\Storage;

/*
| T-005, T-010, T-017, T-090, T-107, T-111.
*/

it('T-005 keeps stock in the ledger only and enforces the per-person cap', function () {
    $t = openEvent();
    placeOrder([[$t['normal'], 2]]);
    expect(sold($t['normal']))->toBe(2); // one ledger, no trigger/listener double counting

    $id = '30022223333';
    $body = orderBody([[$t['normal'], 5]]);
    foreach ($body['holders'] as &$h) {
        $h['id_number'] = $id;
    }
    app(CreateOrderAction::class)->execute(app(OrderRequestValidator::class)->validate($body), Actor::guest());
    $more = orderBody([[$t['normal'], 1]]);
    $more['holders'][0]['id_number'] = $id;

    expect(fn () => app(CreateOrderAction::class)->execute(app(OrderRequestValidator::class)->validate($more), Actor::guest()))
        ->toThrow(DomainError::class);
});

it('T-010 gives guests no way to change an order', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]]);
    $before = $order->fresh()->toArray();

    $this->postJson('/api/v1/admin/orders/'.$order->id.'/cancel', ['note' => 'x'])->assertStatus(401);
    $this->putJson('/api/v1/orders', ['booking_reference' => $order->booking_reference, 'payment_status' => 'confirmed'])->assertStatus(405);
    $this->postJson('/api/v1/orders/status', ['refs' => [$order->booking_reference], 'payment_status' => 'confirmed'])->assertOk();

    expect($order->fresh()->toArray())->toBe($before);
});

it('T-017 never lets a member write to the QR disk, but lets an admin generate', function () {
    Storage::fake((string) config('qcamelmc.qr.disk', 'qr'));
    $this->actingAs(staffUser(null));
    $this->postJson('/api/v1/admin/qr/generate', ['text' => 'QTR-A-TKT01', 'filename' => 'QTR-A-TKT01'])->assertStatus(403);
    expect(Storage::disk((string) config('qcamelmc.qr.disk', 'qr'))->allFiles())->toBe([]);

    $this->actingAs(staffUser(Role::Admin));
    $this->postJson('/api/v1/admin/qr/generate', ['text' => 'QTR-A-TKT01', 'filename' => 'QTR-A-TKT01'])->assertOk();
    Storage::disk((string) config('qcamelmc.qr.disk', 'qr'))->assertExists('QTR-A-TKT01.png');
});

it('T-090 blocks every caller after 60 wrong passcodes in 15 minutes from any address', function () {
    $max = (int) config('qcamelmc.auth.passcode_global_max', 60);
    foreach (range(1, $max) as $i) {
        LoginAttempt::query()->create(['identifier' => PasscodeAuthenticator::GLOBAL_ID, 'kind' => 'passcode', 'success' => false, 'ip_address' => '10.0.'.intdiv($i, 250).'.'.($i % 250), 'attempted_at' => now()->subMinutes(5)]);
    }

    $res = $this->postJson('/staff/passcode', ['passcode' => 'Gate-Pass-2026'])->assertOk()->json();
    expect($res['ok'])->toBeFalse()->and($res['blocked'] ?? false)->toBeTrue();
});

it('T-107 builds the paid payload with holders, prices and QR data', function () {
    configureSadad();
    Storage::fake((string) config('qcamelmc.qr.disk', 'qr'));
    $t = openEvent();
    $order = placeOrder([[$t['vip'], 1], [$t['normal'], 1]], 'sadad');
    app(ConfirmPaymentAction::class)->execute($order->booking_reference, 'SD-107');
    $p = N8nPayloads::paymentConfirmed($order->fresh());
    $holder = collect($p['ticket_holders'])->firstWhere('ticket_type', 'vip');

    expect($p)->toHaveKeys(['customers', 'events', 'ticket_holders', 'customer', 'event', 'action', 'timestamp', 'sadad_response'])
        ->and($holder)->toHaveKeys(['qr_code_text', 'qr_code_image', 'phone_international', 'ticket_price'])
        ->and((float) $holder['ticket_price'])->toBe(200.0)
        ->and($holder['phone_international'])->toStartWith('974');
});

it('T-111 attributes POS sales to the cashier and returns the limit message as 422', function () {
    $t = openEvent();
    $cashier = PosUser::query()->create(['name' => 'كاشير 2', 'is_active' => true]);
    $order = posOrder([[$t['normal'], 1]], null, $cashier);
    expect($order->pos_user_id)->toBe($cashier->id);

    $e = DomainError::fromLegacyMessage('TICKET_LIMIT_EXCEEDED: لا يمكن حجز أكثر من 5 تذاكر للشخص الواحد');
    expect($e->status)->toBe(422)->and($e->userMessage())->toBe('لا يمكن حجز أكثر من 5 تذاكر للشخص الواحد');
});

it('reads the Sadad .env fallbacks through config so they survive config:cache', function () {
    configureSadad(['merchant_id' => null, 'website_domain' => null, 'checkout_secret' => null]);
    config(['qcamelmc.sadad.merchant_id' => '1112223', 'qcamelmc.sadad.website_domain' => 'qcamel.test', 'qcamelmc.sadad.checkout_secret' => 'FROM-ENV']);
    $cfg = app(\App\Domain\Payments\Sadad\SadadConfig::class);

    expect($cfg->isConfigured())->toBeTrue()->and($cfg->checkoutSecret())->toBe('FROM-ENV')->and($cfg->merchantId())->toBe('1112223');
});
