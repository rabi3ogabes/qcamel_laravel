<?php

namespace App\Filament\Resources\LoginAttempts;

use App\Domain\Shared\Actor;
use App\Domain\Staff\LoginThrottle;
use App\Enums\ActivityType;
use App\Filament\Concerns\InNavigationGroup;
use App\Filament\Resources\LoginAttempts\Pages\ListLoginAttempts;
use App\Models\LoginAttempt;
use App\Support\Audit;
use App\Support\Formats;
use App\Support\QatarClock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * U-AUTH-03 — sign-in attempts (account e-mail and team passcode), who is
 * blocked right now, and a one-click unblock that is audited.
 */
class LoginAttemptResource extends Resource
{
    use InNavigationGroup;

    protected static ?string $model = LoginAttempt::class;

    protected static string $navGroup = 'system';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'login-attempts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    public static function getModelLabel(): string
    {
        return __('admin.sec.attempt');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.sec.attempts');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.sec.attempts');
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

    /** Identifiers (e-mail / IP key) with enough recent failures to be locked out now. */
    private static ?array $blocked = null;

    public static function blockedIdentifiers(): array
    {
        if (self::$blocked !== null) {
            return self::$blocked;
        }
        $since = now()->subMinutes((int) config('qcamelmc.auth.login_block', 30));

        return self::$blocked = LoginAttempt::query()->where('success', false)->where('attempted_at', '>=', $since)
            ->selectRaw('identifier, COUNT(*) as n')->groupBy('identifier')
            ->havingRaw('COUNT(*) >= ?', [(int) config('qcamelmc.auth.login_max', 5)])
            ->pluck('identifier')->all();
    }

    public static function getNavigationBadge(): ?string
    {
        $n = count(array_filter(self::blockedIdentifiers(), fn ($i) => ! str_starts_with($i, 'login-ip:')));

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('attempted_at', 'desc')
            ->poll('30s')
            ->columns([
                IconColumn::make('success')->label(__('admin.sec.result'))->boolean()->alignCenter(),
                TextColumn::make('identifier')->label(__('admin.sec.identifier'))->searchable()->fontFamily('mono')->size('sm')
                    ->formatStateUsing(fn (string $state) => match (true) {
                        str_starts_with($state, 'login-ip:') => 'IP '.substr($state, 9),
                        str_starts_with($state, 'passcode:') => __('admin.sec.kinds.passcode').' · '.substr($state, 9),
                        default => $state,
                    })
                    ->badge(fn (LoginAttempt $record) => in_array($record->identifier, self::blockedIdentifiers(), true))
                    ->color(fn (LoginAttempt $record) => in_array($record->identifier, self::blockedIdentifiers(), true) ? 'danger' : null),
                TextColumn::make('kind')->label(__('admin.sec.kind'))->badge()->color('gray')
                    ->formatStateUsing(fn ($state) => __('admin.sec.kinds.'.$state)),
                TextColumn::make('ip_address')->label('IP')->fontFamily('mono')->size('xs')->searchable(),
                TextColumn::make('user_agent')->label(__('admin.log.ua'))->limit(40)->tooltip(fn (LoginAttempt $record) => $record->user_agent)->size('xs')->color('gray')->toggleable(),
                TextColumn::make('attempted_at')->label(__('admin.log.when'))->sortable()
                    ->formatStateUsing(fn ($state) => QatarClock::local($state)->format('d/m/Y H:i:s'))
                    ->description(fn (LoginAttempt $record) => Formats::ago($record->attempted_at)),
            ])
            ->filters([
                TernaryFilter::make('success')->label(__('admin.sec.result'))->trueLabel(__('admin.sec.ok'))->falseLabel(__('admin.sec.failed')),
                SelectFilter::make('kind')->label(__('admin.sec.kind'))->options(['account' => __('admin.sec.kinds.account'), 'passcode' => __('admin.sec.kinds.passcode')]),
                TernaryFilter::make('ip_rows')->label(__('admin.sec.ip_rows'))->default(false)
                    ->queries(true: fn (Builder $q) => $q, false: fn (Builder $q) => $q->where('identifier', 'not like', 'login-ip:%'), blank: fn (Builder $q) => $q),
            ])
            ->recordActions([
                Action::make('unblock')->label(__('admin.sec.unblock'))->icon(Heroicon::OutlinedLockOpen)->color('success')->size('sm')
                    ->visible(fn (LoginAttempt $record) => in_array($record->identifier, self::blockedIdentifiers(), true))
                    ->requiresConfirmation()->modalDescription(fn (LoginAttempt $record) => __('admin.sec.unblock_desc', ['who' => $record->identifier]))
                    ->action(function (LoginAttempt $record) {
                        self::$blocked = null;
                        $n = LoginThrottle::unblock($record->identifier);
                        if ($record->ip_address) {
                            $n += LoginThrottle::unblock(LoginThrottle::ipKey($record->ip_address));
                        }
                        Audit::log(ActivityType::Auth, __('admin.sec.audit_unblock', ['who' => $record->identifier]), null, ['identifier' => $record->identifier, 'cleared' => $n]);
                        Notification::make()->success()->title(__('admin.sec.unblocked'))->send();
                    }),
            ])
            ->emptyStateHeading(__('admin.sec.empty'))
            ->emptyStateIcon(Heroicon::OutlinedFingerPrint);
    }

    public static function getPages(): array
    {
        return ['index' => ListLoginAttempts::route('/')];
    }
}
