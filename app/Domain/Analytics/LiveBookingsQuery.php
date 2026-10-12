<?php

namespace App\Domain\Analytics;

use App\Domain\Booking\Queries\PersonQueries;
use App\Models\Event;
use App\Models\TicketHolder;
use App\Models\TicketType;
use App\Support\Countries;
use App\Support\Money;
use App\Support\QatarClock;
use App\Support\Text;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * F-LIVE-003…029 aggregates, computed in SQL by Qatar day (UTC-safe),
 * never by downloading every order (D: "show all" loaded everything).
 */
class LiveBookingsQuery
{
    public function __construct(private readonly PersonQueries $people) {}

    /** F-LIVE-003: first active, still-sellable event's Qatar date, else today. */
    public function defaultDate(): string
    {
        $e = Event::query()->public()->orderBy('event_date')->get()->first(fn (Event $e) => $e->canPurchaseTickets());

        return $e ? $e->firstDay() : QatarClock::today();
    }

    /** @return list<string> */
    private function eventIds(?string $date): array
    {
        return $date
            ? Event::query()->withTrashed()->onQatarDate($date)->pluck('id')->all()
            : [];
    }

    private function base(?string $date)
    {
        $q = DB::table('orders as o')->whereNull('o.deleted_at')->where('o.payment_status', 'confirmed');
        if ($date) {
            $q->whereIn('o.event_id', $this->eventIds($date) ?: ['00000000-0000-0000-0000-000000000000']);
        }

        return $q;
    }

    /** @return array<string,mixed> */
    public function summary(?string $date): array
    {
        $orders = (clone $this->base($date))->selectRaw('COUNT(*) as n, COALESCE(SUM(total_amount),0) as revenue')->first();
        $lastHour = (clone $this->base($date))->where('o.created_at', '>=', now()->subHour())->count();

        $holders = (clone $this->base($date))->join('ticket_holders as h', 'h.order_id', '=', 'o.id');
        $byType = (clone $holders)->selectRaw('h.ticket_type as t, COUNT(*) as total, SUM(CASE WHEN h.is_present THEN 1 ELSE 0 END) as present')
            ->groupBy('h.ticket_type')->get()->keyBy('t');

        $channel = "CASE WHEN o.payment_method = 'sadad' THEN 'online' WHEN o.source = 'pos' THEN 'pos' ELSE 'venue' END";
        $split = (clone $holders)->selectRaw("$channel as ch, h.ticket_type as t, COUNT(*) as total, SUM(CASE WHEN h.is_present THEN 1 ELSE 0 END) as present")
            ->groupByRaw("$channel, h.ticket_type")->get();
        $channels = [];
        foreach ($split as $r) {
            $channels[$r->ch]['total'] = ($channels[$r->ch]['total'] ?? 0) + (int) $r->total;
            $channels[$r->ch]['types'][$r->t] = ['total' => (int) $r->total, 'present' => (int) $r->present];
        }

        $nat = (clone $holders)->selectRaw("COALESCE(NULLIF(h.nationality, ''), '—') as nat, SUM(CASE WHEN o.payment_method = 'sadad' THEN 1 ELSE 0 END) as online, SUM(CASE WHEN o.payment_method = 'sadad' THEN 0 ELSE 1 END) as pos, COUNT(*) as total, SUM(CASE WHEN h.is_present THEN 1 ELSE 0 END) as present")
            ->groupBy('nat')->orderByDesc('total')->limit(40)->get()
            ->map(fn ($r) => ['name' => $r->nat === '—' ? __('ops.live.unknown_nationality') : Countries::label($r->nat), 'flag' => $r->nat === '—' ? '🌍' : Countries::flag($r->nat, '🌍'), 'online' => (int) $r->online, 'pos' => (int) $r->pos, 'total' => (int) $r->total, 'present' => (int) $r->present])
            ->all();

        $capacity = $date
            ? TicketType::query()->whereIn('event_id', $this->eventIds($date))->selectRaw('type, SUM(available_quantity) as cap')->groupBy('type')->pluck('cap', 'type')->map(fn ($v) => (int) $v)->all()
            : [];

        $types = [];
        foreach (['normal', 'vip', 'parking'] as $t) {
            $types[$t] = ['total' => (int) ($byType[$t]->total ?? 0), 'present' => (int) ($byType[$t]->present ?? 0), 'capacity' => $capacity[$t] ?? null];
        }

        return [
            'bookings' => (int) $orders->n,
            'revenue' => (string) $orders->revenue,
            'tickets' => array_sum(array_column($types, 'total')),
            'present' => array_sum(array_column($types, 'present')),
            'last_hour' => $lastHour,
            'types' => $types,
            'channels' => $channels,
            'nationalities' => $nat,
        ];
    }

