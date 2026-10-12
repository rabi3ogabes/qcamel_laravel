<?php

namespace App\Filament\Resources\InvoiceDispatch;

use App\Domain\Messaging\AutomationRelay;
use App\Domain\Messaging\InvoiceDispatcher;
use App\Domain\Shared\Actor;
use App\Enums\ActivityType;
use App\Filament\Concerns\InNavigationGroup;
use App\Filament\Resources\InvoiceDispatch\Pages\ListInvoiceDispatch;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Jobs\SendOrderMail;
use App\Models\Order;
use App\Support\Audit;
use App\Support\Countries;
use App\Support\Formats;
use App\Support\QatarClock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * ADM-INV — invoice sending console. Monitors and controls the server-side
 * `invoices:dispatch` scheduler (lock, delays, attempts, on-hold) instead of
 * running a scheduler in the admin's browser tab.
 */
class InvoiceDispatchResource extends Resource
{
    use InNavigationGroup;

    protected static ?string $model = Order::class;

    protected static ?string $slug = 'invoices';

    protected static string $navGroup = 'finance';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    public static function getModelLabel(): string
    {
        return __('admin.inv.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.inv.plural');
    }

    public static function canViewAny(): bool
    {
        return Actor::current()->can('invoices.manage');
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

    public static function maxAttempts(): int
    {
        return (int) config('qcamelmc.notifications.max_attempts', 2);
    }

    // ---- SQL state predicates (F-ADM-INV-004…007) -------------------------------------

    public static function pendingMessage(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('n8n_response_message')->orWhereIn('n8n_response_message', [Order::SENDING_WHATSAPP, Order::SENDING_EMAIL]));
    }

    public static function eventDone(Builder $q, bool $done = true): Builder
    {
        $today = QatarClock::today();
        [$todayStart, $todayEnd] = QatarClock::dayRange($today);
        $nowTime = QatarClock::now()->format('H:i:s');
        $cond = fn ($e) => $e->where(function ($w) use ($todayStart, $todayEnd, $nowTime) {
            $w->whereRaw('COALESCE(end_date, event_date) < ?', [$todayStart])
                ->orWhere(fn ($x) => $x->whereRaw('COALESCE(end_date, event_date) <= ?', [$todayEnd])->where('sales_cutoff_time', '<=', $nowTime));
        });

        return $done ? $q->whereHas('event', $cond) : $q->whereDoesntHave('event', $cond);
    }

    public static function onHold(Builder $q, bool $hold = true): Builder
    {
        $fn = fn ($w) => $w->where('send_attempt_count', '>=', self::maxAttempts())
            ->where(fn ($x) => self::pendingMessage($x)->orWhereNull('n8n_responded_at'));

        return $hold ? $q->where($fn) : $q->whereNot($fn);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('payment_method', 'sadad')->where('payment_status', 'confirmed')
            ->with(['customer', 'event:id,title,event_date,end_date,sales_cutoff_time,is_archived', 'holders:id,order_id,ticket_type'])
            ->withCount('emailDeliveryEvents');
    }

