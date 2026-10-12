<?php

namespace App\Filament\Resources\StaffAccounts;

use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Domain\Staff\StaffAccountService;
use App\Enums\Role;
use App\Filament\Concerns\InNavigationGroup;
use App\Filament\Resources\StaffAccounts\Pages\ManageStaffAccounts;
use App\Models\PosUser;
use App\Models\User;
use App\Support\Formats;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * F-AUTH-026…031 / F-ADM-STF-012 — staff login accounts: create (password or
 * e-mail invite), change role, reset password, link POS profile, sign out
 * everywhere, revoke. Every rule lives in StaffAccountService.
 */
class StaffAccountResource extends Resource
{
    use InNavigationGroup;

    protected static ?string $model = User::class;

    protected static string $navGroup = 'team';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'staff-accounts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function getModelLabel(): string
    {
        return __('admin.stf.account');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.stf.accounts');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.stf.accounts');
    }

    public static function canViewAny(): bool
    {
        return Actor::current()->can('staff.manage');
    }

    public static function canCreate(): bool
    {
        return false; // header action with the service instead of a CRUD form
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** Roles the acting admin may grant (admin-level only by the owner). */
    public static function roleOptions(): array
    {
        $owner = Actor::current()->isSuperAdmin();

        return collect(Role::cases())->sortByDesc(fn (Role $r) => $r->rank())
            ->reject(fn (Role $r) => ! $owner && in_array($r->value, Role::adminRoles(), true))
            ->mapWithKeys(fn (Role $r) => [$r->value => $r->label().' — '.__('admin.stf.role_hint.'.$r->value)])->all();
    }

    public static function run(callable $fn, string $ok): bool
    {
        try {
            $fn();
            Notification::make()->success()->title($ok)->send();

            return true;
        } catch (DomainError $e) {
            $errors = $e->extra['errors'] ?? [];
            Notification::make()->danger()->title($errors ? implode(' · ', $errors) : $e->userMessage())->send();

            return false;
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['roles', 'posUser'])->withCount(['roles']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('admin.stf.name'))->weight('bold')->searchable()
                    ->description(fn (User $record) => $record->email)
                    ->icon(fn (User $record) => $record->isAdmin() ? Heroicon::ShieldCheck : Heroicon::OutlinedUser)
                    ->iconColor(fn (User $record) => $record->isAdmin() ? 'primary' : 'gray'),
                TextColumn::make('email')->hidden()->searchable(),
                TextColumn::make('role')->label(__('admin.stf.role'))->badge()
                    ->state(fn (User $record) => $record->topRole()?->label() ?? __('admin.stf.no_role'))
                    ->color(fn (User $record) => match ($record->topRole()) {
                        Role::SuperAdmin => 'warning', Role::Admin => 'primary', null => 'gray', default => 'info',
                    }),
                TextColumn::make('posUser.name')->label(__('admin.stf.pos_profile'))->placeholder('—')
                    ->formatStateUsing(fn ($state, User $record) => $record->posUser?->label()),
                IconColumn::make('two_factor_confirmed_at')->label(__('admin.stf.twofa'))->boolean()
                    ->state(fn (User $record) => $record->two_factor_confirmed_at !== null)->alignCenter(),
                TextColumn::make('last_login_at')->label(__('admin.stf.last_login'))->placeholder(__('admin.stf.never'))
                    ->formatStateUsing(fn ($state) => Formats::dateTime($state))
                    ->description(fn (User $record) => $record->last_login_at ? Formats::ago($record->last_login_at) : null)->sortable(),
                IconColumn::make('is_active')->label(__('admin.stf.status'))->boolean()->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('role')->label(__('admin.stf.role'))->options(collect(Role::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $q->whereHas('roles', fn ($r) => $r->where('name', $v)))),
                TernaryFilter::make('is_active')->label(__('admin.stf.status'))->default(true),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('role')->label(__('admin.stf.change_role'))->icon(Heroicon::OutlinedArrowsUpDown)
                        ->fillForm(fn (User $record) => ['role' => $record->topRole()?->value])
                        ->schema([Select::make('role')->label(__('admin.stf.role'))->options(fn () => self::roleOptions())->required()->native(false)])
                        ->action(fn (User $record, array $data) => self::run(fn () => app(StaffAccountService::class)->setRole($record, Role::from($data['role']), Actor::current()), __('admin.stf.role_updated'))),
                    Action::make('pos')->label(__('admin.stf.link_pos'))->icon(Heroicon::OutlinedLink)
                        ->fillForm(fn (User $record) => ['pos_user_id' => $record->pos_user_id])
                        ->schema([Select::make('pos_user_id')->label(__('admin.stf.pos_profile'))->helperText(__('admin.stf.pos_profile_hint'))
                            ->options(fn () => PosUser::query()->active()->get()->mapWithKeys(fn ($p) => [$p->id => $p->label()])->all())->native(false)->searchable()])
                        ->action(function (User $record, array $data) {
                            $record->forceFill(['pos_user_id' => $data['pos_user_id'] ?: null])->save();
                            Notification::make()->success()->title(__('admin.common.saved'))->send();
                        }),
                    Action::make('password')->label(__('admin.stf.reset_password'))->icon(Heroicon::OutlinedKey)
                        ->modalDescription(fn (User $record) => __('admin.stf.reset_password_desc', ['email' => $record->email]))
                        ->schema([TextInput::make('password')->label(__('admin.stf.new_password'))->password()->revealable()->required()->minLength(8)->maxLength(128)])
                        ->action(fn (User $record, array $data) => self::run(fn () => app(StaffAccountService::class)->resetPassword($record, $data['password'], Actor::current()), __('admin.stf.password_changed'))),
                    Action::make('sessions')->label(__('admin.stf.sign_out_all'))->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
                        ->requiresConfirmation()->modalDescription(__('admin.stf.sign_out_all_desc'))
                        ->action(function (User $record) {
                            $n = app(StaffAccountService::class)->killSessions($record, exceptCurrent: $record->id === Actor::current()->userId());
                            Notification::make()->success()->title(__('admin.stf.signed_out', ['n' => $n]))->send();
                        }),
                    Action::make('revoke')->label(__('admin.stf.revoke'))->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                        ->visible(fn (User $record) => $record->is_active)
                        ->requiresConfirmation()->modalHeading(__('admin.stf.revoke'))
                        ->modalDescription(fn (User $record) => __('admin.stf.revoke_desc', ['email' => $record->email]))
                        ->action(fn (User $record) => self::run(fn () => app(StaffAccountService::class)->revoke($record, Actor::current()), __('admin.stf.revoked'))),
                ])->tooltip(__('admin.common.actions')),
            ])
            ->emptyStateHeading(__('admin.stf.empty'))
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck);
    }

    public static function createAction(): Action
    {
        return Action::make('createAccount')->label(__('admin.stf.create'))->icon(Heroicon::OutlinedUserPlus)
            ->modalHeading(__('admin.stf.create'))->modalDescription(__('admin.stf.create_desc'))->modalWidth('lg')
            ->fillForm(['role' => Role::Supervisor->value, 'invite' => false])
            ->schema([
                TextInput::make('name')->label(__('admin.stf.name'))->maxLength(100),
                TextInput::make('email')->label(__('admin.stf.email'))->email()->required()->maxLength(254)->placeholder('staff@example.com'),
                Select::make('role')->label(__('admin.stf.role'))->options(fn () => self::roleOptions())->required()->native(false),
                Toggle::make('invite')->label(__('admin.stf.invite'))->helperText(__('admin.stf.invite_hint'))->live(),
                TextInput::make('password')->label(__('admin.stf.password'))->password()->revealable()->minLength(8)->maxLength(128)
                    ->placeholder(__('admin.stf.password_ph'))->required(fn (Get $get) => ! $get('invite'))->hidden(fn (Get $get) => (bool) $get('invite')),
            ])
            ->action(function (array $data, Action $action) {
                $ok = self::run(fn () => app(StaffAccountService::class)->create((string) ($data['name'] ?? ''), $data['email'], (string) ($data['password'] ?? ''), Role::from($data['role']), Actor::current(), (bool) $data['invite']),
                    __($data['invite'] ? 'admin.stf.invited' : 'admin.stf.created'));
                if (! $ok) {
                    $action->halt();
                }
            });
    }

    public static function getPages(): array
    {
        return ['index' => ManageStaffAccounts::route('/')];
    }
}
