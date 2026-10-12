<?php

use App\Domain\Booking\Actions\CreateOrderAction;
use App\Domain\Booking\Data\OrderRequestValidator;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PosUser;
use App\Models\TicketHolder;
use Illuminate\Database\QueryException;

/*
| Order creation rules — T-021…T-036, T-039…T-041, T-045 (F-ORD-*).
*/

it('prices the order from the database, never from the client (T-021, T-036)', function () {
    $t = openEvent();
    $order = placeOrder([[$t['vip'], 1], [$t['normal'], 2]], 'cash_pos', ['total_amount' => '1.00', 'payment_status' => 'confirmed', 'price' => 1]);

    expect((string) $order->total_amount)->toBe('400.00')
        ->and($order->payment_status->value)->toBe('pending');
});

it('starts a web Sadad order pending with a ~30 minute hold that reserves stock (T-022)', function () {
    configureSadad();
    $t = openEvent();
    $order = placeOrder([[$t['vip'], 1], [$t['normal'], 2]], 'sadad');

    expect($order->payment_status->value)->toBe('pending')
        ->and($order->payment_expires_at->between(now()->addMinutes(29), now()->addMinutes(31)))->toBeTrue()
        ->and(sold($t['vip']))->toBe(1)
        ->and(sold($t['normal']))->toBe(2)
        ->and(sold($t['parking']))->toBe(0);
});

it('builds the reference from the Qatar event date, unpadded (T-023)', function () {
    $t = openEvent(date: '2026-11-05');
    $order = placeOrder([[$t['normal'], 1]]);

    expect($order->booking_reference)->toMatch('/^QTR-[0-9]{1,2}-[0-9]{1,2}-[0-9]{4}-[0-9A-F]{12}$/')
        ->and($order->booking_reference)->toStartWith('QTR-5-11-2026-');
});

it('creates one holder per ticket with sequential codes and the most expensive type (T-024)', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 2], [$t['vip'], 1]]);
    $codes = $order->holders()->orderBy('position')->pluck('qr_code')->all();

    expect($codes)->toBe([$order->booking_reference.'-TKT01', $order->booking_reference.'-TKT02', $order->booking_reference.'-TKT03'])
        ->and($order->ticket_type->value)->toBe('vip')
        ->and($order->customer->id_number)->toBe('28463400001');
});

it('keeps a web cash order pending without an expiry (T-025)', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'cash_pos');

    expect($order->payment_status->value)->toBe('pending')
        ->and($order->payment_expires_at)->toBeNull();
});

it('confirms a POS sale immediately with the cashier attribution (T-026)', function () {
    $t = openEvent();
    $cashier = PosUser::query()->create(['name' => 'كاشير 1', 'icon' => '⭐', 'is_active' => true]);
    $admin = staffUser(Role::Admin);
    $order = posOrder([[$t['normal'], 2]], $admin, $cashier);

    expect($order->payment_status->value)->toBe('confirmed')
        ->and($order->booking_reference)->toStartWith('POS-')
        ->and($order->confirmed_by)->toBe($admin->id)
        ->and($order->pos_user_id)->toBe($cashier->id)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->confirmed_at)->not->toBeNull()
        ->and($order->n8n_response_message)->toBe('طلب من نقطة البيع - POS');
});

it('keeps an explicit holder country code and derives one from an international phone (T-027)', function () {
    $t = openEvent();
    $body = orderBody([[$t['normal'], 2]]);
    $body['holders'][0]['country_code'] = '+966';
    $body['holders'][1]['phone'] = '+971 55510002';
    $data = app(OrderRequestValidator::class)->validate($body);
    $order = app(CreateOrderAction::class)->execute($data, Actor::guest());
    $holders = $order->holders()->orderBy('position')->get();

    expect($holders[0]->country_code)->toBe('+966')
        ->and($holders[1]->country_code)->toBe('+971');
});

