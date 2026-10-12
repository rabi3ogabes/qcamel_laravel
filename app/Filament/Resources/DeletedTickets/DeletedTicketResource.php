<?php

namespace App\Filament\Resources\DeletedTickets;

use App\Domain\Booking\Actions\RecycleBinActions;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Filament\Concerns\InNavigationGroup;
use App\Filament\Resources\DeletedTickets\Pages\ListDeletedTickets;
use App\Models\DeletedTicket;
use App\Support\Formats;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** F-ADM-TKT-025/026: recycle bin — restore under the stock lock, purge (owner only). */
class DeletedTicketResource extends Resource
{
    use InNavigationGroup;

    protected static ?string $model = DeletedTicket::class;

    protected static ?string $slug = 'recycle-bin';

    protected static string $navGroup = 'tickets';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    public static function getModelLabel(): string
    {
        return __('admin.booked.bin_item');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.booked.bin_title');
    }

    public static function canViewAny(): bool
    {
        return Actor::current()->can('tickets.restore');
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('deleted_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('admin.booked.cols.holder'))->searchable()->weight('bold')
                    ->description(fn (DeletedTicket $record) => ($record->context['booking_reference'] ?? '—').' • '.($record->context['event_title'] ?? '')),
                TextColumn::make('ticket_type')->label(__('admin.orders.cols.tickets'))->badge()->formatStateUsing(fn ($state) => __('enums.ticket_kind.'.(is_object($state) ? $state->value : $state))),
                TextColumn::make('holder_created_at')->label(__('admin.booked.sold_label'))->formatStateUsing(fn ($state) => Formats::dateTime($state)),
                TextColumn::make('is_present')->label(__('admin.booked.cols.scan'))->badge()
                    ->formatStateUsing(fn (DeletedTicket $record) => $record->is_present ? __('admin.booked.scanned').' '.Formats::dateTime($record->checked_in_at) : __('admin.booked.not_scanned'))
                    ->color(fn (DeletedTicket $record) => $record->is_present ? 'success' : 'gray'),
                TextColumn::make('deleted_at')->label(__('admin.booked.deleted_at'))->sortable()->formatStateUsing(fn ($state) => Formats::dateTime($state))
                    ->description(fn (DeletedTicket $record) => $record->deleted_by_name),
            ])
            ->recordActions([
                Action::make('restore')->label(__('admin.booked.restore'))->icon(Heroicon::OutlinedArrowUturnLeft)->color('success')
                    ->requiresConfirmation()
                    ->action(function (DeletedTicket $record) {
                        try {
                            app(RecycleBinActions::class)->restore($record, Actor::current());
                            Notification::make()->success()->title(__('tickets.restored'))->send();
                        } catch (DomainError $e) {
                            Notification::make()->danger()->title($e->userMessage())->send();
                        }
                    }),
                Action::make('purge')->label(__('admin.booked.purge'))->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->visible(fn () => Actor::current()->isSuperAdmin())
                    ->requiresConfirmation()->modalDescription(__('admin.booked.purge_confirm'))
                    ->action(function (DeletedTicket $record) {
                        try {
                            app(RecycleBinActions::class)->purge($record, Actor::current());
                            Notification::make()->success()->title(__('tickets.purged'))->send();
                        } catch (DomainError $e) {
                            Notification::make()->danger()->title($e->userMessage())->send();
                        }
                    }),
            ])
            ->emptyStateHeading(__('admin.booked.bin_empty'))
            ->emptyStateDescription(__('admin.booked.bin_hint'));
    }

    public static function getPages(): array
    {
        return ['index' => ListDeletedTickets::route('/')];
    }
}
