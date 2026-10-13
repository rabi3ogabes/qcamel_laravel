<?php

use App\Domain\Payments\Actions\ConfirmPaymentAction;
use App\Domain\Payments\Actions\FailPaymentAction;
use App\Domain\Payments\Sadad\ApiResult;
use App\Domain\Payments\Sadad\SadadApiClient;
use App\Enums\Role;
use App\Mail\BookingInvoiceMail;
use App\Mail\PaymentFailedMail;
use App\Models\Order;
use App\Models\PaymentError;
use App\Models\PaymentEvent;
use Illuminate\Support\Facades\Mail;

/*
| Payment HTTP surface — T-059…T-061, T-067…T-072 (F-PAY-024…040).
*/

beforeEach(fn () => configureSadad(['site_url' => 'https://tickets.qcamel.test']));

function apiResult(string $kind, Order $order): ApiResult
{
    return match ($kind) {
        'success' => ApiResult::success('SD-'.substr($order->booking_reference, -6), (float) $order->total_amount, $order->booking_reference, true),
        'failed' => ApiResult::failed('SD-F'),
        default => ApiResult::notFound(),
    };
}

it('T-069 redirects the customer back to the result page after the callback (POST and GET)', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad');
    $this->mock(SadadApiClient::class, fn ($m) => $m->shouldReceive('lookupTransaction')->andReturn(apiResult('success', $order)));

    $r = $this->post('/payments/sadad/callback', ['ORDERID' => $order->booking_reference, 'transaction_status' => '3']);
    $r->assertStatus(303)->assertRedirect('https://tickets.qcamel.test/payment/result?ref='.$order->booking_reference);
    $this->get('/payments/sadad/callback?ORDERID='.$order->booking_reference)->assertStatus(303);
    $this->post('/sadad-callback', ['ORDERID' => $order->booking_reference])->assertStatus(303);

    expect($order->fresh()->payment_status->value)->toBe('confirmed');
});

it('T-069 never throws on garbage and shows the reference when no safe base exists', function () {
    configureSadad(['site_url' => null]);
    config(['qcamelmc.sadad.frontend_url' => null]); // no .env fallback either → no safe base

    $this->post('/payments/sadad/callback', ['junk' => str_repeat('<x>', 50)])->assertSuccessful();
    $this->post('/payments/sadad/callback', ['ORDERID' => 'QTR-1-1-2026-ABCDEFABCDEF', 'transaction_status' => '9'])
        ->assertSuccessful()->assertSee('QTR-1-1-2026-ABCDEFABCDEF');
});

it('T-070 always answers the webhook with success and never double-confirms on replay', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad');
    $this->mock(SadadApiClient::class, fn ($m) => $m->shouldReceive('lookupTransaction')->andReturn(apiResult('success', $order)));
    Mail::fake();

    $body = ['websiteRefNo' => $order->booking_reference, 'transactionStatus' => 3, 'transactionNumber' => 'SD-W1'];
    $this->postJson('/payments/sadad/webhook', $body)->assertOk()->assertExactJson(['status' => 'success']);
    $this->postJson('/payments/sadad/webhook', $body)->assertOk()->assertExactJson(['status' => 'success']);
    $this->call('POST', '/payments/sadad/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{not json')->assertOk();

    expect($order->fresh()->payment_status->value)->toBe('confirmed');
    Mail::assertSent(BookingInvoiceMail::class, 1);
});

it('T-067 sends the confirmation follow-ups exactly once', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad');
    Mail::fake();

    app(ConfirmPaymentAction::class)->execute($order->booking_reference, 'SD-1');
    app(ConfirmPaymentAction::class)->execute($order->booking_reference, 'SD-1'); // replay → already_confirmed

    Mail::assertSent(BookingInvoiceMail::class, 1);
});

it('T-068 records a payment error and mails the customer on failure', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad');
    Mail::fake();

    app(FailPaymentAction::class)->execute($order->booking_reference, 'Card declined by issuing bank');

    $error = PaymentError::query()->where('booking_reference', $order->booking_reference)->first();
    expect($error)->not->toBeNull()->and($error->error_code)->toBe('PAYMENT_FAILED');
    Mail::assertSent(PaymentFailedMail::class);
});

it('T-074 copies the order context into the payment error record', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 2]], 'sadad');
    Mail::fake();
    app(FailPaymentAction::class)->execute($order->booking_reference, 'declined');
    $error = PaymentError::query()->where('booking_reference', $order->booking_reference)->first();

    expect($error->event_id)->toBe($order->event_id)->and((int) $error->quantity)->toBe(2)
        ->and($error->customer_name)->toBe($order->customer->name);
});

