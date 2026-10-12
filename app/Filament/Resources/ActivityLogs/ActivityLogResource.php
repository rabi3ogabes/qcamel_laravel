<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Domain\Shared\Actor;
use App\Enums\ActivityType;
use App\Enums\ActorType;
use App\Filament\Concerns\InNavigationGroup;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\Activity;
use App\Support\Formats;
use App\Support\QatarClock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * ADM-LOG rebuild — append-only audit viewer: filters by type, actor type,
 * actor and Qatar date range, search over description / identifier /
 * properties; readable details; purge by age is owner-only and logged.
 */
class ActivityLogResource extends Resource
{
    use InNavigationGroup;

    protected static ?string $model = Activity::class;

    protected static string $navGroup = 'analytics';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'activity-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function getModelLabel(): string
    {
        return __('admin.log.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.log.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.log.title');
    }

    public static function canViewAny(): bool
    {
        return Actor::current()->can('logs.view');
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
        return false; // append-only for admins (F-ADM-LOG-009 rebuild)
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return collect(ActivityType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->sort()->all();
    }

    public static function typeIcon(?string $type): Heroicon
    {
        return match ($type) {
            'pos_form' => Heroicon::OutlinedBanknotes, 'qr_search' => Heroicon::OutlinedMagnifyingGlass, 'ticket_scan', 'ticket_checkin_reset' => Heroicon::OutlinedQrCode,
            'login_blocked', 'auth' => Heroicon::OutlinedLockClosed, 'settings_changed' => Heroicon::OutlinedCog6Tooth, 'staff_role_changed' => Heroicon::OutlinedShieldCheck,
            'event_updated', 'new_event_started', 'event_change' => Heroicon::OutlinedCalendarDays, 'order_cancelled', 'order_confirmed', 'order_deleted' => Heroicon::OutlinedShoppingBag,
            'price_changed', 'capacity_changed', 'stock_recount' => Heroicon::OutlinedSquares2x2, 'invoice_dispatch' => Heroicon::OutlinedPaperAirplane,
            'payment_review' => Heroicon::OutlinedExclamationTriangle, default => Heroicon::OutlinedDocumentText,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer'))
            ->defaultSort('id', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('log_name')->label(__('admin.log.type'))->badge()->color('gray')
                    ->formatStateUsing(fn ($state, Activity $record) => $record->typeLabel())
                    ->icon(fn ($state) => self::typeIcon($state)),
                TextColumn::make('description')->label(__('admin.log.description'))->wrap()->limit(110)->searchable(),
                TextColumn::make('actor_type')->label(__('admin.log.actor'))->badge()
                    ->formatStateUsing(fn ($state) => ActorType::tryFrom((string) $state)?->label() ?? $state)
                    ->color(fn ($state) => match ($state) { 'admin' => 'primary', 'staff' => 'info', 'system' => 'gray', default => 'warning' })
                    ->description(fn (Activity $record) => $record->causer?->name ?? $record->actor_identifier),
                TextColumn::make('actor_identifier')->hidden()->searchable(),
                TextColumn::make('ip_address')->label('IP')->fontFamily('mono')->size('xs')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label(__('admin.log.when'))->sortable()
                    ->formatStateUsing(fn ($state) => QatarClock::local($state)->format('d/m/Y H:i:s'))
                    ->description(fn (Activity $record) => Formats::ago($record->created_at)),
            ])
            ->filters([
                SelectFilter::make('log_name')->label(__('admin.log.type'))->multiple()->options(fn () => self::typeOptions()),
                SelectFilter::make('actor_type')->label(__('admin.log.actor'))->options(ActorType::options()),
                Filter::make('when')->schema([
                    Grid::make(2)->schema([
                        DatePicker::make('from')->label(__('admin.rpt.from'))->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('to')->label(__('admin.rpt.to'))->native(false)->displayFormat('d/m/Y'),
                    ]),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', QatarClock::dayRange(substr($d, 0, 10))[0]))
                    ->when($data['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<', QatarClock::dayRange(substr($d, 0, 10))[1])))
                    ->indicateUsing(fn (array $data) => array_values(array_filter([
                        ($data['from'] ?? null) ? __('admin.rpt.from').' '.Formats::date($data['from']) : null,
                        ($data['to'] ?? null) ? __('admin.rpt.to').' '.Formats::date($data['to']) : null,
                    ]))),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                Action::make('details')->label(__('admin.log.details'))->icon(Heroicon::OutlinedEye)->color('gray')->iconButton()
                    ->modalHeading(fn (Activity $record) => $record->typeLabel())->modalWidth('2xl')
                    ->modalSubmitAction(false)->modalCancelActionLabel(__('common.close'))
                    ->schema(fn (Activity $record) => [
                        TextEntry::make('description')->label(__('admin.log.description'))->state($record->description),
                        Grid::make(3)->schema([
                            TextEntry::make('actor')->label(__('admin.log.actor'))->state(($record->causer?->name ?? $record->actor_identifier ?? '—').' · '.(ActorType::tryFrom((string) $record->actor_type)?->label() ?? '—')),
                            TextEntry::make('when')->label(__('admin.log.when'))->state(Formats::longDateTime($record->created_at)),
                            TextEntry::make('ip')->label('IP')->state($record->ip_address ?? '—'),
                        ]),
                        TextEntry::make('subject')->label(__('admin.log.subject'))->state($record->subject_type ? class_basename($record->subject_type).' · '.$record->subject_id : '—'),
                        KeyValueEntry::make('properties')->label(__('admin.log.data'))
                            ->state(collect($record->properties?->toArray() ?? [])->map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))->all()),
                        TextEntry::make('ua')->label(__('admin.log.ua'))->state($record->user_agent ?? '—')->size('xs')->color('gray'),
                    ]),
            ])
            ->emptyStateHeading(__('admin.log.empty'))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList);
    }

    public static function getPages(): array
    {
        return ['index' => ListActivityLogs::route('/')];
    }
}
