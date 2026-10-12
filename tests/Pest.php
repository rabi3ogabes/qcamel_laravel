<?php

use App\Domain\Booking\Actions\CreateOrderAction;
use App\Domain\Booking\Data\OrderRequestValidator;
use App\Domain\Shared\Actor;
use App\Enums\Role;
use App\Models\Event;
use App\Models\Order;
use App\Models\PosUser;
use App\Models\TicketType;
use App\Models\User;
use App\Settings\SadadSettings;
use App\Support\QatarClock;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test bootstrap — every Feature test runs on a fresh in-memory SQLite DB
| (phpunit.xml forces it), with the roles/permissions matrix seeded and all
| outbound HTTP faked unless a test says otherwise.
|--------------------------------------------------------------------------
*/
pest()->extend(TestCase::class)->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        Http::preventStrayRequests();
        app()->setLocale('ar');
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/* ---------------------------------------------------------------- accounts */

function staffUser(Role|string|null $role = Role::Admin, array $attributes = []): User
{
    $user = User::factory()->create(array_merge(['is_active' => true], $attributes));
    if ($role) {
        $user->assignRole($role instanceof Role ? $role->value : $role);
    }

    return $user;
}

function actingAsRole(Role|string|null $role = Role::Admin): User
{
    $user = staffUser($role);
    test()->actingAs($user);

    return $user;
}

/* ----------------------------------------------------------------- catalogue */

/**
 * An open event (sales until the cut-off of its last day) with three ticket types.
 *
 * @return array{event: Event, normal: TicketType, vip: TicketType, parking: TicketType}
 */
function openEvent(int $normal = 200, int $vip = 50, int $parking = 100, array $prices = ['normal' => '100.00', 'vip' => '200.00', 'parking' => '20.00'], ?string $date = null): array
{
    $date ??= QatarClock::local(QatarClock::today())->addDays(3)->toDateString();
    $event = Event::factory()->on($date)->withTickets($normal, $vip, $parking, $prices)->create(['is_active' => true, 'is_archived' => false]);
    $types = TicketType::query()->where('event_id', $event->id)->get()->keyBy(fn ($t) => $t->type->value);

    return ['event' => $event, 'normal' => $types['normal'], 'vip' => $types['vip'], 'parking' => $types['parking']];
}

function configureSadad(array $overrides = []): void
{
    $s = app(SadadSettings::class);
    foreach (array_merge(['merchant_id' => '1234567', 'website_domain' => 'qcamel.test', 'environment' => 'sandbox', 'checkout_secret' => 'S3CR3T', 'site_url' => null], $overrides) as $k => $v) {
        $s->{$k} = $v;
    }
    $s->save();
}

/* --------------------------------------------------------------------- orders */

/**
 * Order body in the public API shape. $lines = [[TicketType, qty], ...]
 */
function orderBody(array $lines, string $method = 'cash_pos', array $extra = []): array
{
    $items = [];
    $holders = [];
    $n = 0;
    foreach ($lines as [$type, $qty]) {
        $items[] = ['ticket_id' => $type->id, 'quantity' => $qty];
        for ($i = 0; $i < $qty; $i++) {
            $n++;
            $holders[] = ['ticket_id' => $type->id, 'name' => "حامل التذكرة {$n}", 'phone' => '5551'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'nationality' => 'QA', 'id_number' => uniqueIdNumber()];
        }
    }

    return array_replace_recursive([
        'customer' => ['name' => 'محمد أحمد', 'phone' => '55512345', 'email' => 'buyer@example.com', 'nationality' => 'QA', 'id_number' => '28463400001', 'country_code' => '+974'],
        'items' => $items,
        'holders' => $holders,
        'payment_method' => $method,
        'source' => 'web',
        'terms_accepted' => true,
    ], $extra);
}

/** Create an order through the real validator + action. */
function placeOrder(array $lines, string $method = 'cash_pos', array $extra = [], ?Actor $actor = null): Order
{
    $data = app(OrderRequestValidator::class)->validate(orderBody($lines, $method, $extra));

    return app(CreateOrderAction::class)->execute($data, $actor ?? Actor::current());
}

function posOrder(array $lines, ?User $cashierAccount = null, ?PosUser $cashier = null): Order
{
    $account = $cashierAccount ?? staffUser(Role::Admin);
    test()->actingAs($account);

    return placeOrder($lines, 'cash_pos', array_filter(['source' => 'pos', 'pos_user_id' => $cashier?->id]), Actor::user($account));
}

function uniqueIdNumber(): string
{
    static $n = 0;

    return '2'.str_pad((string) (random_int(100000000, 899999999) + (++$n)), 10, '0', STR_PAD_LEFT);
}

function sold(TicketType $type): int
{
    return (int) TicketType::query()->whereKey($type->id)->value('sold_quantity');
}