it('T-071 verifies a payment and throttles repeat checks for guests', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad');
    $this->mock(SadadApiClient::class, fn ($m) => $m->shouldReceive('lookupTransaction')->andReturn(ApiResult::notFound()));

    $this->postJson('/api/v1/payments/verify', ['booking_reference' => $order->booking_reference])
        ->assertOk()->assertJson(['success' => true, 'checked' => true, 'payment_status' => 'pending']);
    $this->postJson('/api/v1/payments/verify', ['booking_reference' => $order->booking_reference])
        ->assertOk()->assertJson(['checked' => false]);

    $this->postJson('/api/v1/payments/verify', ['booking_reference' => 'bad ref!'])->assertStatus(400);
    $this->postJson('/api/v1/payments/verify', ['booking_reference' => 'QTR-1-1-2026-AAAAAAAAAAAA'])->assertStatus(404);
});

it('T-071 lets an admin bypass the throttle and logs the admin source', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad');
    $this->mock(SadadApiClient::class, fn ($m) => $m->shouldReceive('lookupTransaction')->andReturn(ApiResult::notFound()));
    $this->actingAs(staffUser(Role::Admin));

    foreach ([1, 2] as $_) {
        $this->postJson('/api/v1/payments/verify', ['booking_reference' => $order->booking_reference])->assertJson(['checked' => true]);
    }
    expect(PaymentEvent::query()->where('booking_reference', $order->booking_reference)->pluck('source')->unique()->all())->toBe(['admin']);
});

it('T-072 restarts only a pending online order with a valid hold', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad');

    $res = $this->postJson('/api/v1/payments/sadad/restart', ['booking_reference' => $order->booking_reference])->assertOk();
    expect($res->json('payment.fields.ORDER_ID'))->toBe($order->booking_reference)
        ->and($res->json('payment.fields.TXN_AMOUNT'))->toBe((string) $order->total_amount);

    $cash = placeOrder([[$t['normal'], 1]], 'cash_pos');
    $this->postJson('/api/v1/payments/sadad/restart', ['booking_reference' => $cash->booking_reference])->assertStatus(409)->assertJson(['code' => 'not_payable']);
    $this->postJson('/api/v1/payments/sadad/restart', ['booking_reference' => 'QTR-1-1-2026-AAAAAAAAAAAA'])->assertStatus(404);

    $order->forceFill(['payment_expires_at' => now()->subMinute()])->save();
    $this->postJson('/api/v1/payments/sadad/restart', ['booking_reference' => $order->booking_reference])->assertStatus(409);
});

it('T-072 answers 503 when the gateway is not configured', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad');
    configureSadad(['checkout_secret' => null]);

    $this->postJson('/api/v1/payments/sadad/restart', ['booking_reference' => $order->booking_reference])->assertStatus(503);
});

it('T-060 returns only real bookings from a status lookup, without private fields', function () {
    $t = openEvent();
    $a = placeOrder([[$t['normal'], 1]], 'sadad');
    $b = posOrder([[$t['normal'], 1]]);
    $c = placeOrder([[$t['normal'], 1]], 'sadad');
    app(FailPaymentAction::class)->execute($c->booking_reference, 'declined');
    auth()->logout();

    $res = $this->postJson('/api/v1/orders/status', ['refs' => [$a->booking_reference, 'QTR-NOPE', 'bad ref!', $b->booking_reference, $c->booking_reference]])->assertOk();
    $orders = collect($res->json('orders'))->keyBy('booking_reference');

    expect($orders)->toHaveCount(3)
        ->and($orders[$b->booking_reference]['payment_status'])->toBe('confirmed')
        ->and($orders[$c->booking_reference]['payment_status'])->toBe('failed')
        ->and($orders[$a->booking_reference])->toHaveKeys(['event_id'])
        ->and(json_encode($res->json()))->not->toContain('id_number')->not->toContain('customer_id')->not->toContain('payment_note');
});

it('T-015 hides the national ID from guests on the invoice', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]]);

    $this->getJson('/api/v1/invoice/QTR-NOPE')->assertStatus(404);
    $this->getJson('/api/v1/invoice/'.rawurlencode('bad ref!'))->assertStatus(400);
    $this->getJson('/api/v1/invoice/QTR-1-1-2026-AAAAAAAAAAAA')->assertStatus(404);
    $body = $this->getJson('/api/v1/invoice/'.$order->booking_reference)->assertOk()->json();
    expect(json_encode($body))->not->toContain('id_number')->not->toContain('28463400001');
});

it('T-018 shows the national ID on the invoice to admins', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]]);
    $holderId = $order->holders()->first()->getRawOriginal('id_number');
    $this->actingAs(staffUser(Role::Admin));

    expect($this->getJson('/api/v1/invoice/'.$order->booking_reference)->assertOk()->json('order.ticket_holders.0.id_number'))->toBe($holderId);
});

it('T-061 releases expired holds on demand and stops counting them as held', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 2]], 'sadad');
    $order->forceFill(['payment_expires_at' => now()->subMinute()])->save();

    expect($this->postJson('/api/v1/release-expired', ['event_id' => $t['event']->id])->assertOk()->json('released'))->toBeGreaterThanOrEqual(1)
        ->and(sold($t['normal']))->toBe(0);
});
