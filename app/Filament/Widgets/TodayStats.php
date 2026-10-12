<?php

namespace App\Filament\Widgets;

use App\Domain\Shared\Actor;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Formats;
use App\Support\QatarClock;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Dashboard KPIs for the Qatar day, with 7-day sparklines and day-over-day deltas. */
class TodayStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return Actor::current()->can('orders.view');
    }

    protected function getStats(): array
    {
        $d = Cache::remember('admin.dash.stats.'.QatarClock::today(), 30, fn () => $this->compute());
        $delta = function (float $now, float $prev) {
            if ($prev <= 0) {
                return null;
            }
            $p = (int) round(($now - $prev) / $prev * 100);

            return ($p >= 0 ? '+' : '').$p.'% '.__('admin.dash.vs_yesterday');
        };

        return [
            Stat::make(__('admin.dash.revenue_today'), Formats::money($d['revenue'][6], short: true))
                ->description($delta($d['revenue'][6], $d['revenue'][5]) ?? __('admin.dash.confirmed_only'))
                ->descriptionIcon($d['revenue'][6] >= $d['revenue'][5] ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
                ->color($d['revenue'][6] >= $d['revenue'][5] ? 'success' : 'warning')->chart($d['revenue']),
            Stat::make(__('admin.dash.bookings_today'), (string) $d['orders'][6])
                ->description(__('admin.dash.tickets_n', ['n' => $d['tickets']]))->descriptionIcon(Heroicon::Ticket)
                ->color('primary')->chart($d['orders']),
            Stat::make(__('admin.dash.checked_in_today'), (string) $d['checkins'])
                ->description(__('admin.dash.of_valid', ['n' => $d['valid']]))->descriptionIcon(Heroicon::QrCode)->color('info'),
            Stat::make(__('admin.dash.pending'), (string) $d['pending'])
                ->description($d['review'] ? __('admin.dash.review_n', ['n' => $d['review']]) : __('admin.dash.awaiting_payment'))
                ->descriptionIcon($d['review'] ? Heroicon::ExclamationTriangle : Heroicon::Clock)
                ->color($d['review'] ? 'danger' : 'gray')
                ->url(OrderResource::getUrl('index', ['tab' => $d['review'] ? 'review' : 'pending'])),
        ];
    }

    private function compute(): array
    {
        $today = QatarClock::today();
        [$from] = QatarClock::dayRange(QatarClock::local($today)->subDays(6)->toDateString());
        [$tFrom, $tTo] = QatarClock::dayRange($today);

        $rows = Order::query()->where('payment_status', 'confirmed')->where('created_at', '>=', $from)
            ->get(['created_at', 'total_amount', 'quantity']);
        $revenue = $orders = array_fill(0, 7, 0);
        $tickets = 0;
        foreach ($rows as $o) {
            $i = 6 - (int) QatarClock::local($o->created_at)->startOfDay()->diffInDays(QatarClock::local($today)->startOfDay());
            if ($i < 0 || $i > 6) {
                continue;
            }
            $revenue[$i] += (float) $o->total_amount;
            $orders[$i]++;
            if ($i === 6) {
                $tickets += (int) $o->quantity;
            }
        }

        $checkins = DB::table('ticket_holders')->whereBetween('checked_in_at', [$tFrom, $tTo])->count();
        $valid = DB::table('ticket_holders as h')->join('orders as o', 'o.id', '=', 'h.order_id')->join('events as e', 'e.id', '=', 'o.event_id')
            ->whereNull('o.deleted_at')->where('o.payment_status', 'confirmed')
            ->where('e.event_date', '<', $tTo)->where(fn ($q) => $q->where(fn ($x) => $x->whereNull('e.end_date')->where('e.event_date', '>=', $tFrom))->orWhere('e.end_date', '>=', $tFrom))
            ->count();

        return [
            'revenue' => $revenue, 'orders' => $orders, 'tickets' => $tickets, 'checkins' => $checkins, 'valid' => $valid,
            'pending' => Order::query()->where('payment_status', 'pending')->count(),
            'review' => Order::query()->whereNotNull('review_required_at')->whereNull('refund_handled_at')->count(),
        ];
    }
}
