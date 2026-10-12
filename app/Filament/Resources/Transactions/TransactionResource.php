<?php

namespace App\Filament\Resources\Transactions;

use App\Domain\Shared\Actor;
use App\Enums\PaymentStatus;
use App\Filament\Concerns\InNavigationGroup;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Jobs\SyncSadadTransaction;
use App\Models\Order;
use App\Support\Formats;
use App\Support\QatarClock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/** ADM-TRX — financial ledger per channel with stored Sadad fee / net (read-only). */
class TransactionResource extends Resource
{
    use InNavigationGroup;

    protected static ?string $model = Order::class;

    protected static ?string $slug = 'transactions';

    protected static string $navGroup = 'finance';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    public static function getModelLabel(): string
    {
        return __('admin.trx.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.trx.plural');
    }

    public static function canViewAny(): bool
    {
        return Actor::current()->can('transactions.view');
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
        return parent::getEloquentQuery()->whereHas('event')
            ->with(['customer', 'event:id,title,event_date,is_archived', 'holders:id,order_id,ticket_type', 'sadadTransaction', 'posUser:id,name,icon']);
    }

    public static function table(Table $table): Table
    {
        $sadadStatus = fn (?string $s) => $s ? __('enums.sadad_live.'.strtoupper(str_replace(' ', '_', $s))) : null;

        return $table
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(30)
            ->columns([
                TextColumn::make('payment_id')->label(__('admin.trx.cols.txn'))->fontFamily('mono')->placeholder('—')->searchable()->copyable(),
                TextColumn::make('booking_reference')->label(__('admin.orders.cols.reference'))->fontFamily('mono')->searchable()->copyable()
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record])),
                TextColumn::make('customer.name')->label(__('admin.orders.cols.customer'))->placeholder(__('common.unknown_customer'))
                    ->description(fn (Order $record) => $record->customer?->fullPhone()),
                TextColumn::make('event.title')->label(__('admin.orders.filters.event'))->limit(28),
                TextColumn::make('quantity')->label(__('admin.orders.cols.tickets'))->formatStateUsing(fn (Order $record) => OrdersTable::ticketSummary($record))->html(),
                TextColumn::make('total_amount')->label(__('admin.orders.cols.amount'))->alignEnd()->weight('bold')
                    ->formatStateUsing(fn ($state) => Formats::money($state, short: true))
                    ->summarize([
                        Sum::make()->label(__('admin.trx.confirmed_total'))->query(fn ($query) => $query->where('payment_status', 'confirmed'))->formatStateUsing(fn ($state) => Formats::money($state, short: true)),
                        Count::make()->label(__('admin.trx.confirmed_count'))->query(fn ($query) => $query->where('payment_status', 'confirmed')),
                    ]),
                TextColumn::make('payment_status')->label(__('admin.orders.cols.status'))->badge()
                    ->formatStateUsing(fn (Order $record) => __('admin.trx.status.'.$record->payment_status->value))
                    ->color(fn (Order $record) => match ($record->payment_status) { PaymentStatus::Confirmed => 'success', PaymentStatus::Pending => 'warning', default => 'danger' })
                    ->description(fn (Order $record) => $record->payment_error_reason ? \Illuminate\Support\Str::limit($record->payment_error_reason, 40) : null),
                TextColumn::make('sadadTransaction.status')->label(__('admin.trx.cols.sadad_status'))->badge()->placeholder('—')
                    ->formatStateUsing(fn ($state) => $sadadStatus($state))
                    ->color(fn ($state) => match (strtoupper((string) $state)) { 'SUCCESS' => 'success', 'FAILED', 'REJECTED' => 'danger', 'REFUND', 'REFUNDED' => 'info', default => 'warning' })
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? 'sadad') === 'sadad'),
                TextColumn::make('sadadTransaction.commission')->label(__('admin.trx.cols.fee'))->placeholder('—')->alignEnd()
                    ->state(fn (Order $record) => $record->sadadTransaction ? \App\Support\Money::of((string) ($record->sadadTransaction->commission ?? 0))->add(\App\Support\Money::of((string) ($record->sadadTransaction->refund_charge ?? 0)))->toDecimal() : null)
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : Formats::money($state, short: true))->color('warning')
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? 'sadad') === 'sadad'),
                TextColumn::make('sadadTransaction.net_amount')->label(__('admin.trx.cols.net'))->placeholder('—')->alignEnd()->weight('bold')->color('success')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : Formats::money($state, short: true))
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? 'sadad') === 'sadad'),
                TextColumn::make('created_at')->label(__('admin.trx.cols.date'))->sortable()->formatStateUsing(fn ($state) => Formats::dateTime($state)),
            ])
            ->filters([
                SelectFilter::make('season')->label(__('admin.trx.filters.season'))->default('current')
                    ->options(['current' => __('common.current'), 'archive' => __('common.archive')])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $q->whereHas('event', fn ($e) => $e->where('is_archived', $v === 'archive')))),
                SelectFilter::make('payment_status')->label(__('admin.orders.cols.status'))
                    ->options(['confirmed' => __('admin.trx.status.confirmed'), 'pending' => __('admin.trx.status.pending'), 'cancelled' => __('admin.trx.status.cancelled')]),
                SelectFilter::make('sadad_status')->label(__('admin.trx.cols.sadad_status'))
                    ->options(['success' => __('admin.trx.filters.sadad_success'), 'failed' => __('admin.trx.filters.sadad_failed'), 'progress' => __('admin.trx.filters.sadad_progress')])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $q->whereHas('sadadTransaction', fn ($t) => $t->whereIn('status', match ($v) {
                        'success' => ['SUCCESS'], 'failed' => ['FAILED', 'REJECTED'], default => ['INPROGRESS', 'IN PROGRESS', 'PENDING'],
                    })))),
                Filter::make('day')->label(__('admin.trx.filters.day'))
                    ->schema([DatePicker::make('day')->label(__('admin.trx.filters.day'))->native(false)->maxDate(now('Asia/Qatar'))])
                    ->query(fn (Builder $query, array $data) => $query->when($data['day'] ?? null, fn ($q, $d) => $q->whereBetween('created_at', QatarClock::dayRange(substr((string) $d, 0, 10)))))
                    ->indicateUsing(fn (array $data) => ($data['day'] ?? null) ? __('admin.trx.filters.day').': '.Formats::longDate(substr((string) $data['day'], 0, 10).' 12:00:00') : null),
            ])
            ->recordActions([
                Action::make('details')->label(__('admin.trx.details'))->icon(Heroicon::OutlinedEye)->iconButton()->color('gray')
                    ->modalHeading(__('admin.trx.details'))->modalWidth('2xl')->modalSubmitAction(false)
                    ->schema(fn (Order $record) => self::detailSchema($record)),
                Action::make('sync')->label(__('admin.trx.sync'))->icon(Heroicon::OutlinedArrowPath)->iconButton()->color('info')
                    ->visible(fn (Order $record) => $record->isSadad() && filled($record->payment_id))
                    ->action(function (Order $record) {
                        SyncSadadTransaction::dispatchSync($record->id);
                        $t = $record->fresh('sadadTransaction')->sadadTransaction;
                        $t && ! $t->last_error
                            ? Notification::make()->success()->title(__('admin.trx.synced'))->send()
                            : Notification::make()->danger()->title(__('admin.trx.sync_failed'))->body($t?->last_error)->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('sync_many')->label(__('admin.trx.sync_page'))->icon(Heroicon::OutlinedCreditCard)
                    ->action(function (Collection $records) {
                        $n = 0;
                        foreach ($records->filter(fn (Order $o) => $o->isSadad() && filled($o->payment_id)) as $o) {
                            SyncSadadTransaction::dispatch($o->id);
                            $n++;
                        }
                        Notification::make()->success()->title(__('admin.trx.queued', ['n' => $n]))->send();
                    })->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading(__('admin.trx.empty'));
    }

    /** F-ADM-TRX-015…018 details modal. */
    private static function detailSchema(Order $o): array
    {
        $t = $o->sadadTransaction;
        $others = Order::query()->where('customer_id', $o->customer_id)->whereKeyNot($o->id)->latest()->limit(10)->get(['id', 'booking_reference', 'total_amount', 'payment_status', 'created_at']);

        return [
            Section::make()->columns(2)->schema([
                TextEntry::make('a')->label(__('admin.trx.cols.txn'))->state($o->payment_id ?? '—')->fontFamily('mono'),
                TextEntry::make('b')->label(__('admin.orders.cols.reference'))->state($o->booking_reference)->fontFamily('mono')
                    ->url(route('invoice.show', $o->booking_reference), true),
                TextEntry::make('c')->label(__('admin.orders.cols.customer'))->state($o->customer?->name ?? __('common.unknown_customer')),
                TextEntry::make('d')->label(__('public.invoice.phone'))->state($o->customer?->fullPhone() ?? '—'),
                TextEntry::make('e')->label(__('public.checkout.email'))->state($o->customer?->email ?: '—'),
                TextEntry::make('f')->label(__('admin.orders.filters.event'))->state($o->event?->title ?? '—'),
                TextEntry::make('g')->label(__('admin.orders.cols.amount'))->state(Formats::money($o->total_amount, short: true)),
                TextEntry::make('h')->label(__('admin.orders.cols.status'))->badge()->state(__('admin.trx.status.'.$o->payment_status->value)),
                TextEntry::make('i')->label(__('admin.trx.cols.date'))->state(Formats::dateTime($o->created_at)),
                TextEntry::make('j')->label(__('admin.orders.cols.confirmed_at'))->state($o->confirmed_at ? Formats::dateTime($o->confirmed_at) : '—'),
                TextEntry::make('k')->label(__('admin.orders.cols.error'))->state($o->payment_error_reason)->color('danger')->columnSpanFull()->visible(filled($o->payment_error_reason)),
            ]),
            Section::make(__('admin.trx.sadad_block'))->columns(3)->visible($o->isSadad() && filled($o->payment_id))->schema($t ? [
                TextEntry::make('s1')->label(__('admin.trx.cols.sadad_status'))->badge()->state(($t->status ? __('enums.sadad_live.'.strtoupper(str_replace(' ', '_', $t->status))) : '—').($t->is_refund ? ' · '.__('admin.trx.refunded') : '')),
                TextEntry::make('s2')->label('website_ref_no')->state($t->website_ref_no ?? '—')->fontFamily('mono'),
                TextEntry::make('s3')->label(__('admin.trx.cols.gross'))->state(Formats::money($t->amount, short: true)),
                TextEntry::make('s4')->label(__('admin.trx.cols.commission'))->state(Formats::money($t->commission, short: true)),
                TextEntry::make('s5')->label(__('admin.trx.cols.refund_charge'))->state(Formats::money($t->refund_charge, short: true)),
                TextEntry::make('s6')->label(__('admin.trx.cols.net'))->state(Formats::money($t->net_amount, short: true))->weight('bold')->color('success'),
                TextEntry::make('s7')->label(__('admin.trx.cols.mode'))->state($t->mode ?? '—'),
                TextEntry::make('s8')->label(__('admin.trx.cols.entity'))->state($t->entity ?? '—'),
                TextEntry::make('s9')->label(__('admin.trx.cols.sadad_date'))->state($t->transaction_date ?? '—'),
                TextEntry::make('s10')->label(__('admin.trx.last_sync'))->state(Formats::dateTime($t->synced_at))->columnSpanFull()
                    ->helperText($t->last_error ? __('admin.trx.sync_failed').': '.$t->last_error : null),
            ] : [TextEntry::make('s0')->hiddenLabel()->state(__('admin.trx.not_synced'))]),
            Section::make(__('admin.trx.other_orders'))->visible($others->isNotEmpty())->schema([
                Grid::make(1)->schema($others->map(fn (Order $x) => TextEntry::make('o'.$x->id)->hiddenLabel()
                    ->state($x->booking_reference.' · '.Formats::dateTime($x->created_at).' · '.Formats::money($x->total_amount, short: true).' · '.__('admin.trx.status.'.$x->payment_status->value))
                    ->url(route('invoice.show', $x->booking_reference), true))->all()),
            ]),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => ListTransactions::route('/')];
    }
}
