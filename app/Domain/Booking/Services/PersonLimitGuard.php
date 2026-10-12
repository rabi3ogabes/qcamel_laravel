<?php

namespace App\Domain\Booking\Services;

use App\Domain\Shared\DomainError;
use App\Enums\PaymentStatus;
use App\Support\PhoneNumber;
use App\Support\QatarClock;
use Illuminate\Support\Facades\DB;

/**
 * Per-person cap (R-020…R-025, F-ORD-020, D-031): ≤ N vip+normal tickets per
 * person per event across all channels, counting confirmed orders and
 * pending orders with an unexpired (or null) hold. Parking excluded.
 * Callers hold the event's ticket-type row locks (serialises writers).
 */
class PersonLimitGuard
{
    /**
     * Count a person's admission tickets for an event.
     */
    public function count(?string $idNumber, ?string $phone, string $eventId, ?string $excludeHolderId = null): int
    {
        $key = PhoneNumber::personKey($idNumber, $phone);
        if ($key === null) {
            return 0;
        }

        return $this->countByKey($key, $eventId, $excludeHolderId);
    }

    public function countByKey(string $key, string $eventId, ?string $excludeHolderId = null): int
    {
        $now = QatarClock::nowUtc();
        $q = DB::table('ticket_holders as h')
            ->join('orders as o', 'o.id', '=', 'h.order_id')
            ->whereNull('o.deleted_at')
            ->where('o.event_id', $eventId)
            ->whereIn('h.ticket_type', ['vip', 'normal'])
            ->where(fn ($w) => $w->where('o.payment_status', PaymentStatus::Confirmed->value)
                ->orWhere(fn ($p) => $p->where('o.payment_status', PaymentStatus::Pending->value)
                    ->where(fn ($e) => $e->whereNull('o.payment_expires_at')->orWhere('o.payment_expires_at', '>', $now))))
            ->when($excludeHolderId, fn ($q) => $q->where('h.id', '!=', $excludeHolderId));

        if (str_starts_with($key, 'id:')) {
            // ID key wins; phone ignored (R-021).
            $q->where('h.person_key', $key);
        } else {
            $last8 = substr($key, 3);
            $q->where(fn ($w) => $w->where('h.person_key', $key)->orWhere('h.phone_e164', 'like', '%'.$last8));
        }

        return (int) $q->count();
    }

    /**
     * Assert the cap for the holders of one request (in input order).
     * Holders already accepted earlier in the same request count (R-025).
     *
     * @param  list<array{name:string, id_number:?string, phone:?string, type:string}>  $holders
     */
    public function assert(string $eventId, array $holders, int $cap): void
    {
        $inRequest = [];
        $dbCache = [];
        foreach ($holders as $h) {
            if (! in_array($h['type'], ['vip', 'normal'], true)) {
                continue;
            }
            $key = PhoneNumber::personKey($h['id_number'] ?? null, $h['phone'] ?? null);
            if ($key === null) {
                continue;
            }
            $dbCache[$key] ??= $this->countByKey($key, $eventId);
            $existing = $dbCache[$key] + ($inRequest[$key] ?? 0);
            if ($existing >= $cap) {
                throw self::limitError($h['name'], $existing, $cap);
            }
            $inRequest[$key] = ($inRequest[$key] ?? 0) + 1;
        }
    }

    /** R-022 verbatim server text. */
    public static function limitError(string $name, int $existing, int $cap = 5): DomainError
    {
        return new DomainError(
            'ticket_limit_exceeded',
            422,
            "الحد الأقصى هو {$cap} تذاكر (عادي + VIP) لكل شخص. {$name} لديه {$existing} تذكرة بالفعل",
        );
    }

    /**
     * Advisory pre-check groups (R-023) used by checkout and POS.
     *
     * @param  list<array{name:string, id_number:?string, phone:?string, type:string}>  $holders
     * @return list<array{name:string, existing:int, requested:int, remaining:int, message:string}>
     */
    public function violations(string $eventId, array $holders, int $cap): array
    {
        $groups = [];
        foreach ($holders as $h) {
            if (! in_array($h['type'], ['vip', 'normal'], true)) {
                continue;
            }
            $key = PhoneNumber::personKey($h['id_number'] ?? null, $h['phone'] ?? null);
            if ($key === null) {
                continue;
            }
            $groups[$key] ??= ['name' => $h['name'], 'requested' => 0];
            $groups[$key]['requested']++;
        }
        $out = [];
        foreach ($groups as $key => $g) {
            $existing = $this->countByKey($key, $eventId);
            if ($existing + $g['requested'] > $cap) {
                $remaining = max(0, $cap - $existing);
                $out[] = [
                    'name' => $g['name'], 'existing' => $existing, 'requested' => $g['requested'], 'remaining' => $remaining,
                    'message' => $remaining === 0
                        ? __('orders.limit_reached', ['name' => $g['name'], 'cap' => $cap])
                        : __('orders.limit_partial', ['name' => $g['name'], 'existing' => $existing, 'remaining' => $remaining, 'requested' => $g['requested']]),
                ];
            }
        }

        return $out;
    }
}
