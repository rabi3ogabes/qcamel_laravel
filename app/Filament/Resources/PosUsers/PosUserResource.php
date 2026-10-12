<?php

namespace App\Filament\Resources\PosUsers;

use App\Domain\Shared\Actor;
use App\Enums\ActivityType;
use App\Filament\Concerns\InNavigationGroup;
use App\Filament\Resources\PosUsers\Pages\ManagePosUsers;
use App\Models\PosUser;
use App\Support\Audit;
use App\Support\Formats;
use App\Support\QatarClock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * F-ADM-STF-002…009 — POS profiles (cashiers / gate team, no login): inline
 * rename, active switch, icon picker, sales today / total and last attendance
 * (U-ADM-STF-03); delete only without orders, otherwise archive.
 */
class PosUserResource extends Resource
{
    use InNavigationGroup;

    protected static ?string $model = PosUser::class;

    protected static string $navGroup = 'team';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'pos-users';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    public static function getModelLabel(): string
    {
        return __('admin.pos.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.pos.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.pos.plural');
    }

    public static function canViewAny(): bool
    {
        return Actor::current()->can('pos_users.manage');
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

    /** R-unique: active names unique case-insensitively (F-ADM-STF-004 rebuild). */
    public static function nameRules(?string $ignoreId = null): array
    {
        return ['required', 'string', 'max:80', Rule::unique('pos_users', 'name')->whereNull('deleted_at')->ignore($ignoreId)];
    }

    public static function table(Table $table): Table
    {
        [$from, $to] = QatarClock::dayRange(QatarClock::today());

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['orders as sales_today' => fn ($q) => $q->where('payment_status', 'confirmed')->whereBetween('created_at', [$from, $to])])
                ->withSum(['orders as amount_today' => fn ($q) => $q->where('payment_status', 'confirmed')->whereBetween('created_at', [$from, $to])], 'total_amount')
                ->withCount(['orders as sales_total' => fn ($q) => $q->where('payment_status', 'confirmed')])
                ->withSum(['orders as amount_total' => fn ($q) => $q->where('payment_status', 'confirmed')], 'total_amount')
                ->withMax(['attendance as last_present' => fn ($q) => $q->where('status', 'present')], 'attendance_date'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('icon')->label(__('admin.pos.icon'))->size('lg')->alignCenter()->width('4rem')
                    ->default('⭐')->tooltip(__('admin.pos.icon_hint'))
                    ->action(self::iconAction()),
                TextInputColumn::make('name')->label(__('admin.pos.name'))->searchable()
                    ->rules(fn (PosUser $record) => self::nameRules($record->id))
                    ->afterStateUpdated(fn () => Notification::make()->success()->title(__('admin.pos.renamed'))->send()),
                ToggleColumn::make('is_active')->label(__('admin.pos.status'))->onColor('success')
                    ->afterStateUpdated(fn ($state) => Notification::make()->success()->title(__($state ? 'admin.pos.enabled' : 'admin.pos.disabled'))->send()),
                TextColumn::make('sales_today')->label(__('admin.pos.sales_today'))->alignCenter()
                    ->formatStateUsing(fn ($state, PosUser $record) => $state ? $state.' · '.Formats::money($record->amount_today, short: true) : '—'),
                TextColumn::make('sales_total')->label(__('admin.pos.sales_total'))->alignCenter()->sortable()
                    ->formatStateUsing(fn ($state, PosUser $record) => $state ? $state.' · '.Formats::money($record->amount_total, short: true) : '—'),
                TextColumn::make('last_present')->label(__('admin.pos.last_attendance'))->placeholder('—')
                    ->formatStateUsing(fn ($state) => Formats::longDate($state, false)),
                TextColumn::make('created_at')->label(__('admin.pos.created'))->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([TernaryFilter::make('is_active')->label(__('admin.pos.status'))])
            ->recordActions([
                ActionGroup::make([
                    self::iconAction()->label(__('admin.pos.change_icon'))->icon(Heroicon::OutlinedFaceSmile),
                    Action::make('remove')->label(__('admin.pos.delete'))->icon(Heroicon::OutlinedTrash)->color('danger')
                        ->requiresConfirmation()->modalHeading(__('admin.pos.delete'))
                        ->modalDescription(fn (PosUser $record) => $record->orders()->exists() ? __('admin.pos.delete_has_orders') : __('admin.pos.delete_desc'))
                        ->action(function (PosUser $record) {
                            $hasOrders = $record->orders()->exists();
                            if ($hasOrders) {
                                $record->forceFill(['is_active' => false])->save();
                                $record->delete(); // soft: keeps sales attribution
                            } else {
                                $record->forceDelete();
                            }
                            Audit::log(ActivityType::PosUserChanged, __('admin.pos.audit_deleted', ['name' => $record->name]), null, ['pos_user_id' => $record->id, 'archived' => $hasOrders], Actor::current());
                            Notification::make()->success()->title(__($hasOrders ? 'admin.pos.archived' : 'admin.pos.deleted'))->send();
                        }),
                ]),
            ])
            ->emptyStateHeading(__('admin.pos.empty'))
            ->emptyStateIcon(Heroicon::OutlinedIdentification);
    }

    public static function iconAction(): Action
    {
        return Action::make('icon')->modalHeading(__('admin.pos.change_icon'))->modalWidth('md')
            ->fillForm(fn (PosUser $record) => ['icon' => $record->icon ?: PosUser::ICONS[0]])
            ->schema([ToggleButtons::make('icon')->hiddenLabel()->inline()->required()
                ->options(array_combine(PosUser::ICONS, PosUser::ICONS))])
            ->action(function (PosUser $record, array $data) {
                $record->forceFill(['icon' => $data['icon']])->save();
                Notification::make()->success()->title(__('admin.pos.icon_updated'))->send();
            });
    }

    public static function createAction(): Action
    {
        return Action::make('addPosUser')->label(__('admin.pos.add'))->icon(Heroicon::OutlinedPlus)->modalWidth('md')
            ->modalHeading(__('admin.pos.add'))
            ->schema([
                TextInput::make('name')->label(__('admin.pos.name'))->placeholder(__('admin.pos.name_ph'))->autofocus()
                    ->rules(self::nameRules())->required()->maxLength(80)
                    ->validationMessages(['unique' => __('admin.pos.name_taken')]),
            ])
            ->action(function (array $data) {
                $name = preg_replace('/\s+/u', ' ', trim($data['name']));
                $user = PosUser::query()->create(['name' => $name, 'icon' => PosUser::nextIcon(), 'is_active' => true]);
                Audit::log(ActivityType::PosUserChanged, __('admin.pos.audit_created', ['name' => $name]), $user, [], Actor::current());
                Notification::make()->success()->title(__('admin.pos.added'))->send();
            });
    }

    public static function getPages(): array
    {
        return ['index' => ManagePosUsers::route('/')];
    }
}
