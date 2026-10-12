<?php

namespace App\Filament\Resources\PaymentErrors;

use App\Domain\Shared\Actor;
use App\Enums\ActivityType;
use App\Enums\PaymentErrorSource;
use App\Filament\Concerns\InNavigationGroup;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\PaymentErrors\Pages\ListPaymentErrors;
use App\Models\Order;
use App\Models\PaymentError;
use App\Support\Audit;
use App\Support\Formats;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/** ADM-PERR — payment errors log: server-side pagination, resolve (not delete), link to the order. */
class PaymentErrorResource extends Resource
{
    use InNavigationGroup;

    protected static ?string $model = PaymentError::class;

    protected static string $navGroup = 'finance';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    public static function getModelLabel(): string
    {
        return __('admin.perr.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.perr.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = PaymentError::query()->whereNull('resolved_at')->where('created_at', '>=', now()->subDay())->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canViewAny(): bool
    {
        return Actor::current()->can('payment_errors.view');
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
        return Actor::current()->isSuperAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['event:id,title,is_archived', 'resolver:id,name']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100])
            ->poll('30s')
            ->recordClasses(fn (PaymentError $record) => $record->resolved_at ? 'opacity-60' : null)
            ->columns([
                TextColumn::make('created_at')->label(__('admin.perr.cols.time'))->sortable()->formatStateUsing(fn ($state) => Formats::dateTime($state)),
                TextColumn::make('customer_name')->label(__('admin.orders.cols.customer'))->placeholder('—')->searchable()
                    ->description(fn (PaymentError $record) => $record->customer_phone),
                TextColumn::make('quantity')->label(__('admin.orders.cols.tickets'))->placeholder('—')->alignCenter(),
                TextColumn::make('amount')->label(__('admin.orders.cols.amount'))->placeholder('—')->formatStateUsing(fn ($state) => Formats::money($state, short: true)),
                TextColumn::make('error_source')->label(__('admin.perr.cols.source'))->badge()
                    ->formatStateUsing(fn ($state) => __('enums.payment_error_source.'.($state?->value ?? $state)))
                    ->color(fn ($state) => match ($state?->value ?? $state) { 'sadad' => 'warning', 'bank' => 'danger', default => 'gray' }),
                TextColumn::make('error_message')->label(__('admin.perr.cols.reason'))->wrap()->lineClamp(3)
                    ->state(fn (PaymentError $record) => $record->error_message ?: $record->error_code ?: '—')
                    ->description(fn (PaymentError $record) => $record->event?->title),
                TextColumn::make('booking_reference')->label(__('admin.orders.cols.reference'))->fontFamily('mono')->placeholder('—')->searchable()
                    ->description(fn (PaymentError $record) => $record->payment_id)
                    ->url(fn (PaymentError $record) => $record->order_id && Order::query()->whereKey($record->order_id)->exists() ? OrderResource::getUrl('view', ['record' => $record->order_id]) : null),
                TextColumn::make('resolved_at')->label(__('admin.perr.cols.resolved'))->placeholder('—')->toggleable()
                    ->formatStateUsing(fn (PaymentError $record) => $record->resolved_at ? Formats::dateTime($record->resolved_at).' · '.($record->resolver?->name ?? '') : '—')
                    ->description(fn (PaymentError $record) => $record->resolved_note),
            ])
            ->filters([
                SelectFilter::make('error_source')->label(__('admin.perr.cols.source'))
                    ->options(collect(PaymentErrorSource::cases())->mapWithKeys(fn ($c) => [$c->value => __('enums.payment_error_source.'.$c->value)])->all()),
                SelectFilter::make('season')->label(__('admin.trx.filters.season'))->default('current')
                    ->options(['current' => __('common.current'), 'archive' => __('common.archive')])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $v === 'archive'
                        ? $q->whereHas('event', fn ($e) => $e->where('is_archived', true))
                        : $q->where(fn ($w) => $w->whereNull('event_id')->orWhereHas('event', fn ($e) => $e->where('is_archived', false))))),
                TernaryFilter::make('resolved')->label(__('admin.perr.cols.resolved'))->nullable()->attribute('resolved_at')->default(false),
            ])
            ->recordActions([
                Action::make('resolve')->label(__('admin.perr.resolve'))->icon(Heroicon::OutlinedCheckCircle)->color('success')->iconButton()
                    ->visible(fn (PaymentError $record) => ! $record->resolved_at && Actor::current()->can('payment_errors.manage'))
                    ->schema([Textarea::make('note')->label(__('admin.perr.note'))->rows(2)->maxLength(500)])
                    ->action(function (PaymentError $record, array $data) {
                        $record->forceFill(['resolved_at' => now(), 'resolved_by' => Actor::current()->userId(), 'resolved_note' => $data['note'] ?? null])->save();
                        Notification::make()->success()->title(__('admin.perr.resolved'))->send();
                    }),
                DeleteAction::make()->iconButton()
                    ->after(fn (PaymentError $record) => Audit::log(ActivityType::SettingsChanged, __('admin.perr.audit_deleted'), null, $record->only(['booking_reference', 'error_code', 'error_message']), Actor::current())),
            ])
            ->toolbarActions([
                BulkAction::make('resolve_many')->label(__('admin.perr.resolve'))->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn () => Actor::current()->can('payment_errors.manage'))
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        PaymentError::query()->whereIn('id', $records->pluck('id'))->whereNull('resolved_at')->update(['resolved_at' => now(), 'resolved_by' => Actor::current()->userId()]);
                        Notification::make()->success()->title(__('admin.perr.resolved'))->send();
                    })->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading(__('admin.perr.empty'))
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck);
    }

    public static function getPages(): array
    {
        return ['index' => ListPaymentErrors::route('/')];
    }
}
