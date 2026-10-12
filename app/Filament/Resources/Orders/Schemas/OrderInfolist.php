<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Domain\Shared\Actor;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Support\Countries;
use App\Support\Formats;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Order page: summary, customer, tickets (price paid + scan state), payment timeline, moves, automation. */
class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $pii = Actor::current()->canSeePii();

        return $schema->columns(3)->components([
            Section::make(__('admin.orders.sections.summary'))->icon(Heroicon::OutlinedReceiptPercent)->columnSpan(2)->columns(3)->schema([
                TextEntry::make('booking_reference')->label(__('admin.orders.cols.reference'))->copyable()->fontFamily('mono')->weight('bold')->color('primary'),
                TextEntry::make('payment_status')->label(__('admin.orders.cols.status'))->badge()
                    ->formatStateUsing(fn (Order $record) => __('enums.public_payment_status.'.$record->publicStatus()))
                    ->color(fn (Order $record) => match ($record->publicStatus()) {
                        'confirmed' => 'success', 'pending' => 'warning', default => 'danger'
                    })
                    ->helperText(fn (Order $record) => $record->noteLabel()),
                TextEntry::make('total_amount')->label(__('admin.orders.cols.amount'))->formatStateUsing(fn ($state) => Formats::money($state))->weight('bold')->size('lg'),
                TextEntry::make('payment_method')->label(__('admin.orders.cols.method'))->formatStateUsing(fn (Order $record) => __('enums.payment_method.'.$record->payment_method->value))
                    ->helperText(fn (Order $record) => $record->posUser ? __('ops.shell.cashier').': '.$record->posUser->name : null),
                TextEntry::make('source')->label(__('admin.orders.filters.source'))->formatStateUsing(fn (Order $record) => __('enums.order_source.'.$record->source->value)),
                TextEntry::make('quantity')->label(__('admin.orders.cols.tickets'))->formatStateUsing(fn (Order $record) => OrdersTable::ticketSummary($record))->html(),
                TextEntry::make('event.title')->label(__('admin.orders.filters.event'))->helperText(fn (Order $record) => Formats::longDate($record->event?->event_date)),
                TextEntry::make('created_at')->label(__('admin.orders.cols.booked_at'))->formatStateUsing(fn ($state) => Formats::longDateTime($state)),
                TextEntry::make('confirmed_at')->label(__('admin.orders.cols.confirmed_at'))->formatStateUsing(fn ($state) => Formats::longDateTime($state))->placeholder('—'),
                TextEntry::make('payment_id')->label(__('admin.orders.cols.payment_id'))->fontFamily('mono')->copyable()->placeholder('—'),
                TextEntry::make('payment_expires_at')->label(__('admin.orders.cols.hold_until'))->formatStateUsing(fn ($state) => Formats::dateTime($state))->placeholder('—')
                    ->visible(fn (Order $record) => $record->payment_status->value === 'pending'),
                IconEntry::make('sadad_manually_verified')->label(__('admin.orders.cols.verified'))->boolean(),
                TextEntry::make('manual_payment_reference')->label(__('admin.orders.confirm.reference'))->placeholder('—')->visible(fn (Order $record) => filled($record->manual_payment_reference)),
                TextEntry::make('payment_error_reason')->label(__('admin.orders.cols.error'))->color('danger')->columnSpanFull()->visible(fn (Order $record) => filled($record->payment_error_reason)),
            ]),

            Section::make(__('admin.orders.sections.customer'))->icon(Heroicon::OutlinedUser)->columnSpan(1)->schema([
                TextEntry::make('customer.name')->label(__('public.invoice.name'))->weight('bold')
                    ->formatStateUsing(fn (Order $record) => trim(Countries::flag($record->customer?->nationality, '').' '.$record->customer?->name)),
                TextEntry::make('customer.phone')->label(__('public.invoice.phone'))->formatStateUsing(fn (Order $record) => $record->customer?->fullPhone())->copyable(),
                TextEntry::make('customer.email')->label(__('public.checkout.email'))->placeholder('—')->copyable(),
                TextEntry::make('customer.nationality')->label(__('public.invoice.nationality'))->formatStateUsing(fn ($state) => Countries::label($state)),
                TextEntry::make('customer.id_number')->label(__('public.checkout.id_number'))->fontFamily('mono')->visible($pii)
                    ->state(fn (Order $record) => $record->customer?->getAttribute('id_number')),
                TextEntry::make('terms_accepted_at')->label(__('admin.orders.cols.terms'))->formatStateUsing(fn ($state) => Formats::dateTime($state))->placeholder('—'),
            ]),

            Section::make(__('admin.orders.sections.holders'))->icon(Heroicon::OutlinedTicket)->columnSpanFull()->schema([
                RepeatableEntry::make('holders')->hiddenLabel()->contained(false)->grid(['default' => 1, 'md' => 2, 'xl' => 3])->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('name')->hiddenLabel()->weight('bold')
                            ->formatStateUsing(fn ($record) => trim(Countries::flag($record->nationality, '').' '.$record->name)),
                        TextEntry::make('ticket_type')->hiddenLabel()->badge()->alignEnd()
                            ->formatStateUsing(fn ($record) => $record->ticket_type->longLabel())
                            ->color(fn ($record) => ['vip' => 'warning', 'normal' => 'primary', 'parking' => 'info'][$record->ticket_type->value]),
                        TextEntry::make('qr_code')->hiddenLabel()->fontFamily('mono')->size('sm')->copyable()->columnSpanFull(),
                        TextEntry::make('phone')->hiddenLabel()->formatStateUsing(fn ($record) => $record->displayPhone())->size('sm'),
                        TextEntry::make('unit_price')->hiddenLabel()->formatStateUsing(fn ($state) => Formats::money($state, short: true))->size('sm')->alignEnd(),
                        TextEntry::make('is_present')->hiddenLabel()->columnSpanFull()->badge()
                            ->formatStateUsing(fn ($record) => $record->is_present ? '✓ '.__('common.present').' · '.Formats::dateTime($record->checked_in_at).($record->checked_in_by_name ? ' · '.$record->checked_in_by_name : '') : __('common.absent'))
                            ->color(fn ($record) => $record->is_present ? 'success' : 'gray'),
                    ]),
                ]),
            ]),

            Section::make(__('admin.orders.sections.payments'))->icon(Heroicon::OutlinedCreditCard)->columnSpan(2)->collapsible()
                ->visible(fn (Order $record) => $record->paymentEvents()->exists())->schema([
                    RepeatableEntry::make('paymentEvents')->hiddenLabel()->contained(false)->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('created_at')->hiddenLabel()->formatStateUsing(fn ($state) => Formats::dateTime($state))->size('sm'),
                            TextEntry::make('source')->hiddenLabel()->badge()->formatStateUsing(fn ($state) => __('enums.payment_event_source.'.(is_object($state) ? $state->value : $state))),
                            TextEntry::make('api_result')->hiddenLabel()->placeholder('—')->formatStateUsing(fn ($state) => $state ? __('enums.sadad_api_result.'.(is_object($state) ? $state->value : $state)) : '—'),
                            TextEntry::make('outcome')->hiddenLabel()->badge()->placeholder('—')
                                ->formatStateUsing(fn ($state) => $state ? __('enums.payment_outcome.'.(is_object($state) ? $state->value : $state)) : '—'),
                        ]),
                    ]),
                ]),

            Section::make(__('admin.orders.sections.automation'))->icon(Heroicon::OutlinedChatBubbleLeftRight)->columnSpan(1)->collapsible()->schema([
                TextEntry::make('automation_status')->label(__('admin.orders.cols.automation'))->badge(),
                TextEntry::make('n8n_response_message')->label(__('admin.orders.cols.automation_reply'))->placeholder('—'),
                TextEntry::make('n8n_responded_at')->label(__('admin.orders.cols.automation_at'))->formatStateUsing(fn ($state) => Formats::dateTime($state))->placeholder('—'),
                TextEntry::make('invoice_email_sent_at')->label(__('admin.orders.cols.email_at'))->formatStateUsing(fn ($state) => Formats::dateTime($state))->placeholder('—'),
                TextEntry::make('invoice_downloaded_at')->label(__('admin.orders.cols.downloaded_at'))->formatStateUsing(fn ($state) => Formats::dateTime($state))->placeholder('—'),
            ]),

            Section::make(__('admin.orders.sections.moves'))->icon(Heroicon::OutlinedCalendarDays)->columnSpanFull()->collapsible()
                ->visible(fn (Order $record) => $record->eventChanges()->exists())->schema([
                    RepeatableEntry::make('eventChanges')->hiddenLabel()->contained(false)->schema([
                        TextEntry::make('id')->hiddenLabel()
                            ->formatStateUsing(fn ($record) => __('admin.orders.move.row', [
                                'from' => ($record->old_event_title ?? '—').' ('.Formats::date($record->old_event_date).')',
                                'to' => ($record->new_event_title ?? '—').' ('.Formats::date($record->new_event_date).')',
                                'at' => Formats::dateTime($record->created_at), 'n' => $record->new_codes_count,
                            ])),
                    ]),
                ]),
        ]);
    }
}
