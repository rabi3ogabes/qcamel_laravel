<?php

namespace App\Domain\Booking\Services;

use App\Domain\Shared\DomainError;
use App\Enums\PaymentStatus;
use App\Enums\TicketKind;
use App\Events\TicketTypeStockChanged;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stock invariant (R-045…R-058): ticket_types.sold_quantity = seats held by
 * pending + confirmed orders. Every change goes through this class inside
 * the caller's DB transaction, after locking the event's ticket-type rows
 * ORDER BY id FOR UPDATE (R-047).
 */
class StockLedger
{
    /**
     * Lock all ticket types of one event (fixed order, avoids deadlocks).
     *
     * @return Collection<string, TicketType> keyed by type value
     */
    public function lockEvent(string $eventId): Collection
    {
        return TicketType::query()
            ->where('event_id', $eventId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (TicketType $t) => $t->type->value);
    }

    /** Lock two events' rows in (event_id, id) order (event move, R-047). */
    public function lockEvents(array $eventIds): Collection
    {
        $ids = array_values(array_unique($eventIds));
        sort($ids);

        return TicketType::query()
            ->whereIn('event_id', $ids)
            ->orderBy('event_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Seats held by an order per type (R-049 legacy fallback).
     *
     * @return array<string, int>
     */
    public function seatsOf(Order $order): array
    {
        $counts = DB::table('ticket_holders')
            ->where('order_id', $order->id)
            ->selectRaw('ticket_type, COUNT(*) as n')
            ->groupBy('ticket_type')
            ->pluck('n', 'ticket_type')
            ->map(fn ($n) => (int) $n)
            ->all();

        if ($counts === [] && $order->quantity > 0) {
            $counts = [$order->ticket_type->value => (int) $order->quantity];
        }

        return $counts;
    }

    /**
     * Reserve seats for every type or fail the whole change (R-046).
     *
     * @param  array<string, int>  $seats
     */
    public function reserve(string $eventId, array $seats, ?Collection $locked = null, bool $withType = false): void
    {
        $locked ??= $this->lockEvent($eventId);
        ksort($seats);
        foreach ($seats as $type => $n) {
            if ($n <= 0) {
                continue;
            }
            $row = $locked->get($type) ?? $locked->first(fn ($t) => $t->event_id === $eventId && $t->type->value === $type);
            if (! $row || $row->available_quantity - $row->sold_quantity < $n) {
                throw DomainError::insufficientStock($withType ? $type : null);
            }
        }
        foreach ($seats as $type => $n) {
            if ($n <= 0) {
                continue;
            }
            $row = $locked->get($type) ?? $locked->first(fn ($t) => $t->event_id === $eventId && $t->type->value === $type);
            DB::table('ticket_types')->where('id', $row->id)->increment('sold_quantity', $n, ['updated_at' => now()]);
            $row->sold_quantity += $n;
            $this->broadcast($row);
        }
    }

    /**
     * Release seats (never below 0, R-048).
     *
     * @param  array<string, int>  $seats
     */
    public function release(string $eventId, array $seats, ?Collection $locked = null): void
    {
        $locked ??= $this->lockEvent($eventId);
        foreach ($seats as $type => $n) {
            if ($n <= 0) {
                continue;
            }
            $row = $locked->get($type) ?? $locked->first(fn ($t) => $t->event_id === $eventId && $t->type->value === $type);
            if (! $row) {
                continue; // unknown type ignored (R-046)
            }
            $new = max(0, $row->sold_quantity - $n);
            DB::table('ticket_types')->where('id', $row->id)->update(['sold_quantity' => $new, 'updated_at' => now()]);
            $row->sold_quantity = $new;
            $this->broadcast($row);
        }
    }

    public function releaseOrder(Order $order, ?Collection $locked = null): void
    {
        $this->release($order->event_id, $this->seatsOf($order), $locked);
    }

    public function reserveOrder(Order $order, ?Collection $locked = null): void
    {
        $this->reserve($order->event_id, $this->seatsOf($order), $locked);
    }

    /** Move an active order's seats between events, or refuse (R-046). */
    public function move(Order $order, string $fromEventId, string $toEventId): void
    {
        $rows = $this->lockEvents([$fromEventId, $toEventId]);
        $seats = $this->seatsOf($order);
        $to = $rows->where('event_id', $toEventId)->keyBy(fn ($t) => $t->type->value);
        $from = $rows->where('event_id', $fromEventId)->keyBy(fn ($t) => $t->type->value);
        $this->reserve($toEventId, $seats, $to);
        $this->release($fromEventId, $seats, $from);
    }

    /**
     * R-050 recount: recompute every sold_quantity from live orders.
     *
     * @return array<int, array{id:string, event_id:string, type:string, old:int, new:int}> changed rows
     */
    public function recount(bool $write = true, ?string $eventId = null): array
    {
        $expected = $this->expectedCounts($eventId);
        $changed = [];
        $types = TicketType::query()->when($eventId, fn ($q) => $q->where('event_id', $eventId))->orderBy('event_id')->orderBy('id');
        if ($write) {
            $types->lockForUpdate();
        }
        foreach ($types->get() as $t) {
            $new = $expected[$t->event_id][$t->type->value] ?? 0;
            if ((int) $t->sold_quantity !== $new) {
                $changed[] = ['id' => $t->id, 'event_id' => $t->event_id, 'type' => $t->type->value, 'old' => (int) $t->sold_quantity, 'new' => $new];
                if ($write) {
                    DB::table('ticket_types')->where('id', $t->id)->update(['sold_quantity' => $new, 'updated_at' => now()]);
                }
            }
        }

        return $changed;
    }

    /** @return array<string, array<string, int>> event_id => type => held */
    public function expectedCounts(?string $eventId = null): array
    {
        $active = [PaymentStatus::Pending->value, PaymentStatus::Confirmed->value];
        $out = [];

        DB::table('ticket_holders as h')
            ->join('orders as o', 'o.id', '=', 'h.order_id')
            ->whereNull('o.deleted_at')
            ->whereIn('o.payment_status', $active)
            ->when($eventId, fn ($q) => $q->where('o.event_id', $eventId))
            ->selectRaw('o.event_id, h.ticket_type, COUNT(*) as n')
            ->groupBy('o.event_id', 'h.ticket_type')
            ->get()
            ->each(function ($r) use (&$out) {
                $out[$r->event_id][$r->ticket_type] = ($out[$r->event_id][$r->ticket_type] ?? 0) + (int) $r->n;
            });

        // R-049 legacy holder-less orders.
        DB::table('orders as o')
            ->whereNull('o.deleted_at')
            ->whereIn('o.payment_status', $active)
            ->when($eventId, fn ($q) => $q->where('o.event_id', $eventId))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('ticket_holders as h')->whereColumn('h.order_id', 'o.id'))
            ->select('o.event_id', 'o.ticket_type', 'o.quantity')
            ->get()
            ->each(function ($r) use (&$out) {
                $out[$r->event_id][$r->ticket_type] = ($out[$r->event_id][$r->ticket_type] ?? 0) + (int) $r->quantity;
            });

        return $out;
    }

    private function broadcast(TicketType $row): void
    {
        event(new TicketTypeStockChanged($row->event_id, $row->type->value, (int) $row->available_quantity - (int) $row->sold_quantity, (int) $row->sold_quantity, (int) $row->available_quantity));
    }

    /** Helper: seat counts from a list of kinds. */
    public static function countKinds(iterable $kinds): array
    {
        $c = [];
        foreach ($kinds as $k) {
            $v = $k instanceof TicketKind ? $k->value : (string) $k;
            $c[$v] = ($c[$v] ?? 0) + 1;
        }

        return $c;
    }
}
