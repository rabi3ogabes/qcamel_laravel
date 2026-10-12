<?php

namespace App\Domain\Analytics;

use App\Models\ActiveVisitor;
use App\Support\QatarClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Admin analytics (F-ADM-VIS-001…012, F-LIVE-032…043, R-010, T-125):
 * everything grouped by QATAR date with SQL aggregates, no row caps.
 */
class VisitorAnalyticsQuery
{
    /** @return array<string,mixed> live counters + breakdowns of active visitors */
    public function live(): array
    {
        $rows = ActiveVisitor::query()->activeNow()->orderByDesc('last_seen_at')->get();
        $count = fn (string $col) => $rows->groupBy(fn ($r) => $r->{$col} ?: 'unknown')->map->count()->sortDesc()->all();

        return [
            'total' => $rows->count(),
            'new' => $rows->where('is_new_visitor', true)->count(),
            'returning' => $rows->where('is_new_visitor', false)->count(),
            'desktop' => $rows->where('device_type', 'desktop')->count(),
            'mobile' => $rows->where('device_type', 'mobile')->count(),
            'tablet' => $rows->where('device_type', 'tablet')->count(),
            'countries' => $rows->groupBy(fn ($r) => ($r->country_code ?: 'XX').'|'.($r->country ?: __('common.unknown')))->map->count()->sortDesc()->all(),
            'sources' => $count('traffic_source'),
            'pages' => $count('current_page'),
            'browsers' => $count('browser'),
            'os' => $count('os'),
            'cities' => $count('city'),
            'rows' => $rows,
        ];
    }

    /** @return list<array{date:string,total:int,unique:int,new:int,returning:int}> */
    public function perDate(string $from, string $to): array
    {
        return DB::table('page_views')
            ->whereBetween('viewed_on_qatar', [$from, $to])
            ->selectRaw('viewed_on_qatar as d, COUNT(*) as total, COUNT(DISTINCT session_id) as uniq, SUM(CASE WHEN is_new_visitor THEN 1 ELSE 0 END) as new_v')
            ->groupBy('viewed_on_qatar')
            ->orderBy('viewed_on_qatar')
            ->get()
            ->map(fn ($r) => ['date' => substr((string) $r->d, 0, 10), 'total' => (int) $r->total, 'unique' => (int) $r->uniq, 'new' => (int) $r->new_v, 'returning' => max(0, (int) $r->total - (int) $r->new_v)])
            ->all();
    }

    public function uniqueInRange(string $from, string $to): int
    {
        return (int) DB::table('page_views')->whereBetween('viewed_on_qatar', [$from, $to])->distinct()->count('session_id');
    }

    /** @return list<array{path:string, views:int, unique:int}> */
    public function topPages(string $from, string $to, int $limit = 20): array
    {
        return DB::table('page_views')->whereBetween('viewed_on_qatar', [$from, $to])
            ->selectRaw('page_path, COUNT(*) as views, COUNT(DISTINCT session_id) as uniq')
            ->groupBy('page_path')->orderByDesc('views')->limit($limit)->get()
            ->map(fn ($r) => ['path' => $r->page_path, 'views' => (int) $r->views, 'unique' => (int) $r->uniq])->all();
    }

    /** @return list<array{country:string, code:string, total:int, unique:int}> */
    public function topCountries(string $from, string $to, int $limit = 20): array
    {
        return DB::table('page_views')->whereBetween('viewed_on_qatar', [$from, $to])
            ->selectRaw("COALESCE(country, 'Unknown') as country, COALESCE(country_code, 'XX') as code, COUNT(*) as total, COUNT(DISTINCT session_id) as uniq")
            ->groupBy('country', 'country_code')->orderByDesc('total')->limit($limit)->get()
            ->map(fn ($r) => ['country' => $r->country, 'code' => $r->code, 'total' => (int) $r->total, 'unique' => (int) $r->uniq])->all();
    }

    /**
     * F-ADM-VIS-009…012 visitors vs scans per Qatar day (last N days incl. today).
     *
     * @return array{days:list<array{date:string, visitors:int, scans:int}>, visitors:int, scans:int, rate:int}
     */
    public function visitorsVsScans(int $days): array
    {
        $today = CarbonImmutable::parse(QatarClock::today());
        $from = $today->subDays($days - 1)->toDateString();
        $to = $today->toDateString();
        $visitors = collect($this->perDate($from, $to))->keyBy('date');

        [$start] = QatarClock::dayRange($from);
        [, $end] = QatarClock::dayRange($to);
        $scans = DB::table('ticket_holders')->where('is_present', true)->whereBetween('checked_in_at', [$start, $end])
            ->pluck('checked_in_at')
            ->groupBy(fn ($t) => QatarClock::dateOf($t))
            ->map->count();

        $out = [];
        for ($d = CarbonImmutable::parse($from); $d->lte($today); $d = $d->addDay()) {
            $k = $d->toDateString();
            $out[] = ['date' => $k, 'visitors' => (int) ($visitors[$k]['unique'] ?? 0), 'scans' => (int) ($scans[$k] ?? 0)];
        }
        $v = array_sum(array_column($out, 'visitors'));
        $s = array_sum(array_column($out, 'scans'));

        return ['days' => $out, 'visitors' => $v, 'scans' => $s, 'rate' => $v > 0 ? (int) round($s / $v * 100) : 0];
    }

    /** U-ADM-VIS-02 conversion funnel (visits → checkout → paid → scanned). */
    public function funnel(string $from, string $to): array
    {
        [$start] = QatarClock::dayRange($from);
        [, $end] = QatarClock::dayRange($to);
        $visits = $this->uniqueInRange($from, $to);
        $checkout = (int) DB::table('page_views')->whereBetween('viewed_on_qatar', [$from, $to])->where('page_path', 'like', '/checkout%')->distinct()->count('session_id');
        $orders = (int) DB::table('orders')->whereNull('deleted_at')->where('source', 'web')->whereBetween('created_at', [$start, $end])->count();
        $paid = (int) DB::table('orders')->whereNull('deleted_at')->where('source', 'web')->where('payment_status', 'confirmed')->whereBetween('created_at', [$start, $end])->count();
        $scanned = (int) DB::table('ticket_holders')->where('is_present', true)->whereBetween('checked_in_at', [$start, $end])->count();

        return compact('visits', 'checkout', 'orders', 'paid', 'scanned');
    }
}
