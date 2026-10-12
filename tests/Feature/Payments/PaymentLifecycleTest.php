<?php

use App\Domain\Booking\Actions\ExpireHoldsAction;
use App\Domain\Payments\Actions\ConfirmPaymentAction;
use App\Domain\Payments\Actions\FailPaymentAction;
use App\Domain\Payments\PaymentDecision;
use App\Domain\Payments\PaymentProcessor;
use App\Domain\Payments\Sadad\ApiResult;
use App\Domain\Payments\Sadad\NormalizedPayment;
use App\Domain\Payments\Sadad\SadadApiClient;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\Refund;

/*
| Payment lifecycle — T-046…T-052, T-062…T-066 (F-PAY-031…040).
*/

beforeEach(fn () => configureSadad());

function sadadOrder(array $t, int $qty = 2): Order
{
    return placeOrder([[$t['normal'], $qty]], 'sadad');
}

function fakeSadadApi(ApiResult $result): void
{
    test()->mock(SadadApiClient::class, fn ($m) => $m->shouldReceive('lookupTransaction')->andReturn($result));
}

it('T-046 confirms a pending online order without touching stock again', function () {
    $t = openEvent();
    $order = sadadOrder($t);
    expect(sold($t['normal']))->toBe(2);

    expect(app(ConfirmPaymentAction::class)->execute($order->booking_reference, 'SD-1001'))->toBe('confirmed');
    $order->refresh();

    expect($order->payment_status->value)->toBe('confirmed')->and($order->payment_id)->toBe('SD-1001')
        ->and($order->paid_at)->not->toBeNull()->and($order->confirmed_at)->not->toBeNull()
        ->and(sold($t['normal']))->toBe(2);
});

it('T-047 treats replays as already confirmed and never overwrites the payment id', function () {
    $t = openEvent();
    $order = sadadOrder($t);
    $confirm = app(ConfirmPaymentAction::class);
    $confirm->execute($order->booking_reference, 'SD-1');

    expect($confirm->execute($order->booking_reference, 'SD-1'))->toBe('already_confirmed')
        ->and($confirm->execute($order->booking_reference, 'SD-2'))->toBe('already_confirmed')
        ->and($order->fresh()->payment_id)->toBe('SD-1')
        ->and($confirm->execute('QTR-1-1-2026-AAAAAAAAAAAA', 'SD-3'))->toBe('order_not_found');
});

it('T-048 refuses a transaction already used by another order', function () {
    $t = openEvent();
    $a = sadadOrder($t, 1);
    $b = sadadOrder($t, 1);
    app(ConfirmPaymentAction::class)->execute($a->booking_reference, 'SD-SHARED');

    expect(app(ConfirmPaymentAction::class)->execute($b->booking_reference, 'SD-SHARED'))->toBe('transaction_already_used')
        ->and($b->fresh()->payment_status->value)->toBe('pending');
});

it('T-049 fails a pending order once and releases its seats once', function () {
    $t = openEvent();
    $order = sadadOrder($t);
    $fail = app(FailPaymentAction::class);

    expect($fail->execute($order->booking_reference, 'declined'))->toBe('failed');
    $order->refresh();
    expect($order->payment_status->value)->toBe('cancelled')->and($order->payment_note)->toBe('payment_failed')
        ->and($order->payment_error_reason)->toBe('declined')->and(sold($t['normal']))->toBe(0)
        ->and($fail->execute($order->booking_reference, 'declined'))->toBe('not_pending')
        ->and(sold($t['normal']))->toBe(0);

    $confirmed = sadadOrder($t, 1);
    app(ConfirmPaymentAction::class)->execute($confirmed->booking_reference, 'SD-7');
    expect($fail->execute($confirmed->booking_reference))->toBe('not_pending')
        ->and($confirmed->fresh()->payment_status->value)->toBe('confirmed');
});

it('T-050 expires only overdue pending Sadad orders, once', function () {
    $t = openEvent();
    $overdue = sadadOrder($t, 1);
    $fresh = sadadOrder($t, 1);
    $cash = placeOrder([[$t['normal'], 1]], 'cash_pos');
    // Mark overdue last: any new sale of the same event would expire it lazily (T-032).
    $overdue->forceFill(['payment_expires_at' => now()->subMinute()])->save();

    expect(app(ExpireHoldsAction::class)->all())->toBe(1);
    expect($overdue->fresh()->payment_status->value)->toBe('cancelled')->and($overdue->fresh()->payment_note)->toBe('expired')
        ->and($fresh->fresh()->payment_status->value)->toBe('pending')->and($cash->fresh()->payment_status->value)->toBe('pending')
        ->and(sold($t['normal']))->toBe(2)
        ->and(app(ExpireHoldsAction::class)->all())->toBe(0);
});

it('T-051 confirms a late payment on an expired order while seats remain', function () {
    $t = openEvent();
    $order = sadadOrder($t, 1);
    $order->forceFill(['payment_expires_at' => now()->subMinute()])->save();
    app(ExpireHoldsAction::class)->all();
    expect(sold($t['normal']))->toBe(0);

    expect(app(ConfirmPaymentAction::class)->execute($order->booking_reference, 'SD-LATE'))->toBe('confirmed');
    $order->refresh();
    expect($order->payment_status->value)->toBe('confirmed')->and($order->payment_note)->toBeNull()
        ->and($order->payment_error_reason)->toBeNull()->and(sold($t['normal']))->toBe(1);
});

