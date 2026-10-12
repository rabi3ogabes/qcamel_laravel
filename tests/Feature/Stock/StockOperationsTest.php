<?php

use App\Domain\Booking\Actions\ChangeOrderEventAction;
use App\Domain\Booking\Actions\OrderStatusActions;
use App\Domain\Booking\Actions\RecycleBinActions;
use App\Domain\Events\EventAdminActions;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\ExpiredQrCode;
use App\Models\Order;
use App\Models\TicketType;

/*
| Stock ledger operations — T-053…T-056, T-058, T-126.
*/

function adminActor(): Actor
{
    $u = staffUser(Role::SuperAdmin);
    test()->actingAs($u);

    return Actor::user($u);
}

it('T-053 releases seats on cancel, re-reserves on reinstate, refuses reinstate when sold out', function () {
    $t = openEvent(normal: 2);
    $actor = adminActor();
    $order = placeOrder([[$t['normal'], 2]]);
    $actions = app(OrderStatusActions::class);

    $actions->setStatus($order, PaymentStatus::Cancelled, $actor);
    expect(sold($t['normal']))->toBe(0);
    $actions->setStatus($order->fresh(), PaymentStatus::Pending, $actor);
    expect(sold($t['normal']))->toBe(2);

    $actions->setStatus($order->fresh(), PaymentStatus::Cancelled, $actor);
    posOrder([[$t['normal'], 2]]);
    try {
        $actions->setStatus($order->fresh(), PaymentStatus::Pending, $actor);
        $this->fail('expected insufficient_stock');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('insufficient_stock')->and($e->status)->toBe(409);
    }
});

it('T-054 / T-126 releases seats once when tickets and orders are deleted', function () {
    $t = openEvent();
    $actor = adminActor();
    $order = placeOrder([[$t['normal'], 3]]);

    app(RecycleBinActions::class)->bin($order->holders()->first(), $actor);
    expect(sold($t['normal']))->toBe(2);

    app(OrderStatusActions::class)->delete($order->fresh(), $actor);
    expect(sold($t['normal']))->toBe(0)
        ->and(Order::withTrashed()->find($order->id))->not->toBeNull(); // soft-deleted, auditable

    $cancelled = placeOrder([[$t['normal'], 1]]);
    app(OrderStatusActions::class)->cancel($cancelled, 'x', $actor);
    $before = sold($t['normal']);
    app(OrderStatusActions::class)->delete($cancelled->fresh(), $actor);
    expect(sold($t['normal']))->toBe($before);
});

it('T-055 bins and restores a ticket with the same holder id and keeps its picture', function () {
    $t = openEvent(normal: 1);
    $actor = adminActor();
    $order = placeOrder([[$t['normal'], 1]]);
    $holder = $order->holders()->first();
    $holder->forceFill(['qr_image_url' => 'https://qcamel.test/storage/qr-codes/x.png'])->save();

    $binned = app(RecycleBinActions::class)->bin($holder, $actor);
    expect(sold($t['normal']))->toBe(0)->and($binned->qr_image_url ?? data_get($binned->snapshot, 'qr_image_url'))->not->toBeNull();

    $restored = app(RecycleBinActions::class)->restore($binned, $actor);
    expect($restored->id)->toBe($holder->id)->and(sold($t['normal']))->toBe(1);

    $again = app(RecycleBinActions::class)->bin($restored, $actor);
    posOrder([[$t['normal'], 1]]);
    expect(fn () => app(RecycleBinActions::class)->restore($again, $actor))->toThrow(DomainError::class);
});

it('T-056 moves an order between events, expiring old codes, and refuses a full event', function () {
    $a = openEvent();
    $b = openEvent(normal: 2);
    $actor = adminActor();
    $order = placeOrder([[$a['normal'], 2]]);
    $oldCodes = $order->holders()->pluck('qr_code')->all();

    app(ChangeOrderEventAction::class)->execute($order, $b['event']->id, $actor);
    expect(sold($a['normal']))->toBe(0)->and(sold($b['normal']))->toBe(2)
        ->and(ExpiredQrCode::query()->whereIn('qr_code', $oldCodes)->count())->toBe(2);

    app(ChangeOrderEventAction::class)->execute($order->fresh(), $a['event']->id, $actor);
    expect(sold($a['normal']))->toBe(2)->and(sold($b['normal']))->toBe(0);

    posOrder([[$b['normal'], 2]]);
    expect(fn () => app(ChangeOrderEventAction::class)->execute($order->fresh(), $b['event']->id, $actor))->toThrow(DomainError::class);
    expect(sold($a['normal']))->toBe(2)->and($order->fresh()->event_id)->toBe($a['event']->id);
});

it('T-058 recounts sold quantities from pending and confirmed holders', function () {
    $t = openEvent();
    $actor = adminActor();
    placeOrder([[$t['normal'], 2]]);
    posOrder([[$t['normal'], 1]]);
    $cancelled = placeOrder([[$t['vip'], 1]]);
    app(OrderStatusActions::class)->cancel($cancelled, 'x', $actor);
    TicketType::query()->whereKey($t['parking']->id)->update(['sold_quantity' => 5]); // drift

    app(RecycleBinActions::class)->recount($actor);

    expect(sold($t['normal']))->toBe(3)->and(sold($t['vip']))->toBe(0)->and(sold($t['parking']))->toBe(0);
});

it('T-126 refuses to delete an event that has bookings', function () {
    $t = openEvent();
    $actor = adminActor();
    placeOrder([[$t['normal'], 1]]);

    try {
        app(EventAdminActions::class)->delete($t['event'], $actor);
        $this->fail('expected event_has_orders');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('event_has_orders');
    }
    expect($t['event']->fresh())->not->toBeNull();
});
