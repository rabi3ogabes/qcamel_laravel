<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Domain\Shared\Actor;
use App\Exports\OrdersExport;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Support\QatarClock;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

/** F-ADM-TRX-002: Sadad / POS channel tabs (no "all" channel). */
class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    public function getTabs(): array
    {
        return [
            'sadad' => Tab::make(__('enums.payment_method_short.sadad'))->icon(Heroicon::OutlinedCreditCard)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_method', 'sadad')),
            'cash_pos' => Tab::make(__('enums.payment_method_short.cash_pos'))->icon(Heroicon::OutlinedBanknotes)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_method', 'cash_pos')),
        ];
    }

    public function getSubheading(): ?string
    {
        return ($this->activeTab ?? 'sadad') === 'sadad' ? __('admin.trx.sub_sadad') : __('admin.trx.sub_pos');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label(__('admin.common.export_xlsx'))->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->visible(fn () => Actor::current()->can('reports.export') || Actor::current()->can('orders.export'))
                ->action(fn () => Excel::download(new OrdersExport($this->getFilteredTableQuery()->pluck('orders.id')->all()), 'transactions-'.QatarClock::today().'.xlsx')),
        ];
    }
}
