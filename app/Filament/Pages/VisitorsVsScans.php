<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\VisitorAnalyticsQuery;
use App\Domain\Shared\Actor;
use App\Filament\Concerns\InNavigationGroup;
use App\Support\Formats;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/** ADM-VIS-009…012 — site visitors vs scanned tickets per Qatar day, gate-reach rate. */
class VisitorsVsScans extends Page
{
    use InNavigationGroup;

    protected string $view = 'filament.pages.visitors-vs-scans';

    protected static string $navGroup = 'analytics';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'visitors-vs-scans';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    #[Url]
    public int $days = 7;

    public static function getNavigationLabel(): string
    {
        return __('admin.vvs.title');
    }

    public function getTitle(): string
    {
        return __('admin.vvs.heading');
    }

    public function getSubheading(): ?string
    {
        return __('admin.vvs.sub');
    }

    public static function canAccess(): bool
    {
        return Actor::current()->can('visitors.view');
    }

    protected function getViewData(): array
    {
        $days = in_array($this->days, [1, 7, 30], true) ? $this->days : 7;
        $data = app(VisitorAnalyticsQuery::class)->visitorsVsScans($days);

        return [
            'data' => $data,
            'chart' => [
                'labels' => array_map(fn ($d) => Formats::dayMonth($d['date']), $data['days']),
                'visitors' => array_column($data['days'], 'visitors'),
                'scans' => array_column($data['days'], 'scans'),
            ],
        ];
    }
}
