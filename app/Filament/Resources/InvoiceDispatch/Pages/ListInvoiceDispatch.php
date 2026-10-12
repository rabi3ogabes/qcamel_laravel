<?php

namespace App\Filament\Resources\InvoiceDispatch\Pages;

use App\Domain\Messaging\InvoiceDispatcher;
use App\Domain\Shared\Actor;
use App\Filament\Resources\InvoiceDispatch\InvoiceDispatchResource as R;
use App\Models\Order;
use App\Settings\AutomationSettings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/** F-ADM-INV-007…009/014/017: tabs, scheduler switch + countdown, send-now, webhook warning. */
class ListInvoiceDispatch extends ListRecords
{
    protected static string $resource = R::class;

    public function getTabs(): array
    {
        $base = fn () => R::getEloquentQuery();
        $pending = fn (Builder $q) => R::onHold(R::eventDone(R::pendingMessage($q), false), false);
        $sent = fn (Builder $q) => $q->whereNotNull('n8n_response_message')->whereNotIn('n8n_response_message', [Order::SENDING_WHATSAPP, Order::SENDING_EMAIL])->whereNotNull('n8n_responded_at');
        $hold = fn (Builder $q) => R::eventDone(R::onHold($q), false);
        $done = fn (Builder $q) => R::eventDone($q->where(fn ($w) => R::pendingMessage($w)->orWhereNull('n8n_responded_at')));

        return [
            'pending' => Tab::make(__('admin.inv.tabs.pending'))->icon(Heroicon::OutlinedClock)->badge($pending($base())->count())->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $pending($query)),
            'sent' => Tab::make(__('admin.inv.tabs.sent'))->icon(Heroicon::OutlinedCheckCircle)->badge($sent($base())->count())->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $sent($query)),
            'on_hold' => Tab::make(__('admin.inv.tabs.on_hold'))->icon(Heroicon::OutlinedPauseCircle)->badge($hold($base())->count() ?: null)->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $hold($query)),
            'event_done' => Tab::make(__('admin.inv.tabs.event_done'))->icon(Heroicon::OutlinedCalendarDays)->badge($done($base())->count() ?: null)->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $done($query)),
            'all' => Tab::make(__('common.all')),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        $s = app(AutomationSettings::class);
        if (! $s->whatsappUrl()) {
            return new HtmlString('<span class="text-danger-600 font-bold">⚠️ '.e(filled($s->webhook_url) && ! $s->webhook_enabled ? __('admin.inv.webhook_disabled') : __('admin.inv.webhook_missing')).'</span>');
        }
        $next = app(InvoiceDispatcher::class)->secondsToNextRun();

        return $next === null ? __('admin.inv.auto_off') : __('admin.inv.auto_on', ['s' => $next, 'interval' => $s->auto_invoice_interval_seconds]);
    }

    protected function getHeaderActions(): array
    {
        $s = app(AutomationSettings::class);

        return [
            Action::make('toggle_auto')
                ->label($s->invoice_auto_send_enabled ? __('admin.inv.actions.stop_auto') : __('admin.inv.actions.start_auto'))
                ->icon($s->invoice_auto_send_enabled ? Heroicon::OutlinedPauseCircle : Heroicon::OutlinedPlayCircle)
                ->color($s->invoice_auto_send_enabled ? 'danger' : 'success')
                ->visible(fn () => Actor::current()->can('invoices.manage'))
                ->requiresConfirmation()
                ->action(function () {
                    $s = app(AutomationSettings::class);
                    $s->invoice_auto_send_enabled = ! $s->invoice_auto_send_enabled;
                    $s->save();
                    Notification::make()->success()->title($s->invoice_auto_send_enabled ? __('admin.inv.notify.auto_started') : __('admin.inv.notify.auto_stopped'))->send();
                }),
            Action::make('send_now')->label(__('admin.inv.actions.send_now'))->icon(Heroicon::OutlinedPaperAirplane)->color('primary')
                ->disabled(fn () => ! app(AutomationSettings::class)->whatsappUrl())
                ->requiresConfirmation()
                ->action(function () {
                    $res = app(InvoiceDispatcher::class)->run(true, Actor::current());
                    Notification::make()->success()->title(__('admin.inv.notify.batch', ['n' => $res['batch_size'] ?? 0]))->body($res['message'] ?? null)->send();
                }),
        ];
    }
}