it('rejects impossible orders with the right codes (T-028)', function (callable $make, string $code) {
    $t = openEvent(normal: 3);
    try {
        $make($t);
        $this->fail('expected a DomainError');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe($code);
    }
})->with([
    'more than the stock at the POS' => [fn ($t) => posOrder([[$t['normal'], 4]]), 'insufficient_stock'],
    'above the POS admission cap (R-015)' => [fn ($t) => posOrder([[$t['vip'], 6]]), 'quantity_limit_exceeded'],
    'web admission above the cap' => [fn ($t) => placeOrder([[$t['vip'], 3], [$t['normal'], 3]]), 'quantity_limit_exceeded'],
    'web parking above the cap' => [fn ($t) => placeOrder([[$t['parking'], 6]]), 'quantity_limit_exceeded'],
    'tickets from two events' => [function ($t) {
        $other = openEvent();
        placeOrder([[$t['normal'], 1], [$other['normal'], 1]]);
    }, 'invalid_tickets'],
]);

it('reports a stock shortage as 409 with the ticket type (T-028)', function () {
    $t = openEvent(normal: 3);
    try {
        posOrder([[$t['normal'], 4]]);
    } catch (DomainError $e) {
        expect($e->status)->toBe(409)->and($e->toArray()['ticket_type'] ?? null)->toBe('normal');

        return;
    }
    $this->fail('expected insufficient_stock');
});

it('leaves nothing behind when an order is rejected (T-029)', function () {
    $t = openEvent(normal: 3);
    try {
        posOrder([[$t['normal'], 4]]);
    } catch (DomainError) {
    }

    expect(Customer::count())->toBe(0)->and(Order::count())->toBe(0)
        ->and(TicketHolder::count())->toBe(0)->and(sold($t['normal']))->toBe(0);
});

it('enforces the per-person cap by national ID (T-030)', function () {
    $t = openEvent();
    $withId = function (int $qty, string $id = '30011112222') use ($t) {
        $body = orderBody([[$t['normal'], $qty]]);
        foreach ($body['holders'] as &$h) {
            $h['id_number'] = $id;
        }
        $body['customer']['id_number'] = $id;

        return app(CreateOrderAction::class)->execute(app(OrderRequestValidator::class)->validate($body), Actor::guest());
    };

    $withId(3);
    $before = sold($t['normal']);
    try {
        $withId(3);
        $this->fail('expected ticket_limit_exceeded');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('ticket_limit_exceeded')->and($e->status)->toBe(422)
            ->and($e->userMessage())->toMatch('/\p{Arabic}/u');
    }
    expect(sold($t['normal']))->toBe($before);
    $withId(2);
    $withId(3, '30099998888'); // another person is unaffected

    expect(sold($t['normal']))->toBe(8);
});

it('refuses an online payment under the minimum amount but accepts cash (T-031)', function () {
    configureSadad();
    $t = openEvent(prices: ['normal' => '2.00', 'vip' => '200.00', 'parking' => '2.00']);

    expect(fn () => placeOrder([[$t['normal'], 1]], 'sadad'))->toThrow(DomainError::class);
    try {
        placeOrder([[$t['normal'], 1]], 'sadad');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('below_minimum_amount');
    }
    expect((string) placeOrder([[$t['normal'], 1]], 'cash_pos')->total_amount)->toBe('2.00');
});

it('closes an expired hold only on the next sale of the same event (T-032)', function () {
    configureSadad();
    $a = openEvent();
    $b = openEvent();
    $expired = placeOrder([[$a['normal'], 2]], 'sadad');
    $expired->forceFill(['payment_expires_at' => now()->subMinute()])->save();

    placeOrder([[$b['normal'], 1]]);
    expect($expired->fresh()->payment_status->value)->toBe('pending')->and(sold($a['normal']))->toBe(2);

    placeOrder([[$a['normal'], 1]]);
    expect($expired->fresh()->payment_status->value)->toBe('cancelled')
        ->and($expired->fresh()->payment_note)->toBe('expired')
        ->and(sold($a['normal']))->toBe(1);
});

