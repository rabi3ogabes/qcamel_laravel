<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Filament\Support\OrderActions;
use App\Models\Order;
use App\Support\Formats;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** F-ADM-CUS-009/012/013: the customer's bookings with the shared order actions. */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.cus.orders');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['event:id,title,event_date', 'holders:id,order_id,ticket_type']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('booking_reference')->label(__('admin.orders.cols.reference'))->fontFamily('mono')->weight('bold')->copyable()
                    ->description(fn (Order $record) => trim(($record->event?->title ?? '—').' · '.Formats::date($record->event?->event_date))),
                TextColumn::make('payment_status')->label(__('admin.orders.cols.status'))->badge()
                    ->formatStateUsing(fn (Order $record) => __('enums.public_payment_status.'.$record->publicStatus()))
                    ->color(fn (Order $record) => match ($record->publicStatus()) {
                        'confirmed' => 'success', 'pending' => 'warning', default => 'danger'
                    })
                    ->description(fn (Order $record) => $record->noteLabel()),
                TextColumn::make('quantity')->label(__('admin.orders.cols.tickets'))->formatStateUsing(fn (Order $record) => OrdersTable::ticketSummary($record))->html(),
                TextColumn::make('total_amount')->label(__('admin.orders.cols.amount'))->formatStateUsing(fn ($state) => Formats::money($state, short: true)),
                TextColumn::make('created_at')->label(__('admin.orders.cols.booked_at'))->formatStateUsing(fn ($state) => Formats::dateTime($state)),
            ])
            ->recordActions([
                Action::make('open')->label(__('admin.common.open'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)->iconButton()->color('gray')
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record])),
                ActionGroup::make(OrderActions::all())->icon(Heroicon::OutlinedEllipsisVertical),
            ]);
    }
}
