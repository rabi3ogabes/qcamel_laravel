<?php

namespace App\Filament\Support;

use App\Domain\Booking\Actions\ChangeOrderEventAction;
use App\Domain\Booking\Actions\OrderStatusActions;
use App\Domain\Messaging\AutomationRelay;
use App\Domain\Payments\PaymentCheckThrottle;
use App\Domain\Payments\PaymentProcessor;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Domain\Tickets\QrService;
use App\Enums\PaymentNote;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Models\Event;
use App\Models\Order;
use App\Settings\FeatureFlagSettings;
use App\Support\Formats;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * ADM-ORD actions (F-ADM-ORD-021…035) shared by the orders table and the
 * order page. Every mutation goes through the domain actions (policies,
 * one transaction, stock ledger, audit) — never direct model updates.
 */
final class OrderActions
{
    private static function can(string $permission): bool
    {
        return Actor::current()->can($permission);
    }

    private static function fail(Throwable $e): void
    {
        if (! $e instanceof DomainError) {
            report($e);
        }
        Notification::make()->danger()->title(__('common.error'))
            ->body($e instanceof DomainError ? $e->userMessage() : __('errors.default'))->send();
    }

    /** @return list<Action> */
    public static function all(): array
    {
        return [
            self::tickets(), self::verify(), self::confirm(), self::cancel(), self::moveEvent(),
            self::toggleVerified(), self::refundHandled(), self::sendWhatsApp(), self::sendEmail(),
            self::regenerateQr(), self::delete(),
        ];
    }

    public static function tickets(): Action
    {
        return Action::make('tickets')->label(__('admin.orders.actions.tickets'))->icon(Heroicon::OutlinedPrinter)->color('gray')
            ->url(fn (Order $record) => route('staff.tickets', $record->booking_reference))->openUrlInNewTab();
    }

    public static function verify(): Action
    {
        return Action::make('verify')->label(__('admin.orders.actions.verify'))->icon(Heroicon::OutlinedShieldCheck)->color('info')
            ->visible(fn (Order $record) => self::can('orders.verify') && $record->isSadad() && $record->payment_status !== PaymentStatus::Confirmed && ! $record->isFinalCancellation())
            ->action(function (Order $record) {
                try {
                    $res = PaymentController::verifyReference($record->booking_reference, Actor::current(), app(PaymentProcessor::class), app(PaymentCheckThrottle::class));
                    ($res['payment_status'] ?? null) === 'confirmed'
                        ? Notification::make()->success()->title(__('admin.orders.notify.verified_confirmed'))->send()
                        : Notification::make()->info()->title(__('admin.orders.notify.verified_none'))->send();
                } catch (Throwable $e) {
                    self::fail($e);
                }
            });
    }

