<?php

namespace App\Filament\Resources\BookedTickets;

use App\Domain\Booking\Actions\RecycleBinActions;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Domain\Tickets\CheckInService;
use App\Domain\Tickets\QrService;
use App\Filament\Concerns\InNavigationGroup;
use App\Filament\Resources\BookedTickets\Pages\ListBookedTickets;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\TicketHolder;
use App\Settings\FeatureFlagSettings;
use App\Support\Countries;
use App\Support\Formats;
use App\Support\QatarClock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/** F-ADM-TKT-015…026: booked tickets by event day with scan state, QR, details, recycle bin. */
class BookedTicketResource extends Resource
{
    use InNavigationGroup;

    protected static ?string $model = TicketHolder::class;

    protected static ?string $slug = 'booked-tickets';

    protected static string $navGroup = 'tickets';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function getModelLabel(): string
    {
        return __('admin.booked.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.booked.plural');
    }

    public static function canViewAny(): bool
    {
        return Actor::current()->can('tickets.view');
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
        return parent::getEloquentQuery()->whereHas('order')
            ->with(['order:id,booking_reference,payment_status,payment_method,source,total_amount,created_at,event_id,customer_id', 'order.event:id,title,event_date,end_date,location', 'order.customer:id,name,email,phone,country_code']);
    }

