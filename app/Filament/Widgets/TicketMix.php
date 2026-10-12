<?php

namespace App\Filament\Widgets;

use App\Domain\Shared\Actor;
use App\Enums\TicketKind;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/** Current-season confirmed tickets by type. */
class TicketMix extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return Actor::current()->can('tickets.view');
    }

    public function getHeading(): string
    {
        return __('admin.dash.mix');
    }

    protected function getData(): array
    {
        $rows = DB::table('ticket_holders as h')->join('orders as o', 'o.id', '=', 'h.order_id')->join('events as e', 'e.id', '=', 'o.event_id')
            ->whereNull('o.deleted_at')->where('o.payment_status', 'confirmed')->where('e.is_archived', false)
            ->selectRaw('h.ticket_type as t, COUNT(*) as n')->groupBy('h.ticket_type')->pluck('n', 't');
        $kinds = TicketKind::displayOrder();

        return [
            'datasets' => [[
                'data' => array_map(fn ($k) => (int) ($rows[$k->value] ?? 0), $kinds),
                'backgroundColor' => ['#9C1638', '#F9B11F', '#0D80F2'],
                'borderWidth' => 0,
            ]],
            'labels' => array_map(fn ($k) => $k->label(), $kinds),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return ['cutout' => '68%', 'plugins' => ['legend' => ['position' => 'bottom']], 'scales' => ['x' => ['display' => false], 'y' => ['display' => false]]];
    }
}
