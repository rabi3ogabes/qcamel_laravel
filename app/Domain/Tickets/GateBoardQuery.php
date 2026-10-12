<?php

namespace App\Domain\Tickets;

use App\Models\Event;
use App\Settings\EventSettings;
use App\Settings\FeatureFlagSettings;
use App\Support\Formats;
use Illuminate\Support\Facades\DB;

/**
 * F-STF-012…017 gate board (R-134, D-061): issued = ADMISSIBLE tickets of the
 * event (confirmed + admissible web cash bookings), so scanned ≤ issued;
 * "no current event" is a friendly 200 state.
 */
class GateBoardQuery
{
    public function forEvent(?string $eventId = null): array
    {
        $eventId ??= rescue(fn () => app(EventSettings::class)->current_event_id, null, false);
        $event = $eventId ? Event::query()->find($eventId) : null;
        // Fallback: today's (first) public event when no current event is configured.
        $event ??= Event::query()->public()->onQatarDate(\App\Support\QatarClock::today())->orderBy('display_order')->orderBy('event_date')->first();
        if (! $event) {
            return ['success' => true, 'code' => 'no_current_event', 'event' => null, 'totals' => [], 'issued' => 0, 'scanned' => 0, 'recent' => [], 'generated_at' => now()->toIso8601String()];
        }
        $cash = rescue(fn () => app(FeatureFlagSettings::class)->admit_unpaid_cash_bookings, true, false);

        $rows = DB::table('ticket_holders as h')
            ->join('orders as o', 'o.id', '=', 'h.order_id')
            ->whereNull('o.deleted_at')
            ->where('o.event_id', $event->id)
            ->where(function ($w) use ($cash) {
                $w->where('o.payment_status', 'confirmed');
                if ($cash) {
                    $w->orWhere(fn ($c) => $c->where('o.payment_status', 'pending')->where('o.payment_method', 'cash_pos')->where('o.source', 'web'));
                }
            })
            ->selectRaw('h.ticket_type, COUNT(*) as issued, SUM(CASE WHEN h.is_present THEN 1 ELSE 0 END) as scanned')
            ->groupBy('h.ticket_type')
            ->get();

        $totals = [];
        foreach ($rows as $r) {
            $totals[$r->ticket_type] = ['issued' => (int) $r->issued, 'scanned' => (int) $r->scanned];
        }
        $issued = array_sum(array_column($totals, 'issued'));
        $scanned = array_sum(array_column($totals, 'scanned'));

        $recent = DB::table('ticket_holders as h')
            ->join('orders as o', 'o.id', '=', 'h.order_id')
            ->where('o.event_id', $event->id)->where('h.is_present', true)->whereNotNull('h.checked_in_at')
            ->orderByDesc('h.checked_in_at')->limit((int) config('qcamelmc.gate.board_recent', 25))
            ->get(['h.id', 'h.name', 'h.ticket_type', 'h.checked_in_at', 'h.checked_in_by_name'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'ticket_type' => $r->ticket_type, 'confirmed_at' => $r->checked_in_at, 'time' => Formats::timeOf($r->checked_in_at), 'confirmed_by_name' => $r->checked_in_by_name])
            ->all();

        // NEW: per-gate-staff counts.
        $byStaff = DB::table('ticket_holders as h')->join('orders as o', 'o.id', '=', 'h.order_id')
            ->where('o.event_id', $event->id)->where('h.is_present', true)
            ->selectRaw("COALESCE(h.checked_in_by_name, '—') as staff, COUNT(*) as n")->groupBy('h.checked_in_by_name')->orderByDesc('n')->limit(12)
            ->pluck('n', 'staff')->map(fn ($n) => (int) $n)->all();

        return [
            'success' => true,
            'event' => ['id' => $event->id, 'title' => $event->title, 'event_date' => $event->event_date?->toIso8601String()],
            'totals' => $totals, 'issued' => $issued, 'scanned' => $scanned,
            'remaining' => max(0, $issued - $scanned),
            'percent' => $issued > 0 ? (int) round($scanned / $issued * 100) : 0,
            'recent' => $recent, 'by_staff' => $byStaff, 'generated_at' => now()->toIso8601String(),
        ];
    }
}