it('normalises input and applies customer defaults (T-033)', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'cash_pos', ['customer' => ['name' => '  سارة   علي  ', 'email' => null, 'country_code' => null]]);

    expect($order->customer->name)->toBe('سارة علي')
        ->and($order->customer->country_code)->toBe('+974')
        ->and($order->customer->getRawOriginal('email'))->toBe('')   // stored '' (T-033)
        ->and($order->customer->email)->toBeNull();                   // presented as null (D-094)
});

it('stops before reserving anything when Sadad is not configured (T-039)', function () {
    $t = openEvent();
    try {
        placeOrder([[$t['normal'], 1]], 'sadad');
        $this->fail('expected payment_unavailable');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('payment_unavailable')->and($e->status)->toBe(503);
    }
    expect(sold($t['normal']))->toBe(0)->and(Order::count())->toBe(0);
    expect(placeOrder([[$t['normal'], 1]], 'cash_pos')->exists)->toBeTrue();
});

it('limits open unpaid orders per phone, whatever its formatting (T-040)', function () {
    $t = openEvent();
    foreach (['55512345', '+974 5551 2345', '0097455512345', '5551 2345'] as $phone) {
        placeOrder([[$t['normal'], 1]], 'cash_pos', ['customer' => ['phone' => $phone]]);
    }
    try {
        placeOrder([[$t['normal'], 1]], 'cash_pos', ['customer' => ['phone' => '55512345']]);
        $this->fail('expected too_many_pending_orders');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('too_many_pending_orders')->and($e->status)->toBe(429);
    }
    expect(posOrder([[$t['normal'], 1]])->payment_status->value)->toBe('confirmed'); // POS exempt
});

it('stores the return origin only when it is safe (T-041)', function (?string $origin, ?string $expected) {
    $t = openEvent();
    $data = app(OrderRequestValidator::class)->validate(orderBody([[$t['normal'], 1]]));
    $order = app(CreateOrderAction::class)->execute($data, Actor::guest(), ['return_origin' => $origin]);

    expect($order->return_origin)->toBe($expected);
})->with([
    'https origin' => ['https://qcamelmc.org', 'https://qcamelmc.org'],
    'javascript scheme' => ['javascript:alert(1)', null],
]);

it('requires a staff identity and cash for POS orders, and never a cashier on web orders (T-045)', function () {
    $t = openEvent();
    $cashier = PosUser::query()->create(['name' => 'كاشير', 'is_active' => true]);

    expect(fn () => placeOrder([[$t['normal'], 1]], 'cash_pos', ['source' => 'pos'], Actor::guest()))->toThrow(DomainError::class);
    expect(fn () => placeOrder([[$t['normal'], 1]], 'cash_pos', ['pos_user_id' => $cashier->id]))->toThrow(DomainError::class);

    $supervisor = staffUser(Role::Supervisor);
    $this->actingAs($supervisor);
    expect(placeOrder([[$t['normal'], 1]], 'cash_pos', ['source' => 'pos'], Actor::user($supervisor))->payment_status->value)->toBe('confirmed');

    configureSadad();
    expect(fn () => placeOrder([[$t['normal'], 1]], 'sadad', ['source' => 'pos'], Actor::user($supervisor)))->toThrow(DomainError::class);
});

it('requires the terms to be accepted on web orders (F-PUB-050 rebuild)', function () {
    $t = openEvent();
    try {
        placeOrder([[$t['normal'], 1]], 'cash_pos', ['terms_accepted' => false]);
        $this->fail('expected terms_required');
    } catch (DomainError $e) {
        expect($e->errorCode)->toBe('terms_required');
    }
});

it('has a unique index on orders.payment_id (T-006)', function () {
    $t = openEvent();
    $a = posOrder([[$t['normal'], 1]]);
    $b = posOrder([[$t['normal'], 1]]);
    $a->forceFill(['payment_id' => 'TXN-1'])->save();

    expect(fn () => $b->forceFill(['payment_id' => 'TXN-1'])->save())->toThrow(QueryException::class);
});
