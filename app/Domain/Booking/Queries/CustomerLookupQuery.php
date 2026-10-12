<?php

namespace App\Domain\Booking\Queries;

use App\Domain\Booking\Services\PersonLimitGuard;
use App\Domain\Shared\Actor;
use App\Models\Customer;
use App\Support\PhoneNumber;
use App\Support\Text;
use Illuminate\Support\Facades\DB;

/**
 * POS customer lookup (F-POS-017…019, F-ADM-CUS-017…019, R-097, D-101):
 * search by phone digits / name / ID, de-duplicated by person, previous-event
 * history chips in ONE query, remaining per-event allowance, national ID
 * masked for non-admin staff (R-203).
 */
class CustomerLookupQuery
{
    public function __construct(private readonly PersonLimitGuard $limits) {}

    /** @return list<array<string,mixed>> */
    public function search(string $term, Actor $actor, ?string $eventId = null, int $limit = 8): array
    {
        $term = Text::clean($term);
        if (mb_strlen($term) < 3) {
            return [];
        }
        $digits = PhoneNumber::digits($term);
        $like = Text::like($term);
        $rows = Customer::query()
            ->where(function ($q) use ($digits, $like) {
                if (strlen($digits) >= 3) {
                    $q->orWhere('phone', 'like', '%'.$digits.'%')->orWhere('phone_e164', 'like', '%'.$digits.'%');
                }
                $q->orWhere('name', 'like', $like)->orWhere('id_number', 'like', $like);
            })
            ->latest()
            ->limit(60)
            ->get();

        // De-duplicate by person (ID key preferred), most recent wins.
        $people = $rows->unique(fn ($c) => $c->person_key ?? ($c->phone_e164 ?? $c->id))->take($limit)->values();
        $keys = $people->pluck('person_key')->filter()->values()->all();
        $history = $this->histories($keys);
        $pii = $actor->canSeePii();
        $cap = (int) config('qcamelmc.orders.per_person_cap', 5);

        return $people->map(function (Customer $c) use ($history, $pii, $eventId, $cap) {
            $id = $c->getAttribute('id_number');
            $out = [
                'id' => $c->id, 'name' => $c->name, 'email' => $c->email, 'phone' => PhoneNumber::stripPrefix($c->phone),
                'country_code' => $c->country_code ?: '+974', 'nationality' => $c->nationality,
                'id_number' => $pii ? $id : null,
                'id_number_masked' => $id ? str_repeat('•', max(0, mb_strlen($id) - 4)).mb_substr($id, -4) : null,
                'history' => $history[$c->person_key] ?? [],
            ];
            if ($eventId && $c->person_key) {
                $existing = $this->limits->countByKey($c->person_key, $eventId);
                $out['remaining_allowance'] = max(0, $cap - $existing);
            }
            // Kiosk/non-admin staff never receive the raw ID; the POS still needs it to prefill → token.
            $out['prefill_token'] = $id ? encrypt(['c' => $c->id]) : null;

            return $out;
        })->all();
    }

    /** @param list<string> $keys @return array<string, list<array{event_id:string,title:string,event_date:?string,ticket_count:int}>> */
    private function histories(array $keys): array
    {
        if ($keys === []) {
            return [];
        }
        $out = [];
        DB::table('ticket_holders as h')
            ->join('orders as o', 'o.id', '=', 'h.order_id')
            ->join('events as e', 'e.id', '=', 'o.event_id')
            ->whereNull('o.deleted_at')->where('o.payment_status', 'confirmed')
            ->whereIn('h.person_key', $keys)
            ->selectRaw('h.person_key, e.id as event_id, e.title, e.event_date, COUNT(*) as n')
            ->groupBy('h.person_key', 'e.id', 'e.title', 'e.event_date')
            ->orderByDesc('e.event_date')
            ->get()
            ->each(function ($r) use (&$out) {
                if (count($out[$r->person_key] ?? []) < 50) {
                    $out[$r->person_key][] = ['event_id' => $r->event_id, 'title' => $r->title, 'event_date' => $r->event_date, 'ticket_count' => (int) $r->n];
                }
            });

        return $out;
    }

    /** Resolve the raw ID number server-side from a prefill token (never sent to non-admin clients). */
    public static function idFromToken(?string $token): ?string
    {
        if (! $token) {
            return null;
        }
        try {
            $data = decrypt($token);

            return Customer::query()->find($data['c'] ?? null)?->getAttribute('id_number');
        } catch (\Throwable) {
            return null;
        }
    }
}
