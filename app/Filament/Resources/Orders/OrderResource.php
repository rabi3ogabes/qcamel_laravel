<?php

namespace App\Filament\Resources\Orders;

use App\Domain\Shared\Actor;
use App\Filament\Concerns\InNavigationGroup;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** ADM-ORD — bookings management (default landing page of the dashboard). */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    use InNavigationGroup;

    protected static string $navGroup = 'orders';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'booking_reference';

    public static function getModelLabel(): string
    {
        return __('admin.orders.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.orders.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Order::query()->whereNotNull('review_required_at')->whereNull('refund_handled_at')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canViewAny(): bool
    {
        return Actor::current()->can('orders.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'event:id,title,event_date,location', 'posUser:id,name,icon', 'holders:id,order_id,ticket_type'])
            ->withCount('eventChanges');
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['booking_reference'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['customer', 'event']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            __('admin.dash.customer') => $record->customer?->name,
            __('admin.dash.event') => $record->event?->title,
            __('admin.dash.status') => $record->payment_status?->label(),
        ]);
    }
}
