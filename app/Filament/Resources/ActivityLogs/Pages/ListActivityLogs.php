<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Domain\Shared\Actor;
use App\Enums\ActivityType;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\Activity;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.log.sub');
    }

    /** F-ADM-LOG-003 rebuild: one tab per log group. */
    public function getTabs(): array
    {
        $groups = [
            'all' => [null, null],
            'orders' => [['order_cancelled', 'order_confirmed', 'order_deleted', 'payment_review', 'invoice_dispatch', 'event_change'], Heroicon::OutlinedShoppingBag],
            'gate' => [['ticket_scan', 'qr_search', 'ticket_checkin_reset'], Heroicon::OutlinedQrCode],
            'pos' => [['pos_form', 'invoice_download'], Heroicon::OutlinedBanknotes],
            'admin' => [['settings_changed', 'staff_role_changed', 'attendance', 'pos_user_changed', 'event_updated', 'new_event_started', 'price_changed', 'capacity_changed', 'stock_recount', 'ticket_binned', 'ticket_restored', 'customer_changed', 'data_management'], Heroicon::OutlinedCog6Tooth],
            'security' => [['auth', 'login_blocked'], Heroicon::OutlinedLockClosed],
        ];
        $tabs = [];
        foreach ($groups as $key => [$types, $icon]) {
            $tab = Tab::make(__('admin.log.tabs.'.$key));
            if ($icon) {
                $tab->icon($icon);
            }
            if ($types) {
                $tab->modifyQueryUsing(fn (Builder $query) => $query->whereIn('log_name', $types));
            }
            $tabs[$key] = $tab;
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('purge')->label(__('admin.log.purge'))->icon(Heroicon::OutlinedTrash)->color('danger')->outlined()
                ->visible(fn () => Actor::current()->can('logs.purge'))
                ->modalHeading(__('admin.log.purge'))->modalDescription(__('admin.log.purge_desc'))->modalIcon(Heroicon::OutlinedExclamationTriangle)
                ->schema([Select::make('days')->label(__('admin.log.older_than'))->required()->native(false)
                    ->options([90 => __('admin.log.days', ['n' => 90]), 180 => __('admin.log.days', ['n' => 180]), 365 => __('admin.log.days', ['n' => 365])])])
                ->requiresConfirmation()
                ->action(function (array $data) {
                    $days = (int) $data['days'];
                    $n = Activity::query()->where('created_at', '<', now()->subDays($days))->delete();
                    Audit::log(ActivityType::DataManagement, __('admin.log.audit_purge', ['n' => $n, 'days' => $days]), null, ['deleted' => $n, 'older_than_days' => $days]);
                    Notification::make()->success()->title(__('admin.log.purged', ['n' => $n]))->send();
                }),
        ];
    }
}
