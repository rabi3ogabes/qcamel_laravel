<?php

namespace App\Filament\Resources\KioskDevices;

use App\Domain\Shared\Actor;
use App\Domain\Staff\PasscodeAuthenticator;
use App\Enums\ActivityType;
use App\Enums\DeviceKind;
use App\Filament\Concerns\InNavigationGroup;
use App\Filament\Resources\KioskDevices\Pages\ListKioskDevices;
use App\Models\KioskDevice;
use App\Support\Audit;
use App\Support\Formats;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * R-141 / U-AUTH-02 — devices signed in with the team passcode: name,
 * kind, last seen; revoke one or all (revoking signs the device out on its
 * next request). The device token itself is never shown.
 */
class KioskDeviceResource extends Resource
{
    use InNavigationGroup;

    protected static ?string $model = KioskDevice::class;

    protected static string $navGroup = 'system';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'kiosk-devices';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDeviceTablet;

    public static function getModelLabel(): string
    {
        return __('admin.sec.device');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.sec.devices');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.sec.devices');
    }

    public static function canViewAny(): bool
    {
        return Actor::current()->can('staff.manage');
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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('posUser'))
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('admin.sec.device_name'))->weight('bold')->searchable()
                    ->icon(fn (KioskDevice $record) => match ($record->kind) {
                        DeviceKind::Pos => Heroicon::OutlinedBanknotes, DeviceKind::Board => Heroicon::OutlinedTv, default => Heroicon::OutlinedQrCode
                    })
                    ->description(fn (KioskDevice $record) => $record->kind?->label()),
                TextColumn::make('posUser.name')->label(__('admin.stf.pos_profile'))->placeholder('—')
                    ->formatStateUsing(fn ($state, KioskDevice $record) => $record->posUser?->label()),
                TextColumn::make('status')->label(__('admin.pos.status'))->badge()
                    ->state(fn (KioskDevice $record) => $record->revoked_at ? 'revoked' : ($record->isUsable() ? 'active' : 'expired'))
                    ->formatStateUsing(fn ($state) => __('admin.sec.state.'.$state))
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success', 'expired' => 'gray', default => 'danger'
                    }),
                TextColumn::make('last_seen_at')->label(__('admin.sec.last_seen'))->sortable()->placeholder('—')
                    ->formatStateUsing(fn ($state) => Formats::ago($state))
                    ->description(fn (KioskDevice $record) => $record->last_ip),
                TextColumn::make('user_agent')->label(__('admin.log.ua'))->limit(36)->tooltip(fn (KioskDevice $record) => $record->user_agent)->size('xs')->color('gray')->toggleable(),
                TextColumn::make('expires_at')->label(__('admin.sec.expires'))->formatStateUsing(fn ($state) => Formats::dateTime($state))->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('usable')->label(__('admin.sec.state.active'))->default(true)
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('revoked_at')->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now())),
                        false: fn (Builder $q) => $q->where(fn ($w) => $w->whereNotNull('revoked_at')->orWhere('expires_at', '<=', now())),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('rename')->label(__('admin.sec.rename'))->icon(Heroicon::OutlinedPencilSquare)
                        ->fillForm(fn (KioskDevice $record) => ['name' => $record->name, 'kind' => $record->kind?->value])
                        ->schema([
                            TextInput::make('name')->label(__('admin.sec.device_name'))->required()->maxLength(80),
                            Select::make('kind')->label(__('admin.sec.kind'))->options(DeviceKind::options())->required()->native(false),
                        ])
                        ->action(function (KioskDevice $record, array $data) {
                            $record->forceFill(['name' => trim($data['name']), 'kind' => $data['kind']])->save();
                            Notification::make()->success()->title(__('admin.common.saved'))->send();
                        }),
                    Action::make('revoke')->label(__('admin.sec.revoke'))->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                        ->visible(fn (KioskDevice $record) => $record->revoked_at === null)
                        ->requiresConfirmation()->modalDescription(fn (KioskDevice $record) => __('admin.sec.revoke_desc', ['name' => $record->label()]))
                        ->action(function (KioskDevice $record) {
                            $record->forceFill(['revoked_at' => now()])->save();
                            Audit::log(ActivityType::Auth, __('admin.sec.audit_revoke', ['name' => $record->label()]), $record);
                            Notification::make()->success()->title(__('admin.sec.revoked'))->send();
                        }),
                ]),
            ])
            ->emptyStateHeading(__('admin.sec.no_devices'))
            ->emptyStateDescription(__('admin.sec.no_devices_hint'))
            ->emptyStateIcon(Heroicon::OutlinedDeviceTablet);
    }

    public static function revokeAllAction(): Action
    {
        return Action::make('revokeAll')->label(__('admin.sec.revoke_all'))->icon(Heroicon::OutlinedNoSymbol)->color('danger')->outlined()
            ->requiresConfirmation()->modalDescription(__('admin.sec.revoke_all_desc'))
            ->action(function () {
                $n = PasscodeAuthenticator::revokeAll();
                Audit::log(ActivityType::Auth, __('admin.sec.audit_revoke_all', ['n' => $n]), null, ['revoked' => $n]);
                Notification::make()->success()->title(__('admin.sec.revoked_n', ['n' => $n]))->send();
            });
    }

    public static function getPages(): array
    {
        return ['index' => ListKioskDevices::route('/')];
    }
}
