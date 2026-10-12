<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Domain\Payments\PaymentCheckThrottle;
use App\Domain\Payments\PaymentProcessor;
use App\Domain\Shared\Actor;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exports\OrdersExport;
use App\Filament\Support\OrderActions;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Models\Event;
use App\Models\Order;
use App\Support\Countries;
use App\Support\Formats;
use App\Support\PhoneNumber;
use App\Support\QatarClock;
use App\Support\Text;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;

/** F-ADM-ORD-002…037 as a Filament table: one server query, escaped search, 30 per page. */
class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(30)
            ->paginationPageOptions([30, 60, 100])
            ->poll('30s')
            ->striped()
            ->recordClasses(fn (Order $record) => $record->sadad_manually_verified ? 'ring-1 ring-inset ring-success-500/40' : null)
            ->columns([
                TextColumn::make('booking_reference')->label(__('admin.orders.cols.reference'))
                    ->searchable()->copyable()->copyMessage(__('ops.live.copied_ref'))->fontFamily('mono')->weight('bold')->color('primary')
                    ->description(fn (Order $record) => trim(($record->event?->title ?? '—').' · '.Formats::date($record->event?->event_date)))
                    ->badge(fn (Order $record) => $record->event_changes_count > 0)->tooltip(fn (Order $record) => $record->event_changes_count > 0 ? __('admin.orders.moved_tooltip') : null),
                TextColumn::make('payment_status')->label(__('admin.orders.cols.status'))->badge()
                    ->formatStateUsing(fn (Order $record) => __('enums.public_payment_status.'.$record->publicStatus()))
                    ->color(fn (Order $record) => match ($record->publicStatus()) {
                        'confirmed' => 'success', 'pending' => 'warning', default => 'danger'
                    })
                    ->description(fn (Order $record) => $record->noteLabel()),
                TextColumn::make('customer.name')->label(__('admin.orders.cols.customer'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('customer', function ($c) use ($search) {
                        $like = Text::like($search);
                        $digits = PhoneNumber::digits($search);
                        $c->where('name', 'like', $like)->orWhere('phone', 'like', $like);
                        if (strlen($digits) >= 4) {
                            $c->orWhere('phone_e164', 'like', '%'.$digits.'%');
                        }
                    }))
                    ->formatStateUsing(fn (Order $record) => trim(Countries::flag($record->customer?->nationality, '').' '.($record->customer?->name ?? __('common.unknown_customer'))))
                    ->description(fn (Order $record) => $record->customer ? $record->customer->fullPhone() : __('common.not_available')),
                TextColumn::make('quantity')->label(__('admin.orders.cols.tickets'))
                    ->formatStateUsing(fn (Order $record) => self::ticketSummary($record))->html(),
                TextColumn::make('total_amount')->label(__('admin.orders.cols.amount'))->sortable()->alignEnd()
                    ->formatStateUsing(fn ($state) => Formats::money($state, short: true))->weight('bold'),
                TextColumn::make('payment_method')->label(__('admin.orders.cols.method'))->badge()
                    ->formatStateUsing(fn (Order $record) => $record->posUser ? trim(($record->posUser->icon ?: '👤').' '.$record->posUser->name) : __('enums.payment_method_short.'.$record->payment_method->value))
                    ->color(fn (Order $record) => $record->payment_method === PaymentMethod::Sadad ? 'info' : 'success')
                    ->icon(fn (Order $record) => $record->payment_method === PaymentMethod::Sadad ? Heroicon::OutlinedCreditCard : Heroicon::OutlinedBanknotes),
                IconColumn::make('sadad_manually_verified')->label(__('admin.orders.cols.verified'))->boolean()->alignCenter()->toggleable(),
                TextColumn::make('created_at')->label(__('admin.orders.cols.booked_at'))->sortable()
                    ->formatStateUsing(fn ($state) => Formats::dateTime($state))->toggleable(),
                TextColumn::make('payment_id')->label(__('admin.orders.cols.payment_id'))->fontFamily('mono')->toggleable(isToggledHiddenByDefault: true)->searchable(),
            ])
            ->filters([
                SelectFilter::make('payment_method')->label(__('admin.orders.cols.method'))
                    ->options(['sadad' => __('enums.payment_method_short.sadad'), 'cash_pos' => __('enums.payment_method_short.cash_pos')]),
                SelectFilter::make('source')->label(__('admin.orders.filters.source'))
                    ->options(['web' => __('enums.order_source.web'), 'pos' => __('enums.order_source.pos')]),
                SelectFilter::make('event_id')->label(__('admin.orders.filters.event'))->searchable()
                    ->options(fn () => Event::query()->withTrashed()->orderByDesc('event_date')->get()
                        ->mapWithKeys(fn (Event $e) => [$e->id => Formats::date($e->event_date).' — '.$e->title.($e->is_archived ? ' ('.__('common.archive').')' : '')])->all()),
                Filter::make('upcoming')->label(__('admin.orders.filters.upcoming'))->toggle()->default()
                    ->query(fn (Builder $query) => $query->whereHas('event', fn ($e) => $e->where(fn ($w) => $w->where('event_date', '>=', QatarClock::dayRange(QatarClock::today())[0])->orWhere('end_date', '>=', QatarClock::dayRange(QatarClock::today())[0])))),
                TernaryFilter::make('sadad_manually_verified')->label(__('admin.orders.cols.verified')),
                Filter::make('refunds')->label(__('admin.orders.filters.refunds'))->toggle()
                    ->query(fn (Builder $query) => $query->whereIn('payment_note', ['paid_after_expiry_no_stock', 'paid_after_staff_cancel'])->whereNull('refund_handled_at')),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                ActionGroup::make(OrderActions::all())->icon(Heroicon::OutlinedEllipsisVertical)->tooltip(__('admin.common.actions')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_verify')->label(__('admin.orders.actions.verify'))->icon(Heroicon::OutlinedShieldCheck)
                        ->visible(fn () => Actor::current()->can('orders.verify'))
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $confirmed = 0;
                            foreach ($records->filter(fn (Order $o) => $o->isSadad() && $o->payment_status !== PaymentStatus::Confirmed && ! $o->isFinalCancellation())->take(25) as $o) {
                                $res = rescue(fn () => PaymentController::verifyReference($o->booking_reference, Actor::current(), app(PaymentProcessor::class), app(PaymentCheckThrottle::class)), [], false);
                                $confirmed += ($res['payment_status'] ?? null) === 'confirmed' ? 1 : 0;
                            }
                            Notification::make()->success()->title(__('admin.orders.notify.bulk_verified', ['n' => $confirmed]))->send();
                        })->deselectRecordsAfterCompletion(),
                    BulkAction::make('bulk_export')->label(__('admin.common.export_xlsx'))->icon(Heroicon::OutlinedArrowDownTray)
                        ->visible(fn () => Actor::current()->can('orders.export'))
                        ->action(fn (Collection $records) => Excel::download(new OrdersExport($records->pluck('id')->all()), 'orders-'.QatarClock::today().'.xlsx')),
                ]),
            ])
            ->emptyStateHeading(__('admin.orders.empty'))
            ->emptyStateIcon(Heroicon::OutlinedTicket);
    }

    /** F-ADM-ORD-020: up to 8 type glyphs + "label × n" summary. */
    public static function ticketSummary(Order $record): HtmlString
    {
        $types = $record->holders->pluck('ticket_type')->map(fn ($t) => $t->value)->all();
        if ($types === []) {
            $types = array_fill(0, (int) $record->quantity, $record->ticket_type->value);
        }
        $icons = ['vip' => '👑', 'normal' => '🎫', 'parking' => '🚗'];
        $strip = implode('', array_map(fn ($t) => $icons[$t] ?? '🎫', array_slice($types, 0, 8)));
        $parts = collect($types)->countBy()->map(fn ($n, $t) => e(__('enums.ticket_kind.'.$t)).' × '.$n)->implode(' • ');

        return new HtmlString('<div class="text-base leading-none tracking-tight">'.$strip.'</div><div class="mt-1 text-xs text-gray-500">'.$parts.'</div>');
    }
}
