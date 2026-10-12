<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Domain\Messaging\AutomationRelay;
use App\Domain\Shared\Actor;
use App\Domain\Tickets\CheckInService;
use App\Enums\ActivityType;
use App\Models\TicketHolder;
use App\Support\Audit;
use App\Support\Countries;
use App\Support\Formats;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** F-ADM-CUS-014/015/016: every ticket of the customer — edit holder phone, send one ticket, unmark attendance. */
class HoldersRelationManager extends RelationManager
{
    protected static string $relationship = 'holders';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.cus.tickets');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['order:id,booking_reference,payment_status,event_id', 'order.event:id,title,event_date']))
            ->columns([
                TextColumn::make('name')->label(__('admin.booked.cols.holder'))->weight('bold')
                    ->formatStateUsing(fn (TicketHolder $record) => trim(Countries::flag($record->nationality, '').' '.$record->name))
                    ->description(fn (TicketHolder $record) => $record->displayPhone()),
                TextColumn::make('ticket_type')->label(__('admin.orders.cols.tickets'))->badge()->formatStateUsing(fn (TicketHolder $record) => $record->ticket_type->label()),
                TextColumn::make('order.booking_reference')->label(__('admin.orders.cols.reference'))->fontFamily('mono')
                    ->description(fn (TicketHolder $record) => $record->order?->event?->title),
                TextColumn::make('order.payment_status')->label(__('admin.booked.cols.payment'))->badge()
                    ->formatStateUsing(fn (TicketHolder $record) => __('admin.trx.status.'.$record->order?->payment_status?->value))
                    ->color(fn (TicketHolder $record) => match ($record->order?->payment_status?->value) { 'confirmed' => 'success', 'pending' => 'warning', default => 'danger' }),
                TextColumn::make('is_present')->label(__('admin.booked.cols.scan'))->badge()
                    ->formatStateUsing(fn (TicketHolder $record) => $record->is_present ? '✓ '.__('common.present') : __('admin.booked.not_scanned'))
                    ->color(fn (TicketHolder $record) => $record->is_present ? 'success' : 'gray')
                    ->description(fn (TicketHolder $record) => $record->checked_in_at ? Formats::dateTime($record->checked_in_at) : null),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('phone')->label(__('admin.cus.edit_phone'))->icon(Heroicon::OutlinedPhone)
                        ->visible(fn () => Actor::current()->can('customers.edit'))
                        ->fillForm(fn (TicketHolder $record) => ['country_code' => $record->country_code ?: '+974', 'phone' => PhoneNumber::stripPrefix($record->phone)])
                        ->schema([
                            Select::make('country_code')->label(__('public.checkout.code'))->native(false)->required()
                                ->options(collect(Countries::codes())->mapWithKeys(fn ($c) => [$c['code'] => $c['flag'].' '.$c['code']])->all()),
                            TextInput::make('phone')->label(__('public.invoice.phone'))->tel()->required()
                                ->rule(fn ($get) => function ($attr, $value, $fail) use ($get) {
                                    if (! PhoneNumber::isValidFor((string) $value, (string) $get('country_code'))) {
                                        $fail(__('validation.phone_country'));
                                    }
                                }),
                        ])
                        ->action(function (TicketHolder $record, array $data) {
                            $old = $record->displayPhone();
                            $record->forceFill(['country_code' => $data['country_code'], 'phone' => PhoneNumber::normalizeNational($data['phone'], $data['country_code'])])->save();
                            Audit::log(ActivityType::CustomerChanged, __('admin.cus.audit_holder_phone', ['name' => $record->name]), $record->order, ['holder_id' => $record->id, 'old' => $old, 'new' => $record->displayPhone()], Actor::current());
                            Notification::make()->success()->title(__('admin.cus.holder_saved'))->send();
                        }),
                    Action::make('send_one')->label(__('ops.pos.send_whatsapp'))->icon(Heroicon::OutlinedChatBubbleLeftRight)->color('success')
                        ->visible(fn (TicketHolder $record) => Actor::current()->can('invoices.manage') && $record->order?->payment_status?->value === 'confirmed')
                        ->requiresConfirmation()
                        ->action(function (TicketHolder $record) {
                            $res = rescue(fn () => app(AutomationRelay::class)->send('send_single_ticket', $record->order, $record, Actor::current()), ['success' => false], false);
                            ($res['success'] ?? false)
                                ? Notification::make()->success()->title(__('admin.cus.ticket_sent', ['phone' => $record->displayPhone()]))->send()
                                : Notification::make()->danger()->title(__('admin.orders.notify.send_failed'))->body($res['message'] ?? $res['error'] ?? null)->send();
                        }),
                    Action::make('unmark')->label(__('admin.cus.unmark'))->icon(Heroicon::OutlinedUserMinus)->color('danger')
                        ->visible(fn (TicketHolder $record) => $record->is_present && Actor::current()->can('checkin.reset'))
                        ->schema([Textarea::make('reason')->label(__('ops.scanner.undo_reason'))->required()->minLength(3)->maxLength(300)])
                        ->action(function (TicketHolder $record, array $data) {
                            $res = app(CheckInService::class)->reset($record->id, Actor::current(), $data['reason']);
                            Notification::make()->{($res['success'] ?? false) ? 'success' : 'danger'}()->title($res['message'] ?? '')->send();
                        }),
                ])->icon(Heroicon::OutlinedEllipsisVertical),
            ]);
    }
}