    public static function confirm(): Action
    {
        return Action::make('confirm')
            ->label(fn (Order $record) => $record->payment_status === PaymentStatus::Cancelled ? __('admin.orders.actions.reactivate') : __('admin.orders.actions.confirm'))
            ->icon(Heroicon::OutlinedCheckCircle)->color('success')
            ->visible(fn (Order $record) => self::can('orders.confirm') && $record->payment_status !== PaymentStatus::Confirmed)
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record) => __('admin.orders.confirm.heading', ['ref' => $record->booking_reference]))
            ->modalDescription(fn (Order $record) => $record->isSadad() ? __('admin.orders.confirm.sadad_warning') : __('admin.orders.confirm.description'))
            ->schema(fn (Order $record) => [
                TextInput::make('reference')->label($record->isSadad() ? __('admin.orders.confirm.reference') : __('admin.orders.confirm.reason'))
                    ->required($record->isSadad())->maxLength(120),
                Checkbox::make('notify')->label(__('admin.orders.confirm.notify'))->default(true),
            ])
            ->action(function (Order $record, array $data) {
                try {
                    app(OrderStatusActions::class)->manualConfirm($record, $data['reference'] ?? null, Actor::current(), (bool) ($data['notify'] ?? true));
                    Notification::make()->success()->title(__('admin.orders.notify.confirmed'))->send();
                } catch (Throwable $e) {
                    self::fail($e);
                }
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label(fn (Order $record) => $record->payment_status === PaymentStatus::Pending ? __('admin.orders.actions.release') : __('admin.orders.actions.cancel'))
            ->icon(Heroicon::OutlinedXCircle)->color('danger')
            ->visible(fn (Order $record) => self::can('orders.cancel') && $record->payment_status->isActive())
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record) => __('admin.orders.cancel.heading', ['ref' => $record->booking_reference]))
            ->modalDescription(__('admin.orders.cancel.description'))
            ->schema(fn (Order $record) => array_filter([
                Textarea::make('note')->label(__('admin.orders.cancel.note'))->rows(2)->maxLength(255),
                $record->isSadad() && $record->payment_status === PaymentStatus::Confirmed
                    ? Checkbox::make('refund')->label(__('admin.orders.cancel.refund'))->default(true) : null,
            ]))
            ->action(function (Order $record, array $data) {
                try {
                    $res = app(OrderStatusActions::class)->cancel($record, $data['note'] ?? null, Actor::current(), (bool) ($data['refund'] ?? false));
                    $res === 'cancelled'
                        ? Notification::make()->success()->title(__('admin.orders.notify.cancelled'))->send()
                        : Notification::make()->info()->title(__('errors.already_inactive'))->send();
                } catch (Throwable $e) {
                    self::fail($e);
                }
            });
    }

    public static function moveEvent(): Action
    {
        return Action::make('move')->label(__('admin.orders.actions.move'))->icon(Heroicon::OutlinedCalendarDays)->color('warning')
            ->visible(fn (Order $record) => self::can('orders.change_event') && $record->event_id !== null)
            ->modalHeading(fn (Order $record) => __('admin.orders.move.heading', ['ref' => $record->booking_reference]))
            ->modalDescription(fn (Order $record) => __('admin.orders.move.description', ['from' => ($record->event?->title ?? '—').' · '.Formats::date($record->event?->event_date)]))
            ->schema(fn (Order $record) => [
                Select::make('event_id')->label(__('admin.orders.move.target'))->required()->native(false)->searchable()
                    ->options(Event::query()->currentSeason()->where('is_active', true)->whereKeyNot($record->event_id)->orderBy('event_date')->get()
                        ->filter(fn (Event $e) => ! $e->isExpired('checkin'))
                        ->mapWithKeys(fn (Event $e) => [$e->id => Formats::date($e->event_date).' — '.$e->title])->all()),
            ])
            ->action(function (Order $record, array $data) {
                try {
                    $res = app(ChangeOrderEventAction::class)->execute($record, $data['event_id'], Actor::current());
                    Notification::make()->success()->title(__('admin.orders.notify.moved', ['n' => $res['updated_ticket_holders'] ?? $res['new_codes'] ?? 0]))->send();
                } catch (Throwable $e) {
                    self::fail($e);
                }
            });
    }

    public static function toggleVerified(): Action
    {
        return Action::make('verified')
            ->label(fn (Order $record) => $record->sadad_manually_verified ? __('admin.orders.actions.unverify') : __('admin.orders.actions.mark_verified'))
            ->icon(fn (Order $record) => $record->sadad_manually_verified ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckBadge)->color('gray')
            ->visible(fn () => self::can('orders.verify'))
            ->action(function (Order $record) {
                try {
                    $o = app(OrderStatusActions::class)->toggleVerified($record, Actor::current());
                    Notification::make()->success()->title($o->sadad_manually_verified ? __('admin.orders.notify.verified_on') : __('admin.orders.notify.verified_off'))->send();
                } catch (Throwable $e) {
                    self::fail($e);
                }
            });
    }

    public static function refundHandled(): Action
    {
        return Action::make('refund_handled')->label(__('admin.orders.actions.refund_handled'))->icon(Heroicon::OutlinedBanknotes)->color('success')
            ->visible(fn (Order $record) => self::can('orders.confirm') && in_array($record->payment_note, [PaymentNote::PaidAfterExpiryNoStock->value, PaymentNote::PaidAfterStaffCancel->value], true) && ! $record->refund_handled_at)
            ->requiresConfirmation()
            ->action(function (Order $record) {
                try {
                    app(OrderStatusActions::class)->markRefundHandled($record, Actor::current());
                    Notification::make()->success()->title(__('admin.orders.notify.refund_handled'))->send();
                } catch (Throwable $e) {
                    self::fail($e);
                }
            });
    }

    public static function sendWhatsApp(): Action
    {
        return Action::make('whatsapp')->label(__('admin.orders.actions.whatsapp'))->icon(Heroicon::OutlinedChatBubbleLeftRight)->color('success')
            ->visible(fn (Order $record) => self::can('invoices.manage') && $record->payment_status === PaymentStatus::Confirmed)
            ->requiresConfirmation()
            ->action(fn (Order $record) => self::relay('send_invoice', $record));
    }

    public static function sendEmail(): Action
    {
        return Action::make('email')->label(__('admin.orders.actions.email'))->icon(Heroicon::OutlinedEnvelope)->color('gray')
            ->visible(fn (Order $record) => self::can('invoices.manage') && $record->payment_status === PaymentStatus::Confirmed && filled($record->customer?->email))
            ->requiresConfirmation()
            ->action(fn (Order $record) => self::relay('send_email', $record));
    }

    private static function relay(string $action, Order $record): void
    {
        try {
            $res = app(AutomationRelay::class)->send($action, $record, null, Actor::current());
            ($res['success'] ?? false)
                ? Notification::make()->success()->title(__('admin.orders.notify.sent'))->send()
                : Notification::make()->warning()->title(__('admin.orders.notify.send_failed'))->body($res['message'] ?? $res['error'] ?? null)->persistent()->send();
        } catch (Throwable $e) {
            self::fail($e);
        }
    }

    public static function regenerateQr(): Action
    {
        return Action::make('regen_qr')->label(__('admin.orders.actions.regen_qr'))->icon(Heroicon::OutlinedQrCode)->color('gray')
            ->visible(fn () => self::can('tickets.edit'))
            ->requiresConfirmation()
            ->action(function (Order $record) {
                try {
                    $res = app(QrService::class)->regenerateBooking($record->booking_reference);
                    Notification::make()->success()->title(__('ops.viewer.regenerated', ['n' => $res['updated'] ?? 0]))->send();
                } catch (Throwable $e) {
                    self::fail($e);
                }
            });
    }

    public static function delete(): Action
    {
        return Action::make('delete_order')->label(__('admin.orders.actions.delete'))->icon(Heroicon::OutlinedTrash)->color('danger')
            ->visible(fn () => self::can('orders.delete') && Actor::current()->isSuperAdmin() && rescue(fn () => app(FeatureFlagSettings::class)->show_delete_order_button, false, false))
            ->requiresConfirmation()
            ->modalHeading(__('admin.orders.delete.heading'))
            ->modalDescription(__('admin.orders.delete.description'))
            ->action(function (Order $record) {
                try {
                    app(OrderStatusActions::class)->delete($record, Actor::current());
                    Notification::make()->success()->title(__('admin.orders.notify.deleted'))->send();
                } catch (Throwable $e) {
                    self::fail($e);
                }
            });
    }
}
