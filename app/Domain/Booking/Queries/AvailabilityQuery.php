<?php

namespace App\Domain\Booking\Queries;

use App\Enums\TicketKind;
use App\Models\Event;
use App\Models\TicketType;
use App\Support\QatarClock;
use Illuminate\Support\Facades\DB;

/**
 * F-ORD-032/033, R-053/R-054, T-061: public, read-only availability.
 * remaining = capacity − held, where held = confirmed + unexpired pending
 * (an expired-but-unclosed hold stops counting immediately).
 */
class AvailabilityQuery
{
    /** @return array<string, int> type => held */
    public function heldCounts(string $eventId): array
    {
        $now = QatarClock::nowUtc();

        return DB::table('ticket_holders as h')
            ->join('orders as o', 'o.id', '=', 'h.order_id')
            ->whereNull('o.deleted_at')
            ->where('o.event_id', $eventId)
            ->where(fn ($w) => $w->where('o.payment_status', 'confirmed')
                ->orWhere(fn ($p) => $p->where('o.payment_status', 'pending')
                    ->where(fn ($e) => $e->whereNull('o.payment_expires_at')->orWhere('o.payment_expires_at', '>', $now))))
            ->selectRaw('h.ticket_type, COUNT(*) as n')
            ->groupBy('h.ticket_type')
            ->pluck('n', 'ticket_type')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Public ticket cards in R-029 order (normal → vip → parking).
     *
     * @return list<array{id:string, type:string, price:string, capacity:int, remaining:int, sold_out:bool, description:string}>
     */
    public function forEvent(Event $event): array
    {
        // F-PUB-024: callers release this event's expired holds first, so the
        // authoritative counter equals live held seats (one formula, D-049).
        $types = $event->ticketTypes()->get()->sortBy(fn (TicketType $t) => $t->type->sortWeight())->values();

        return $types->map(function (TicketType $t) {
            $remaining = max(0, (int) $t->available_quantity - (int) $t->sold_quantity);

            return [
                'id' => $t->id, 'type' => $t->type->value, 'price' => (string) $t->price,
                'capacity' => (int) $t->available_quantity, 'remaining' => $remaining,
                'sold_out' => $remaining <= 0, 'description' => $t->description(),
            ];
        })->all();
    }

    /**
     * R-054 check_availability (was the misleading reserve_tickets, D-078).
     *
     * @return array{success:bool, error?:string, message?:string, available?:int, requested?:int, after_reservation?:int}
     */
    public function check(string $eventId, string $type, int $quantity): array
    {
        $kind = TicketKind::tryFrom($type);
        $row = $kind ? TicketType::query()->where('event_id', $eventId)->where('type', $kind->value)->first() : null;
        if (! $row) {
            return ['success' => false, 'error' => 'TICKET_NOT_FOUND', 'message' => __('orders.ticket_not_found')];
        }
        $held = $this->heldCounts($eventId)[$kind->value] ?? 0;
        $available = (int) $row->available_quantity - $held;
        if ($available < $quantity) {
            return [
                'success' => false, 'error' => 'INSUFFICIENT_CAPACITY',
                'message' => $available <= 0 ? __('orders.sold_out_now') : __('orders.not_enough', ['n' => $available]),
                'available' => max(0, $available), 'requested' => $quantity,
            ];
        }

        return ['success' => true, 'available' => $available, 'after_reservation' => $available - $quantity];
    }
}
