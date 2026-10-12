<?php

namespace App\Domain\Booking\Queries;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * R-071 person event history (staff only, D-020) and R-070 lifetime totals
 * with ONE key per holder (ID preferred, else phone — D-095).
 */
class PersonQueries
{
    /**
     * @return list<array{event_id:string, title:string, event_date:?string, ticket_count:int}>
     */
    public function history(?string $idNumber, ?string $phone, int $limit = 50): array
    {
        $id = PhoneNumber::digits($idNumber);
        $ph = PhoneNumber::digits($phone);
        if (strlen($id) < 6 && strlen($ph) < 8) {
            return [];
        }

        return DB::table('ticket_holders as h')
            ->join('orders as o', 'o.id', '=', 'h.order_id')
            ->join('events as e', 'e.id', '=', 'o.event_id')
            ->whereNull('o.deleted_at')
            ->where('o.payment_status', 'confirmed')
            ->where(function ($w) use ($id, $ph) {
                if (strlen($id) >= 6) {
                    $w->orWhere('h.person_key', 'id:'.$id);
                }
                if (strlen($ph) >= 8) {
                    $w->orWhere('h.phone_e164', 'like', '%'.substr($ph, -8));
                }
            })
            ->selectRaw('e.id as event_id, e.title, e.event_date, COUNT(*) as ticket_count')
            ->groupBy('e.id', 'e.title', 'e.event_date')
            ->orderByDesc('e.event_date')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['event_id' => $r->event_id, 'title' => $r->title, 'event_date' => $r->event_date, 'ticket_count' => (int) $r->ticket_count])
            ->all();
    }

    /**
     * Lifetime confirmed tickets per person key (all events incl. archive).
     *
     * @param  list<string>  $keys
     * @return array<string,int>
     */
    public function lifetimeTotals(array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys)));
        if ($keys === []) {
            return [];
        }

        return DB::table('ticket_holders as h')
            ->join('orders as o', 'o.id', '=', 'h.order_id')
            ->whereNull('o.deleted_at')
            ->where('o.payment_status', 'confirmed')
            ->whereIn('h.person_key', $keys)
            ->selectRaw('h.person_key, COUNT(*) as n')
            ->groupBy('h.person_key')
            ->pluck('n', 'person_key')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
