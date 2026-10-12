<?php

namespace App\Filament\Widgets;

use App\Domain\Shared\Actor;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Formats;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** The ten newest bookings, linking to the booking page. */
class LatestOrders extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';


    public static function canView(): bool
    {
        return Actor::current()->can('orders.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.dash.latest'))
            ->query(fn (): Builder => Order::query()->with(['customer', 'event'])->latest()->limit(10))
            ->paginated(false)->poll('30s')
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('booking_reference')->label(__('admin.dash.reference'))->fontFamily('mono')->size('sm')->weight('bold'),
                TextColumn::make('customer.name')->label(__('admin.dash.customer'))->placeholder('—'),
                TextColumn::make('event.title')->label(__('admin.dash.event'))->limit(28)->placeholder('—'),
                TextColumn::make('quantity')->label(__('admin.dash.tickets'))->alignCenter(),
                TextColumn::make('total_amount')->label(__('admin.dash.amount'))->formatStateUsing(fn ($state) => Formats::money($state, short: true)),
                TextColumn::make('payment_status')->label(__('admin.dash.status'))->badge()
                    ->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                TextColumn::make('created_at')->label(__('admin.dash.when'))->formatStateUsing(fn ($state) => Formats::ago($state)),
            ]);
    }
}