    /**
     * Holder cards/table rows, newest first, with lifetime totals per person.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function holders(?string $date, string $search = '', int $limit = 300): Collection
    {
        $q = TicketHolder::query()
            ->with(['order:id,booking_reference,event_id,payment_method,source,n8n_responded_at,invoice_downloaded_at,created_at,confirmed_at,pos_user_id,customer_id', 'order.event:id,title', 'order.posUser:id,name,icon', 'order.customer:id,name'])
            ->whereHas('order', function ($o) use ($date) {
                $o->where('payment_status', 'confirmed');
                if ($date) {
                    $o->whereIn('event_id', $this->eventIds($date) ?: ['00000000-0000-0000-0000-000000000000']);
                }
            });
        $search = trim($search);
        if (mb_strlen($search) >= 2) {
            $like = Text::like($search);
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('qr_code', 'like', $like)
                ->orWhereHas('order', fn ($o) => $o->where('booking_reference', 'like', $like)));
        }
        $rows = $q->latest('created_at')->limit($limit)->get();
        $totals = $this->people->lifetimeTotals($rows->pluck('person_key')->all());
        $count = $rows->count();

        return $rows->values()->map(fn (TicketHolder $h, int $i) => [
            'id' => $h->id,
            'seq' => $count - $i,
            'name' => $h->name,
            'flag' => Countries::flag($h->nationality, '🌍'),
            'nationality' => Countries::label($h->nationality),
            'phone' => $h->displayPhone(),
            'type' => $h->ticket_type->value,
            'type_label' => $h->ticket_type->label(),
            'reference' => $h->order?->booking_reference,
            'event' => $h->order?->event?->title,
            'is_pos' => (bool) $h->order?->isPos(),
            'pos_user' => $h->order?->posUser ? trim(($h->order->posUser->icon ?: '👤').' '.$h->order->posUser->name) : null,
            'sent' => (bool) $h->order?->n8n_responded_at,
            'downloaded' => (bool) $h->order?->invoice_downloaded_at,
            'present' => $h->is_present,
            'checked_in_at' => $h->checked_in_at,
            'confirmed_at' => $h->order?->confirmed_at,
            'created_at' => $h->created_at,
            'recent' => $h->created_at && $h->created_at->gt(now()->subMinutes(5)),
            'lifetime' => $h->person_key ? ($totals[$h->person_key] ?? 0) : 0,
        ]);
    }

    /**
     * F-LIVE-028/029 rebuild: daily sales by Qatar event date from stored line prices.
     *
     * @return array{rows:list<array<string,mixed>>, totals:array<string,mixed>}
     */
    public function dailySales(?string $seasonStart = null, ?bool $archived = null): array
    {
        $rows = DB::table('order_items as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->join('events as e', 'e.id', '=', 'o.event_id')
            ->whereNull('o.deleted_at')->where('o.payment_status', 'confirmed')
            ->when($seasonStart, fn ($q) => $q->where('e.event_date', '>=', QatarClock::at($seasonStart, '00:00:00')->utc()))
            ->when($archived !== null, fn ($q) => $q->where('e.is_archived', $archived))
            ->get(['e.event_date', 'i.type', 'i.quantity', 'i.unit_price', 'i.line_total']);

        $days = [];
        foreach ($rows as $r) {
            $d = QatarClock::dateOf($r->event_date);
            $days[$d][$r->type]['count'] = ($days[$d][$r->type]['count'] ?? 0) + (int) $r->quantity;
            $days[$d][$r->type]['amount'] = self::add($days[$d][$r->type]['amount'] ?? '0', (string) $r->line_total);
            $days[$d][$r->type]['price'] = (string) $r->unit_price;
        }
        ksort($days);
        $out = [];
        $grand = ['vip' => ['count' => 0, 'amount' => '0'], 'normal' => ['count' => 0, 'amount' => '0'], 'parking' => ['count' => 0, 'amount' => '0'], 'total' => '0'];
        foreach ($days as $d => $types) {
            $total = '0';
            foreach (['vip', 'normal', 'parking'] as $t) {
                $total = self::add($total, $types[$t]['amount'] ?? '0');
                $grand[$t]['count'] += $types[$t]['count'] ?? 0;
                $grand[$t]['amount'] = self::add($grand[$t]['amount'], $types[$t]['amount'] ?? '0');
            }
            $grand['total'] = self::add($grand['total'], $total);
            $out[] = ['date' => $d, 'types' => $types, 'total' => $total];
        }

        return ['rows' => $out, 'totals' => $grand];
    }

    private static function add(string $a, string $b): string
    {
        return Money::of($a)->add(Money::of($b))->toDecimal();
    }
}
