<?php

namespace App\Filament\Widgets;

use App\Domain\Analytics\VisitorAnalyticsQuery;
use App\Support\Formats;
use Filament\Widgets\ChartWidget;

/** F-ADM-VIS-012 daily comparison line chart (embedded on the Visitors vs scans page only). */
class VisitorsVsScansChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 'full';

    public int $days = 7;

    public function getHeading(): string
    {
        return __('admin.vvs.daily');
    }

    protected function getData(): array
    {
        $data = app(VisitorAnalyticsQuery::class)->visitorsVsScans(in_array($this->days, [1, 7, 30], true) ? $this->days : 7);

        return [
            'datasets' => [
                ['label' => __('admin.vvs.visitors'), 'data' => array_column($data['days'], 'visitors'), 'borderColor' => '#9C1638', 'backgroundColor' => 'rgba(156,22,56,0.10)', 'fill' => true, 'tension' => 0.4, 'pointRadius' => 0, 'borderWidth' => 2],
                ['label' => __('admin.vvs.scans'), 'data' => array_column($data['days'], 'scans'), 'borderColor' => '#10B981', 'backgroundColor' => 'rgba(16,185,129,0.10)', 'fill' => true, 'tension' => 0.4, 'pointRadius' => 0, 'borderWidth' => 2],
            ],
            'labels' => array_map(fn ($d) => Formats::dayMonth($d['date']), $data['days']),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['position' => 'bottom']], 'interaction' => ['intersect' => false, 'mode' => 'index'],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]];
    }
}