it('T-052 never oversells a late payment when sold out, and opens a refund task', function () {
    $t = openEvent(normal: 1);
    $order = sadadOrder($t, 1);
    $order->forceFill(['payment_expires_at' => now()->subMinute()])->save();
    app(ExpireHoldsAction::class)->all();
    posOrder([[$t['normal'], 1]]); // the last seat goes to someone else

    expect(app(ConfirmPaymentAction::class)->execute($order->booking_reference, 'SD-NOSTOCK'))->toBe('paid_after_expiry_no_stock');
    $order->refresh();
    expect($order->payment_status->value)->toBe('cancelled')->and($order->payment_id)->toBe('SD-NOSTOCK')
        ->and($order->payment_note)->toBe('paid_after_expiry_no_stock')
        ->and(sold($t['normal']))->toBe(1)
        ->and(Refund::query()->where('order_id', $order->id)->exists())->toBeTrue();
});

/* ---------------------------------------------------------- decision rule */

$payload = fn (string $status = 'success', ?float $amount = 150.0) => NormalizedPayment::from(array_filter(['ORDERID' => 'QTR-1-1-2026-ABCDEFABCDEF', 'transaction_status' => ['success' => '3', 'failed' => '2', 'unknown' => '9'][$status], 'TXNAMOUNT' => $amount, 'transaction_number' => 'SD1'], fn ($v) => $v !== null));
$ref = 'QTR-1-1-2026-ABCDEFABCDEF';

it('T-062 confirms only on API success with a matching amount, reference and transaction', function () use ($ref) {
    $ok = fn ($amount) => PaymentDecision::decide($ref, '150.00', 'pending', null, false, ApiResult::success('SD1', $amount, $ref, true))->action;

    expect($ok(150.004))->toBe('confirm')->and($ok(149.99))->toBe('review')
        ->and(PaymentDecision::decide($ref, '150.00', 'pending', null, false, ApiResult::success('SD1', 150, 'QTR-1-1-2026-000000000000', true))->action)->toBe('pending')
        ->and(PaymentDecision::decide($ref, '150.00', 'pending', null, false, ApiResult::success('', 150, $ref, true))->action)->toBe('review');
});

it('T-063 never trusts a signed callback on its own', function () use ($ref, $payload) {
    $d = fn ($p, bool $signed, ?ApiResult $api) => PaymentDecision::decide($ref, '150.00', 'pending', $p, $signed, $api)->action;

    expect($d($payload(), true, ApiResult::success('SD1', 99, $ref, true)))->toBe('review')        // amount mismatch
        ->and($d($payload(), true, ApiResult::unavailable('down')))->toBe('review')                // API unavailable
        ->and($d($payload(), true, ApiResult::notFound()))->toBe('pending')
        ->and($d($payload(), true, ApiResult::inProgress()))->toBe('pending')
        ->and($d($payload(), true, null))->toBe('review')                                          // API not consulted
        ->and($d($payload(), false, ApiResult::unavailable('down')))->toBe('review')
        ->and($d($payload(), false, ApiResult::notFound()))->toBe('pending');
});

it('T-064 fails only when Sadad itself reports failure', function () use ($ref, $payload) {
    expect(PaymentDecision::decide($ref, '150.00', 'pending', null, false, ApiResult::failed('SD1'))->action)->toBe('fail')
        ->and(PaymentDecision::decide($ref, '150.00', 'pending', $payload('failed'), true, ApiResult::notFound())->action)->toBe('pending')
        ->and(PaymentDecision::decide($ref, '150.00', 'pending', $payload('failed'), false, null)->action)->toBe('pending');
});

it('T-065 is a no-op when confirmed and sends a paid staff-cancelled order to review', function () use ($ref) {
    expect(PaymentDecision::decide($ref, '150.00', 'confirmed', null, false, null)->action)->toBe('noop')
        ->and(PaymentDecision::decide($ref, '150.00', 'failed', null, false, ApiResult::success('SD1', 150, $ref, true))->action)->toBe('confirm')
        ->and(PaymentDecision::decide($ref, '150.00', 'cancelled', null, false, ApiResult::success('SD1', 150, $ref, true))->action)->toBe('review');
});

it('T-065 does not call Sadad for an already confirmed order', function () {
    $t = openEvent();
    $order = sadadOrder($t, 1);
    app(ConfirmPaymentAction::class)->execute($order->booking_reference, 'SD-9');
    $this->mock(SadadApiClient::class, fn ($m) => $m->shouldNotReceive('lookupTransaction'));

    expect(app(PaymentProcessor::class)->process($order->booking_reference, null, null, false, 'poll')['outcome'])->toBe('noop');
});

it('T-066 writes exactly one bounded payment event per processing', function () {
    $t = openEvent();
    $order = sadadOrder($t, 1);
    fakeSadadApi(ApiResult::notFound());
    $huge = ['blob' => str_repeat('x', 20000)];

    app(PaymentProcessor::class)->process($order->booking_reference, null, $huge, false, 'webhook');
    $events = PaymentEvent::query()->where('booking_reference', $order->booking_reference)->get();

    expect($events)->toHaveCount(1)
        ->and(strlen(json_encode($events->first()->payload)))->toBeLessThan(8100);

    $this->mock(SadadApiClient::class, fn ($m) => $m->shouldNotReceive('lookupTransaction'));
    expect(app(PaymentProcessor::class)->process('QTR-1-1-2026-FFFFFFFFFFFF', null, null, false, 'poll')['outcome'])->toBe('order_not_found');
});

it('T-062 confirms end-to-end through the processor when the API agrees', function () {
    $t = openEvent();
    $order = sadadOrder($t, 1);
    fakeSadadApi(ApiResult::success('SD-E2E', (float) $order->total_amount, $order->booking_reference, true));

    expect(app(PaymentProcessor::class)->process($order->booking_reference, null, null, false, 'callback')['outcome'])->toBe('confirmed')
        ->and($order->fresh()->payment_id)->toBe('SD-E2E');
});
