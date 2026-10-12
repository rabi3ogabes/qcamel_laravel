<?php

namespace App\Filament\Resources\Customers;

use App\Domain\Booking\Actions\OrderStatusActions;
use App\Domain\Shared\Actor;
use App\Enums\ActivityType;
use App\Filament\Concerns\InNavigationGroup;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\HoldersRelationManager;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Models\Customer;
use App\Models\Event;
use App\Settings\FeatureFlagSettings;
use App\Support\Audit;
use App\Support\Countries;
use App\Support\Formats;
use App\Support\PhoneNumber;
use App\Support\QatarClock;
use App\Support\Text;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** ADM-CUS — customers with confirmed bookings: search incl. ticket QR codes, edit, orders & tickets. */
class CustomerResource extends Resource
{
    use InNavigationGroup;

    protected static ?string $model = Customer::class;

    protected static string $navGroup = 'customers';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('admin.cus.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.cus.plural');
    }

    public static function canViewAny(): bool
    {
        return Actor::current()->can('customers.view');
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
        return parent::getEloquentQuery()
            ->whereHas('orders', fn ($o) => $o->where('payment_status', 'confirmed'))
            ->withCount(['orders as confirmed_orders_count' => fn ($o) => $o->where('payment_status', 'confirmed')])
            ->withMax(['orders as last_order_at' => fn ($o) => $o->where('payment_status', 'confirmed')], 'created_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_order_at', 'desc')
            ->defaultPaginationPageOption(30)
            ->columns([
                TextColumn::make('name')->label(__('public.invoice.name'))->weight('bold')
                    ->formatStateUsing(fn (Customer $record) => trim(Countries::flag($record->nationality, '🌐').' '.$record->name))
                    ->searchable(query: fn (Builder $query, string $search) => self::search($query, $search)),
                TextColumn::make('phone')->label(__('public.invoice.phone'))->formatStateUsing(fn (Customer $record) => $record->fullPhone())->copyable(),
                TextColumn::make('email')->label(__('public.checkout.email'))->placeholder('—')->toggleable(),
                TextColumn::make('nationality')->label(__('public.invoice.nationality'))->formatStateUsing(fn ($state) => Countries::label($state))->toggleable(),
                TextColumn::make('confirmed_orders_count')->label(__('admin.cus.cols.orders'))->badge()->alignCenter()->sortable(),
                TextColumn::make('lifetime')->label(__('admin.cus.cols.lifetime'))->badge()->alignCenter()
                    ->state(fn (Customer $record) => $record->person_key ? DB::table('ticket_holders as h')->join('orders as o', 'o.id', '=', 'h.order_id')->where('o.payment_status', 'confirmed')->whereNull('o.deleted_at')->where('h.person_key', $record->person_key)->count() : null)
                    ->color(fn ($state) => $state > 1 ? 'warning' : 'gray')->tooltip(__('ops.live.lifetime_hint')),
                TextColumn::make('last_order_at')->label(__('admin.cus.cols.last'))->sortable()->formatStateUsing(fn ($state) => Formats::dateTime($state)),
            ])
            ->filters([
                SelectFilter::make('season')->label(__('admin.trx.filters.season'))->default('current')
                    ->options(['current' => __('common.current'), 'archive' => __('common.archive')])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $q->whereHas('orders', fn ($o) => $o->where('payment_status', 'confirmed')->whereHas('event', fn ($e) => $e->where('is_archived', $v === 'archive'))))),
                SelectFilter::make('event')->label(__('admin.orders.filters.event'))->searchable()
                    ->options(fn () => Event::query()->orderByDesc('event_date')->get()->mapWithKeys(fn (Event $e) => [$e->id => Formats::date($e->event_date).' — '.$e->title])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $q->whereHas('orders', fn ($o) => $o->where('payment_status', 'confirmed')->where('event_id', $v)))),
                SelectFilter::make('nationality')->label(__('public.invoice.nationality'))
                    ->options(collect(Countries::list('pos_codes'))->mapWithKeys(fn ($c) => [$c['ar'] => $c['flag'].' '.$c['ar']])->all()),
            ])
            ->recordActions([ViewAction::make()->iconButton()])
            ->emptyStateHeading(__('admin.cus.empty'));
    }

    /** F-ADM-CUS-004 rebuild: name, e-mail, normalised phone digits, booking reference or exact ticket code. */
    public static function search(Builder $query, string $search): Builder
    {
        $like = Text::like($search);
        $digits = PhoneNumber::digits($search);

        return $query->where(function ($w) use ($like, $digits, $search) {
            $w->where('name', 'like', $like)->orWhere('email', 'like', $like)
                ->orWhereHas('orders', fn ($o) => $o->where('booking_reference', 'like', $like))
                ->orWhereHas('holders', fn ($h) => $h->where('qr_code', strtoupper(trim($search))));
            if (strlen($digits) >= 4) {
                $w->orWhere('phone', 'like', '%'.$digits.'%')->orWhere('phone_e164', 'like', '%'.$digits.'%');
            }
        });
    }

    public static function infolist(Schema $schema): Schema
    {
        $pii = Actor::current()->canSeePii();

        return $schema->components([
            Section::make(__('admin.cus.info'))->columns(4)->schema([
                TextEntry::make('name')->label(__('public.invoice.name'))->weight('bold')->formatStateUsing(fn (Customer $record) => trim(Countries::flag($record->nationality, '').' '.$record->name)),
                TextEntry::make('phone')->label(__('public.invoice.phone'))->formatStateUsing(fn (Customer $record) => $record->fullPhone())->copyable(),
                TextEntry::make('email')->label(__('public.checkout.email'))->placeholder('—')->copyable(),
                TextEntry::make('nationality')->label(__('public.invoice.nationality'))->formatStateUsing(fn ($state) => Countries::label($state)),
                TextEntry::make('id_number')->label(__('public.checkout.id_number'))->fontFamily('mono')->visible($pii)->state(fn (Customer $record) => $record->getAttribute('id_number')),
                TextEntry::make('created_at')->label(__('admin.cus.cols.first'))->formatStateUsing(fn ($state) => Formats::dateTime($state)),
            ]),
        ]);
    }

    public static function editAction(): Action
    {
        return Action::make('edit_customer')->label(__('admin.cus.edit'))->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn () => Actor::current()->can('customers.edit'))
            ->fillForm(fn (Customer $record) => ['name' => $record->name, 'email' => $record->email, 'country_code' => $record->country_code ?: '+974', 'phone' => PhoneNumber::stripPrefix($record->phone), 'nationality' => $record->nationality ?: 'قطر'])
            ->schema([
                TextInput::make('name')->label(__('public.invoice.name'))->required()->maxLength(120),
                TextInput::make('email')->label(__('public.checkout.email'))->email()->maxLength(254),
                Select::make('country_code')->label(__('public.checkout.code'))->native(false)->required()
                    ->options(collect(Countries::codes())->mapWithKeys(fn ($c) => [$c['code'] => $c['flag'].' '.$c['code']])->all()),
                TextInput::make('phone')->label(__('public.invoice.phone'))->required()->tel()
                    ->rule(fn ($get) => function ($attr, $value, $fail) use ($get) {
                        if (! PhoneNumber::isValidFor((string) $value, (string) $get('country_code'))) {
                            $fail(__('validation.phone_country'));
                        }
                    }),
                Select::make('nationality')->label(__('public.invoice.nationality'))->native(false)->searchable()->required()
                    ->options(collect(Countries::list('pos_codes'))->mapWithKeys(fn ($c) => [$c['ar'] => $c['flag'].' '.$c['ar']])->all()),
            ])
            ->action(function (Customer $record, array $data) {
                $old = $record->only(['name', 'email', 'phone', 'country_code', 'nationality']);
                $record->forceFill([
                    'name' => Text::clean($data['name']), 'email' => $data['email'] ?: null, 'country_code' => $data['country_code'],
                    'phone' => PhoneNumber::normalizeNational($data['phone'], $data['country_code']), 'nationality' => $data['nationality'],
                ])->save();
                Audit::log(ActivityType::CustomerChanged, __('admin.cus.audit_edit', ['name' => $record->name]), $record, ['old' => $old, 'new' => $record->only(array_keys($old))], Actor::current());
                Notification::make()->success()->title(__('admin.cus.saved'))->send();
            });
    }

    public static function deleteAction(): Action
    {
        return Action::make('delete_customer')->label(__('admin.cus.delete'))->icon(Heroicon::OutlinedTrash)->color('danger')
            ->visible(fn () => Actor::current()->can('customers.delete') && Actor::current()->isSuperAdmin() && rescue(fn () => app(FeatureFlagSettings::class)->show_delete_customer_button, false, false))
            ->requiresConfirmation()->modalDescription(__('admin.cus.delete_confirm'))
            ->action(function (Customer $record, $livewire) {
                $upcoming = $record->orders()->where('payment_status', 'confirmed')->whereHas('event', fn ($e) => $e->where('event_date', '>=', QatarClock::dayRange(QatarClock::today())[0]))->exists();
                if ($upcoming) {
                    Notification::make()->danger()->title(__('admin.cus.delete_upcoming'))->send();

                    return;
                }
                DB::transaction(function () use ($record) {
                    foreach ($record->orders()->get() as $o) {
                        app(OrderStatusActions::class)->delete($o, Actor::current());
                    }
                    Audit::log(ActivityType::CustomerChanged, __('admin.cus.audit_delete', ['name' => $record->name]), null, ['customer_id' => $record->id], Actor::current());
                    $record->delete();
                });
                Notification::make()->success()->title(__('admin.cus.deleted'))->send();
                $livewire->redirect(self::getUrl());
            });
    }

    public static function getRelations(): array
    {
        return [OrdersRelationManager::class, HoldersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