    public static function table(Table $table): Table
    {
        $payTone = fn (TicketHolder $h) => match ($h->order?->payment_status?->value) { 'confirmed' => 'success', 'pending' => 'warning', default => 'danger' };

        return $table
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(60)
            ->paginationPageOptions([30, 60, 120])
            ->poll('30s')
            ->columns([
                TextColumn::make('name')->label(__('admin.booked.cols.holder'))->searchable()->weight('bold')
                    ->formatStateUsing(fn (TicketHolder $record) => trim(Countries::flag($record->nationality, '').' '.$record->name))
                    ->description(fn (TicketHolder $record) => $record->displayPhone()),
                TextColumn::make('ticket_type')->label(__('admin.orders.cols.tickets'))->badge()
                    ->formatStateUsing(fn (TicketHolder $record) => $record->ticket_type->label())
                    ->color(fn (TicketHolder $record) => ['vip' => 'warning', 'normal' => 'primary', 'parking' => 'info'][$record->ticket_type->value]),
                TextColumn::make('order.booking_reference')->label(__('admin.orders.cols.reference'))->fontFamily('mono')->copyable()
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('order', fn ($o) => $o->where('booking_reference', 'like', \App\Support\Text::like($search))))
                    ->description(fn (TicketHolder $record) => $record->order?->event?->title)
                    ->url(fn (TicketHolder $record) => $record->order ? OrderResource::getUrl('view', ['record' => $record->order_id]) : null),
                TextColumn::make('order.source')->label(__('admin.orders.filters.source'))->badge()->color('gray')
                    ->formatStateUsing(fn (TicketHolder $record) => $record->order?->isPos() ? __('admin.booked.pos') : __('admin.booked.online'))
                    ->description(fn (TicketHolder $record) => __('admin.booked.sold_at', ['t' => Formats::dateTime($record->order?->created_at ?? $record->created_at)])),
                TextColumn::make('order.payment_status')->label(__('admin.booked.cols.payment'))->badge()->color($payTone)
                    ->formatStateUsing(fn (TicketHolder $record) => __('admin.trx.status.'.$record->order?->payment_status?->value)),
                TextColumn::make('is_present')->label(__('admin.booked.cols.scan'))->badge()
                    ->formatStateUsing(fn (TicketHolder $record) => $record->is_present ? '✓ '.__('admin.booked.scanned') : __('admin.booked.not_scanned'))
                    ->color(fn (TicketHolder $record) => $record->is_present ? 'success' : 'gray')
                    ->description(fn (TicketHolder $record) => $record->is_present ? Formats::dateTime($record->checked_in_at).($record->checked_in_by_name ? ' — '.$record->checked_in_by_name : '') : null),
                TextColumn::make('qr_code')->label('QR')->fontFamily('mono')->size('xs')->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('day')->label(__('admin.booked.filters.day'))->default(['mode' => 'today'])
                    ->schema([
                        Select::make('mode')->label(__('admin.booked.filters.day'))->native(false)->live()
                            ->options(['today' => __('common.today'), 'tomorrow' => __('common.tomorrow'), 'date' => __('common.specific_day'), 'all' => __('common.all_days')]),
                        DatePicker::make('date')->label(__('common.specific_day'))->native(false)->visible(fn ($get) => $get('mode') === 'date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $mode = $data['mode'] ?? 'today';
                        $day = match ($mode) {
                            'today' => QatarClock::today(),
                            'tomorrow' => QatarClock::now()->addDay()->format('Y-m-d'),
                            'date' => isset($data['date']) ? substr((string) $data['date'], 0, 10) : null,
                            default => null,
                        };

                        return $day ? $query->whereHas('order.event', fn ($e) => $e->onQatarDate($day)) : $query;
                    })
                    ->indicateUsing(fn (array $data) => match ($data['mode'] ?? 'today') {
                        'today' => __('common.today'), 'tomorrow' => __('common.tomorrow'),
                        'date' => isset($data['date']) ? Formats::longDate(substr((string) $data['date'], 0, 10).' 12:00:00') : null, default => null,
                    }),
                SelectFilter::make('scan')->label(__('admin.booked.cols.scan'))
                    ->options(['yes' => __('admin.booked.scanned'), 'no' => __('admin.booked.not_scanned')])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $q->where('is_present', $v === 'yes'))),
                SelectFilter::make('payment')->label(__('admin.booked.cols.payment'))->default('confirmed')
                    ->options(['confirmed' => __('admin.trx.status.confirmed'), 'pending' => __('admin.trx.status.pending'), 'cancelled' => __('admin.trx.status.cancelled')])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $q->whereHas('order', fn ($o) => $o->where('payment_status', $v)))),
                SelectFilter::make('ticket_type')->label(__('admin.orders.cols.tickets'))
                    ->options(['vip' => __('enums.ticket_kind.vip'), 'normal' => __('enums.ticket_kind.normal'), 'parking' => __('enums.ticket_kind.parking')]),
            ])
            ->recordActions([
                Action::make('qr')->label('QR')->icon(Heroicon::OutlinedQrCode)->iconButton()->color('gray')
                    ->visible(fn (TicketHolder $record) => ! $record->is_present && filled($record->qr_code) && $record->order?->payment_status?->value === 'confirmed')
                    ->modalHeading(fn (TicketHolder $record) => $record->ticket_type->label().' — '.$record->name)
                    ->modalDescription(fn (TicketHolder $record) => $record->code())
                    ->modalWidth('md')->modalSubmitAction(false)
                    ->schema(fn (TicketHolder $record) => [TextEntry::make('qr')->hiddenLabel()->alignCenter()
                        ->state(new HtmlString('<div class="mx-auto w-72 rounded-2xl bg-white p-3 ring-1 ring-gray-200 [&>svg]:h-full [&>svg]:w-full">'.app(QrService::class)->svg(CheckInService::signedPayload($record->code()), 576, 'H', 1).'</div><p class="mt-3 text-center text-sm text-gray-500">'.e(__('admin.booked.aim')).'</p>'))->html()]),
                ActionGroup::make([
                    Action::make('details')->label(__('admin.booked.details'))->icon(Heroicon::OutlinedInformationCircle)
                        ->modalHeading(__('admin.booked.details'))->modalDescription(fn (TicketHolder $record) => $record->order?->booking_reference)->modalSubmitAction(false)
                        ->schema(fn (TicketHolder $record) => [Grid::make(2)->schema(self::details($record))]),
                    Action::make('tickets')->label(__('admin.orders.actions.tickets'))->icon(Heroicon::OutlinedPrinter)
                        ->url(fn (TicketHolder $record) => route('staff.tickets', $record->order?->booking_reference ?? '-'))->openUrlInNewTab(),
                    Action::make('unmark')->label(__('ops.scanner.undo'))->icon(Heroicon::OutlinedArrowUturnLeft)->color('warning')
                        ->visible(fn (TicketHolder $record) => $record->is_present && Actor::current()->can('checkin.reset'))
                        ->requiresConfirmation()
                        ->action(function (TicketHolder $record) {
                            $res = app(CheckInService::class)->reset($record->id, Actor::current(), 'admin booked tickets');
                            Notification::make()->{($res['success'] ?? false) ? 'success' : 'danger'}()->title($res['message'] ?? '')->send();
                        }),
                    Action::make('bin')->label(__('admin.booked.bin'))->icon(Heroicon::OutlinedTrash)->color('danger')
                        ->visible(fn () => Actor::current()->can('tickets.bin') && rescue(fn () => app(FeatureFlagSettings::class)->show_delete_ticket_button, true, false))
                        ->requiresConfirmation()
                        ->modalDescription(fn (TicketHolder $record) => __('admin.booked.bin_confirm', ['name' => $record->name]))
                        ->action(function (TicketHolder $record) {
                            try {
                                app(RecycleBinActions::class)->bin($record, Actor::current());
                                Notification::make()->success()->title(__('tickets.binned'))->send();
                            } catch (DomainError $e) {
                                Notification::make()->danger()->title($e->userMessage())->send();
                            }
                        }),
                ])->icon(Heroicon::OutlinedEllipsisVertical),
            ])
            ->emptyStateHeading(__('admin.booked.empty'));
    }

    /** F-ADM-TKT-022 details tiles. */
    private static function details(TicketHolder $h): array
    {
        $o = $h->order;
        $tile = fn (string $k, string $label, ?string $v) => TextEntry::make($k)->label($label)->state($v ?: '—');

        return [
            $tile('a', __('admin.booked.cols.holder'), $h->name),
            $tile('b', __('public.invoice.phone'), $h->displayPhone()),
            $tile('c', __('admin.orders.cols.tickets'), $h->ticket_type->longLabel()),
            $tile('d', __('admin.booked.cols.scan'), $h->is_present ? '✓ '.__('common.present') : __('admin.booked.not_scanned')),
            $tile('e', __('admin.booked.scan_time'), $h->checked_in_at ? Formats::longDateTime($h->checked_in_at) : null),
            $tile('f', __('admin.booked.scanned_by'), $h->checked_in_by_name),
            $tile('g', __('admin.orders.cols.customer'), $o?->customer?->name),
            $tile('h', __('public.checkout.email'), $o?->customer?->email),
            $tile('i', __('admin.orders.filters.event'), $o?->event?->title),
            $tile('j', __('public.invoice.date'), Formats::longDate($o?->event?->event_date)),
            $tile('k', __('public.home.location'), $o?->event?->location),
            $tile('l', __('admin.orders.cols.method'), $o ? __('enums.payment_method.'.$o->payment_method->value) : null),
            $tile('m', __('admin.booked.order_total'), $o ? Formats::money($o->total_amount, short: true) : null),
            $tile('n', __('admin.orders.cols.booked_at'), $o ? Formats::dateTime($o->created_at) : null),
            $tile('p', __('admin.booked.cols.payment'), $o ? __('admin.trx.status.'.$o->payment_status->value) : null),
            $tile('q', __('admin.booked.price_paid'), $h->unit_price !== null ? Formats::money($h->unit_price, short: true) : null),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => ListBookedTickets::route('/')];
    }
}
