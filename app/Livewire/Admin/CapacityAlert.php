<?php

namespace App\Livewire\Admin;

use App\Domain\Shared\Actor;
use App\Models\Event;
use App\Settings\EventSettings;
use App\Support\QatarClock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * ADM-CAP-001…005 — capacity banners for today's event above every admin
 * page. "Today" = active, non-archived event whose Qatar range contains
 * today (multi-day seasons), falling back to the current event; hidden after
 * the configurable cut-off on the last day; cached aggregate, 30 s poll.
 */
class CapacityAlert extends Component
{
    public ?string $eventId = null;

    public function render()
    {
        return view('livewire.admin.capacity-alert', ['data' => $this->data()]);
    }

    /** @return array{event:string, levels:array<string, list<array>>}|null */
    private function data(): ?array
    {
        if (! Actor::current()->can('tickets.view')) {
            return null;
        }
        $today = QatarClock::today();
        $ttl = (int) config('qcamelmc.capacity.cache_seconds', 30);

        return Cache::remember('admin.capacity-alert.'.($this->eventId ?: 'today').'.'.$today, $ttl, function () use ($today) {
            $event = $this->eventId ? Event::query()->find($this->eventId) : $this->todayEvent($today);
            if (! $event || $event->is_archived || $event->isExpired('sales') || ! $event->isToday($today)) {
                return null; // F-ADM-CAP-004: hidden after the cut-off on the last day
            }
            $types = $event->ticketTypes()->get();
            if ($types->isEmpty()) {
                return null;
            }
            $confirmed = DB::table('ticket_holders as h')->join('orders as o', 'o.id', '=', 'h.order_id')
                ->whereNull('o.deleted_at')->where('o.event_id', $event->id)->where('o.payment_status', 'confirmed')
                ->selectRaw('h.ticket_type as t, COUNT(*) as n')->groupBy('h.ticket_type')->pluck('n', 't');

            $th = config('qcamelmc.capacity');
            $levels = ['critical' => [], 'warning' => [], 'nearing' => [], 'normal' => []];
            foreach ($types->sortBy(fn ($t) => $t->type->sortWeight()) as $t) {
                $cap = (int) $t->available_quantity;
                $c = (int) ($confirmed[$t->type->value] ?? 0);
                $pct = $cap > 0 ? (int) round($c / $cap * 100) : 0;
                $level = $pct >= $th['critical'] ? 'critical' : ($pct >= $th['warning'] ? 'warning' : ($pct >= $th['nearing'] ? 'nearing' : 'normal'));
                $levels[$level][] = ['label' => $t->type->label(), 'confirmed' => $c, 'cap' => $cap, 'pct' => $pct, 'left' => max(0, $cap - $c)];
            }

            return ['event' => $event->title, 'levels' => array_filter($levels)];
        });
    }

    private function todayEvent(string $today): ?Event
    {
        $event = Event::query()->public()->onQatarDate($today)->orderBy('display_order')->orderBy('event_date')->first();
        if ($event) {
            return $event;
        }
        $current = app(EventSettings::class)->current_event_id;

        return $current ? Event::query()->public()->find($current) : null;
    }
}
