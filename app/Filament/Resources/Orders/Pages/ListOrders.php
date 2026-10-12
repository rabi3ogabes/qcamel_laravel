<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Shared\Actor;
use App\Domain\Tickets\QrService;
use App\Exports\OrdersExport;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Settings\FeatureFlagSettings;
use App\Support\QatarClock;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

/** F-ADM-ORD-002 rebuild: confirmed / pending / not completed / needs review tabs with live counts. */
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        $count = fn (callable $scope) => $scope(Order::query())->count();

        return [
            'confirmed' => Tab::make(__('admin.orders.tabs.confirmed'))->icon(Heroicon::OutlinedCheckCircle)
                ->badge($count(fn ($q) => $q->where('payment_status', 'confirmed')))->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_status', 'confirmed')),
            'pending' => Tab::make(__('admin.orders.tabs.pending'))->icon(Heroicon::OutlinedClock)
                ->badge($count(fn ($q) => $q->where('payment_status', 'pending')))->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_status', 'pending')),
            'cancelled' => Tab::make(__('admin.orders.tabs.cancelled'))->icon(Heroicon::OutlinedXCircle)
                ->badge($count(fn ($q) => $q->where('payment_status', 'cancelled')))->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_status', 'cancelled')),
            'review' => Tab::make(__('admin.orders.tabs.review'))->icon(Heroicon::OutlinedExclamationTriangle)
                ->badge($count(fn ($q) => $q->whereNotNull('review_required_at')->whereNull('refund_handled_at')) ?: null)->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('review_required_at')),
            'all' => Tab::make(__('common.all')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'confirmed';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label(__('admin.common.export_xlsx'))->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->visible(fn () => Actor::current()->can('orders.export'))
                ->action(fn () => Excel::download(new OrdersExport($this->getFilteredTableQuery()->pluck('orders.id')->all()), 'orders-'.QatarClock::today().'.xlsx')),
            Action::make('backfill')->label(__('admin.orders.actions.backfill_qr'))->icon(Heroicon::OutlinedQrCode)->color('gray')
                ->visible(fn () => Actor::current()->can('tickets.edit') && rescue(fn () => app(FeatureFlagSettings::class)->show_generate_qr_button, false, false))
                ->requiresConfirmation()
                ->action(function () {
                    $res = app(QrService::class)->backfill(100);
                    Notification::make()->success()->title(__('admin.orders.notify.backfilled', ['n' => $res['updated'] ?? 0]))
                        ->body(($res['updated'] ?? 0) >= 100 ? __('admin.orders.notify.backfill_more') : null)->send();
                }),
        ];
    }
}