    public static function state(Order $o): array
    {
        $msg = (string) $o->n8n_response_message;
        if ($msg !== '' && ! $o->hasPendingAutomationMessage()) {
            $email = (bool) preg_match('/اميل|إيميل|بريد|email/iu', $msg);

            return [$email ? '📧 '.__('admin.inv.state.sent_email') : '💬 '.__('admin.inv.state.sent_whatsapp'), 'success'];
        }
        if ($msg === Order::SENDING_EMAIL) {
            return ['📧 '.__('admin.inv.state.awaiting_email'), 'info'];
        }
        if ($msg === Order::SENDING_WHATSAPP) {
            return ['💬 '.__('admin.inv.state.awaiting_whatsapp'), 'info'];
        }
        if (InvoiceDispatcher::eventDone($o)) {
            return [__('admin.inv.state.event_done'), 'warning'];
        }
        if ($o->isInvoiceOnHold(self::maxAttempts())) {
            return [__('admin.inv.state.on_hold', ['n' => $o->send_attempt_count]), 'danger'];
        }

        return [__('admin.inv.state.not_sent'), 'gray'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->poll('20s')
            ->columns([
                TextColumn::make('n8n_response_message')->label(__('admin.orders.cols.status'))->badge()
                    ->state(fn (Order $record) => self::state($record)[0])->color(fn (Order $record) => self::state($record)[1])
                    ->description(fn (Order $record) => $record->n8n_responded_at ? Formats::dateTime($record->n8n_responded_at) : null),
                TextColumn::make('booking_reference')->label(__('admin.orders.cols.reference'))->fontFamily('mono')->searchable()->copyable()
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record])),
                TextColumn::make('customer.name')->label(__('admin.orders.cols.customer'))
                    ->formatStateUsing(fn (Order $record) => trim(Countries::flag($record->customer?->nationality).' '.($record->customer?->name ?? __('common.unknown'))))
                    ->description(fn (Order $record) => $record->customer?->fullPhone())
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('customer', fn ($c) => $c->where('name', 'like', \App\Support\Text::like($search))->orWhere('phone', 'like', \App\Support\Text::like($search)))),
                TextColumn::make('customer.email')->label(__('public.checkout.email'))->placeholder('—')->limit(24)->toggleable(),
                TextColumn::make('quantity')->label(__('admin.orders.cols.tickets'))->formatStateUsing(fn (Order $record) => OrdersTable::ticketSummary($record))->html(),
                TextColumn::make('total_amount')->label(__('admin.orders.cols.amount'))->formatStateUsing(fn ($state) => Formats::money($state, short: true))->alignEnd(),
                TextColumn::make('event.title')->label(__('admin.orders.filters.event'))->placeholder(__('common.not_available'))->limit(22)
                    ->description(fn (Order $record) => Formats::date($record->event?->event_date)),
                TextColumn::make('send_attempt_count')->label(__('admin.inv.cols.attempts'))->alignCenter()->badge()->color(fn ($state) => $state >= self::maxAttempts() ? 'danger' : 'gray'),
                TextColumn::make('created_at')->label(__('admin.orders.cols.booked_at'))->formatStateUsing(fn ($state) => Formats::dateTime($state))->toggleable(),
            ])
            ->filters([
                SelectFilter::make('season')->label(__('admin.trx.filters.season'))->default('current')
                    ->options(['current' => __('common.current'), 'archive' => __('common.archive')])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $q->whereHas('event', fn ($e) => $e->withTrashed()->where('is_archived', $v === 'archive')))),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('send')->label(__('admin.inv.actions.send'))->icon(Heroicon::OutlinedPaperAirplane)->color('success')
                        ->visible(fn (Order $record) => $record->n8n_response_message === null && ! $record->isInvoiceOnHold(self::maxAttempts()) && ! InvoiceDispatcher::eventDone($record))
                        ->action(function (Order $record) {
                            $res = rescue(fn () => app(AutomationRelay::class)->send('send_invoice', $record, null, Actor::current(), $record->quantity), ['success' => false], false);
                            if ($res['success'] ?? false) {
                                Notification::make()->success()->title(__('admin.inv.notify.sent', ['name' => $record->customer?->name]))->send();
                            } else {
                                $record->increment('send_attempt_count');
                                Notification::make()->danger()->title(__('admin.inv.notify.failed', ['n' => $record->send_attempt_count]))->body($res['message'] ?? $res['error'] ?? null)->send();
                            }
                        }),
                    Action::make('email')->label(__('admin.orders.actions.email'))->icon(Heroicon::OutlinedEnvelope)
                        ->visible(fn (Order $record) => filled($record->customer?->email))
                        ->requiresConfirmation()
                        ->action(function (Order $record) {
                            SendOrderMail::dispatch('invoice', $record->id, true);
                            Notification::make()->success()->title(__('admin.inv.notify.email_queued'))->send();
                        }),
                    Action::make('reset')->label(__('admin.inv.actions.reset'))->icon(Heroicon::OutlinedArrowUturnLeft)->color('warning')
                        ->visible(fn (Order $record) => $record->n8n_response_message !== null)
                        ->requiresConfirmation()->modalDescription(__('admin.inv.reset_warning'))
                        ->action(function (Order $record) {
                            $record->forceFill(['n8n_response_message' => null, 'n8n_responded_at' => null, 'automation_status' => 'none'])->save();
                            Audit::log(ActivityType::InvoiceDispatch, __('admin.inv.audit_reset', ['ref' => $record->booking_reference]), $record, [], Actor::current());
                            Notification::make()->success()->title(__('admin.inv.notify.reset'))->send();
                        }),
                    Action::make('reset_attempts')->label(__('admin.inv.actions.reset_attempts'))->icon(Heroicon::OutlinedArrowPath)->color('danger')
                        ->visible(fn (Order $record) => $record->isInvoiceOnHold(self::maxAttempts()))
                        ->action(function (Order $record) {
                            $record->forceFill(['send_attempt_count' => 0])->save();
                            Notification::make()->success()->title(__('admin.inv.notify.attempts_reset'))->send();
                        }),
                    Action::make('email_log')->label(__('admin.inv.actions.email_log'))->icon(Heroicon::OutlinedClock)->color('gray')
                        ->modalHeading(fn (Order $record) => __('admin.inv.email_log_title').' — '.$record->booking_reference)
                        ->modalDescription(fn (Order $record) => $record->customer?->email ?: __('admin.inv.no_email'))
                        ->modalSubmitAction(false)
                        ->schema(fn (Order $record) => [TextEntry::make('log')->hiddenLabel()->state(self::emailTimeline($record))->html()]),
                ])->icon(Heroicon::OutlinedEllipsisVertical),
            ])
            ->toolbarActions([
                BulkAction::make('mark_sent')->label(__('admin.inv.actions.mark_sent'))->icon(Heroicon::OutlinedCheckCircle)
                    ->requiresConfirmation()->modalDescription(fn (Collection $records) => __('admin.inv.mark_sent_confirm', ['n' => $records->count()]))
                    ->action(function (Collection $records) {
                        $ids = $records->filter(fn (Order $o) => $o->n8n_response_message === null)->pluck('id')->all();
                        Order::query()->whereIn('id', $ids)->update(['n8n_response_message' => Order::MARKED_SENT_MARKER, 'n8n_responded_at' => now(), 'automation_status' => 'sent']);
                        Audit::log(ActivityType::InvoiceDispatch, __('admin.inv.audit_marked', ['n' => count($ids)]), null, ['ids' => $ids], Actor::current());
                        Notification::make()->success()->title(__('admin.inv.notify.marked', ['n' => count($ids)]))->send();
                    })->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading(__('admin.inv.empty'));
    }

    private static function emailTimeline(Order $o): HtmlString
    {
        $rows = $o->emailDeliveryEvents()->latest('created_at')->limit(40)->get();
        if ($rows->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-500">'.e(__('admin.inv.email_log_empty')).'</p>');
        }
        $tone = ['sent' => 'text-success-600', 'failed' => 'text-danger-600', 'bounced' => 'text-danger-600', 'complained' => 'text-danger-600', 'queued' => 'text-warning-600', 'retried' => 'text-info-600', 'suppressed' => 'text-warning-700'];
        $html = '<ol class="space-y-3 border-s-2 border-gray-200 ps-4 dark:border-white/10">';
        foreach ($rows as $r) {
            $s = is_object($r->status) ? $r->status->value : (string) $r->status;
            $html .= '<li><p class="text-sm font-bold '.($tone[$s] ?? 'text-gray-500').'">'.e(__('enums.email_status.'.$s) !== 'enums.email_status.'.$s ? __('enums.email_status.'.$s) : $s).'</p>'
                .'<p class="text-xs text-gray-500">'.e(Formats::dateTime($r->created_at)).($r->attempt > 1 ? ' • '.e(__('admin.inv.attempt', ['n' => $r->attempt])) : '').'</p>'
                .($r->detail ? '<p class="text-xs">'.e($r->detail).'</p>' : '')
                .'<p class="text-xs text-gray-400" dir="ltr">'.e((string) $r->recipient).'</p></li>';
        }

        return new HtmlString($html.'</ol>');
    }

    public static function getPages(): array
    {
        return ['index' => ListInvoiceDispatch::route('/')];
    }
}
