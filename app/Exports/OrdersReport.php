<?php

namespace App\Exports;

use App\Models\Order;
use App\Support\QatarClock;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * ADM-RPT — multi-sheet orders report (F-ADM-RPT-003…010 rebuild):
 * "All records" + one sheet per event day (orders without an event are kept,
 * D-defect), Sadad purchases + one sheet per purchase day; Qatar dates;
 * optional filters; totals split by status.
 */
class OrdersReport implements Export, WithMultipleSheets
{
    private bool $pii;

    /** @param array{variant?:string, from?:?string, to?:?string, event_id?:?string, status?:?string, method?:?string} $filters */
    public function __construct(private readonly array $filters = [])
    {
        $this->pii = \App\Domain\Shared\Actor::current()->can('customers.view_pii');
    }

    public static function rows(array $filters): Collection
    {
        $q = Order::query()
            ->with(['customer', 'event:id,title,event_date,location', 'holders:id,order_id,name,phone,nationality,id_number,ticket_type,is_present,country_code'])
            ->when(($filters['variant'] ?? 'full') === 'sadad', fn ($q) => $q->where('payment_method', 'sadad'))
            ->when($filters['method'] ?? null, fn ($q, $m) => $q->where('payment_method', $m))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('payment_status', $s))
            ->when($filters['event_id'] ?? null, fn ($q, $e) => $q->where('event_id', $e))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', QatarClock::dayRange($d)[0]))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<', QatarClock::dayRange($d)[1]))
            ->orderBy('created_at');

        $rows = collect();
        $q->lazyById(500)->each(fn (Order $o) => $rows->push($o));

        return $rows;
    }

    public function sheets(): array
    {
        $rows = self::rows($this->filters);
        $rank = fn (Order $o) => ['confirmed' => 1, 'pending' => 2, 'cancelled' => 3][$o->payment_status?->value] ?? 4;
        $sorted = fn (Collection $c) => $c->sortBy([fn ($a, $b) => $rank($a) <=> $rank($b)])->values();
        $sheets = [];

        if (($this->filters['variant'] ?? 'full') !== 'sadad') {
            $byEventDay = $rows->sortBy(fn (Order $o) => $o->event?->event_date?->getTimestamp() ?? PHP_INT_MAX);
            $sheets[] = new OrdersReportSheet($this->pii, __('admin.rpt.sheet_all'), $sorted($byEventDay));
            $rows->groupBy(fn (Order $o) => $o->event ? QatarClock::dateOf($o->event->event_date) : '~')->sortKeys()
                ->each(function (Collection $group, string $day) use (&$sheets, $sorted) {
                    $title = $day === '~' ? __('admin.rpt.sheet_no_event') : __('admin.rpt.sheet_event', ['d' => QatarClock::local($day)->format('d-m-Y')]);
                    $sheets[] = new OrdersReportSheet($this->pii, $title, $sorted($group));
                });
        }

        $sadad = $rows->filter(fn (Order $o) => $o->payment_method?->value === 'sadad');
        if ($sadad->isNotEmpty()) {
            $sheets[] = new OrdersReportSheet($this->pii, __('admin.rpt.sheet_sadad_all'), $sorted($sadad));
            $sadad->groupBy(fn (Order $o) => QatarClock::dateOf($o->created_at))->sortKeys()
                ->each(function (Collection $group, string $day) use (&$sheets, $sorted) {
                    $sheets[] = new OrdersReportSheet($this->pii, __('admin.rpt.sheet_sadad', ['d' => QatarClock::local($day)->format('d-m-Y')]), $sorted($group));
                });
        }

        return $sheets ?: [new OrdersReportSheet($this->pii, __('admin.rpt.sheet_all'), collect())];
    }
}
