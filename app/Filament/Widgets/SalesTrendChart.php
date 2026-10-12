<?php

namespace App\Filament\Widgets;

use App\Domain\Shared\Actor;
use App\Models\Order;
use App\Support\Formats;
use App\Support\QatarClock;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

/** Confirmed revenue per Qatar day, online vs point of sale. */
class SalesTrendChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['md' => 2];

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = '120s';

    public ?string $filter = '14';

    public static function canView(): bool
    {
        return Actor::current()->can('transactions.view');
    }

    public function getHeading(): string
    {
        return __('admin.dash.trend');
    }

    protected function getFilters(): ?array
    {
        return ['7' => __('admin.dash.days_7'), '14' => __('admin.dash.days_14'), '30' => __('admin.dash.days_30')];
    }

    protected function getData(): array
    {
        $days = in_array($this->filter, ['7', '14', '30'], true) ? (int) $this->filter : 14;

        return Cache::remember("admin.dash.trend.$days.".QatarClock::today(), 60, function () use ($days) {
            $today = QatarClock::local(QatarClock::today());
            [$from] = QatarClock::dayRange($today->subDays($days - 1)->toDateString());
            $labels = $online = $pos = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $d = $today->subDays($i);
                $labels[] = Formats::dayMonth($d);
                $online[$d->toDateString()] = 0;
                $pos[$d->toDateString()] = 0;
            }
            Order::query()->where('payment_status', 'confirmed')->where('created_at', '>=', $from)
                ->get(['created_at', 'total_amount', 'payment_method'])
                ->each(function ($o) use (&$online, &$pos) {
                    $k = QatarClock::dateOf($o->created_at);
                    if (! isset($online[$k])) {
                        return;
                    }
                    $o->payment_method?->value === 'sadad' ? $online[$k] += (float) $o->total_amount : $pos[$k] += (float) $o->total_amount;
                });

            return [
                'datasets' => [
                    ['label' => __('admin.dash.online'), 'data' => array_values($online), 'borderColor' => '#9C1638', 'backgroundColor' => 'rgba(156,22,56,0.12)', 'fill' => true, 'tension' => 0.35, 'pointRadius' => 0],
                    ['label' => __('admin.dash.pos'), 'data' => array_values($pos), 'borderColor' => '#F9B11F', 'backgroundColor' => 'rgba(249,177,31,0.12)', 'fill' => true, 'tension' => 0.35, 'pointRadius' => 0],
                ],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['position' => 'bottom']], 'interaction' => ['intersect' => false, 'mode' => 'index'], 'scales' => ['y' => ['beginAtZero' => true]]];
    }
}
