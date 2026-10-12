<?php

namespace App\Livewire\Public\Concerns;

use App\Domain\Booking\Actions\ExpireHoldsAction;
use App\Domain\Booking\Queries\AvailabilityQuery;
use App\Enums\TicketKind;
use App\Models\Event;
use App\Support\Money;
use Livewire\Attributes\Locked;

/**
 * Shared quantity rules of ticket selection and checkout (F-PUB-030/037/038,
 * R-012…R-014): per type ≤ min(remaining, 5); VIP + normal ≤ 5 combined;
 * parking ≤ 5. The order transaction remains authoritative (F-ORD-001).
 */
trait ManagesCart
{
    /** @var list<array{id:string,type:string,price:string,capacity:int,remaining:int,sold_out:bool,description:string}> */
    #[Locked]
    public array $tickets = [];

    /** @var array<string,int> ticket_type id => quantity */
    public array $qty = [];

    #[Locked]
    public string $eventId = '';

    protected function loadAvailability(Event $event, bool $expire = true): void
    {
        if ($expire) {
            // F-PUB-024: release this event's expired holds before reading stock.
            rescue(fn () => app(ExpireHoldsAction::class)->forEvent($event->id), report: false);
        }
        $this->tickets = app(AvailabilityQuery::class)->forEvent($event);
        $known = array_column($this->tickets, 'id');
        $this->qty = array_intersect_key($this->qty, array_flip($known));
    }

    protected function ticket(string $id): ?array
    {
        foreach ($this->tickets as $t) {
            if ($t['id'] === $id) {
                return $t;
            }
        }

        return null;
    }

    protected function limits(): array
    {
        $event = Event::query()->find($this->eventId);

        return [
            'admission' => (int) ($event?->limit('admission') ?? config('qcamelmc.orders.max_admission_per_web_order')),
            'parking' => (int) ($event?->limit('parking') ?? config('qcamelmc.orders.max_parking_per_web_order')),
            'per_type' => (int) config('qcamelmc.orders.max_per_type_selection'),
        ];
    }

    public function admissionTotal(): int
    {
        $n = 0;
        foreach ($this->tickets as $t) {
            if ($t['type'] !== TicketKind::Parking->value) {
                $n += (int) ($this->qty[$t['id']] ?? 0);
            }
        }

        return $n;
    }

    /** @return array{0:bool,1:?string} can increase + reason message key */
    public function canIncrease(string $id): array
    {
        $t = $this->ticket($id);
        if (! $t || $t['sold_out']) {
            return [false, 'public.select.max_available'];
        }
        $limits = $this->limits();
        $current = (int) ($this->qty[$id] ?? 0);
        $typeMax = min($t['remaining'], $t['type'] === TicketKind::Parking->value ? $limits['parking'] : $limits['per_type']);
        if ($current >= $typeMax) {
            return [false, $current >= $t['remaining'] ? 'public.select.max_available' : ($t['type'] === TicketKind::Parking->value ? 'public.select.max_parking' : 'public.select.max_admission')];
        }
        if ($t['type'] !== TicketKind::Parking->value && $this->admissionTotal() >= $limits['admission']) {
            return [false, 'public.select.max_admission'];
        }

        return [true, null];
    }

    protected function increase(string $id): bool
    {
        [$ok, $reason] = $this->canIncrease($id);
        if (! $ok) {
            $this->toast('error', __($reason));

            return false;
        }
        $this->qty[$id] = (int) ($this->qty[$id] ?? 0) + 1;

        return true;
    }

    protected function decrease(string $id): bool
    {
        if (($this->qty[$id] ?? 0) <= 0) {
            return false;
        }
        $this->qty[$id]--;
        if ($this->qty[$id] <= 0) {
            unset($this->qty[$id]);
        }

        return true;
    }

    public function total(): Money
    {
        $sum = Money::zero();
        foreach ($this->tickets as $t) {
            if (($q = (int) ($this->qty[$t['id']] ?? 0)) > 0) {
                $sum = $sum->add(Money::of($t['price'])->multiply($q));
            }
        }

        return $sum;
    }

    public function count(): int
    {
        return array_sum($this->qty);
    }

    protected function toast(string $type, string $message, ?string $title = null, int $duration = 4500): void
    {
        $this->dispatch('toast', type: $type, message: $message, title: $title, duration: $duration);
    }
}
