<?php

namespace App\Filament\Pages;

use App\Domain\Events\EventAdminActions;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\TicketKind;
use App\Filament\Concerns\InNavigationGroup;
use App\Models\Event;
use App\Settings\EventSettings;
use App\Settings\FeatureFlagSettings;
use App\Support\QatarClock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * ADM-EVT — events & seasons: month-range folders, capacity bars with
 * confirmed AND held figures (one aggregate query), transactional edit with
 * ticket types, archive/restore/active/delete, current event, new season.
 */
class EventsManager extends Page
{
    use InNavigationGroup;

    protected string $view = 'filament.pages.events-manager';

    protected static string $navGroup = 'events';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'events';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    #[Url]
    public string $season = 'current';

    #[Url(as: 'date')]
    public ?string $date = null;

    public ?string $currentEventId = null;

    public static function getNavigationLabel(): string
    {
        return __('admin.evt.nav');
    }

    public function getTitle(): string
    {
        return __('admin.evt.title');
    }

    public static function canAccess(): bool
    {
        return Actor::current()->can('events.view');
    }

    public function mount(): void
    {
        $this->currentEventId = app(EventSettings::class)->current_event_id;
    }

    private function canManage(): bool
    {
        return Actor::current()->can('events.manage');
    }

    // ---- Shared form -----------------------------------------------------------

    /** @return array<int, mixed> */
    private function eventSchema(bool $season = false): array
    {
        $ticketRow = fn (TicketKind $k) => Grid::make(3)->schema([
            TextInput::make("tickets.{$k->value}.qty")->label(__('admin.evt.qty', ['type' => $k->longLabel()]))->integer()->minValue(0)
                ->required($season)->prefixIcon(Heroicon::OutlinedTicket),
            TextInput::make("tickets.{$k->value}.price")->label(__('admin.evt.price'))->numeric()->minValue(0)->step('0.01')
                ->required($season)->suffix(__('common.qar_short')),
            TextInput::make("tickets.{$k->value}.description")->label(__('admin.evt.ticket_desc'))->maxLength(500)->placeholder($k->defaultDescription()),
        ]);

        return [
            Section::make(__('admin.evt.s_basics'))->icon(Heroicon::OutlinedSparkles)->schema([
                TextInput::make('title')->label(__('admin.evt.f_title'))->required()->maxLength(200)->autofocus(),
                Textarea::make('description')->label(__('admin.evt.f_description'))->rows(3)->maxLength(5000),
                TextInput::make('location')->label(__('admin.evt.f_location'))->required()->maxLength(200)->prefixIcon(Heroicon::OutlinedMapPin),
            ]),
            Section::make(__('admin.evt.s_schedule'))->icon(Heroicon::OutlinedCalendar)->description(__('admin.evt.tz_hint'))->columns(2)->schema([
                DatePicker::make('start_day')->label(__($season ? 'admin.evt.f_start_day' : 'admin.evt.f_date'))->native(false)->displayFormat('d/m/Y')->required()->closeOnDateSelection(),
                DatePicker::make('end_day')->label(__('admin.evt.f_end_day'))->native(false)->displayFormat('d/m/Y')->closeOnDateSelection()
                    ->required($season)->afterOrEqual('start_day')->helperText(__('admin.evt.end_day_hint')),
                TimePicker::make('start_time')->label(__('admin.evt.f_start_time'))->seconds(false),
                TimePicker::make('end_time')->label(__('admin.evt.f_end_time'))->seconds(false),
            ]),
            Section::make(__('admin.evt.s_tickets'))->icon(Heroicon::OutlinedTicket)->description(__($season ? 'admin.evt.tickets_hint_season' : 'admin.evt.tickets_hint'))
                ->schema(array_map($ticketRow, TicketKind::cases())),
            Section::make(__('admin.evt.s_media'))->icon(Heroicon::OutlinedPhoto)->collapsible()->collapsed($season)->schema([
                FileUpload::make('image_path')->label(__('admin.evt.f_image'))->disk('public')->directory('events')->image()->maxSize(10240)
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->imagePreviewHeight('140')->helperText(__('admin.evt.image_hint')),
                TextInput::make('image_url')->label(__('admin.evt.f_image_url'))->url()->maxLength(2048)->placeholder('https://')->prefixIcon(Heroicon::OutlinedLink),
                TextInput::make('video_url')->label(__('admin.evt.f_video_url'))->url()->maxLength(2048)->placeholder('https://')->prefixIcon(Heroicon::OutlinedFilm),
            ]),
            Section::make(__('admin.evt.s_rules'))->icon(Heroicon::OutlinedAdjustmentsHorizontal)->collapsible()->collapsed()->columns(2)->schema(array_values(array_filter([
                $season ? null : TextInput::make('display_order')->label(__('admin.evt.f_order'))->integer()->minValue(0)->default(0),
                $season ? null : Toggle::make('is_active')->label(__('admin.evt.f_active'))->default(true)->inline(false),
                TimePicker::make('sales_cutoff_time')->label(__('admin.evt.f_sales_cutoff'))->seconds(false)->helperText(__('admin.evt.cutoff_hint')),
                TimePicker::make('checkin_cutoff_time')->label(__('admin.evt.f_checkin_cutoff'))->seconds(false),
                TextInput::make('max_admission_per_order')->label(__('admin.evt.f_max_admission'))->integer()->minValue(0)->placeholder((string) config('qcamelmc.orders.max_admission_per_web_order')),
                TextInput::make('max_parking_per_order')->label(__('admin.evt.f_max_parking'))->integer()->minValue(0)->placeholder((string) config('qcamelmc.orders.max_parking_per_web_order')),
                TextInput::make('per_person_cap')->label(__('admin.evt.f_person_cap'))->integer()->minValue(0)->placeholder((string) config('qcamelmc.orders.per_person_cap')),
                TextInput::make('hold_minutes')->label(__('admin.evt.f_hold'))->integer()->minValue(1)->suffix(__('admin.evt.minutes'))->placeholder((string) config('qcamelmc.orders.hold_minutes')),
            ]))),
        ];
    }

    private function formData(Event $e): array
    {
        $tickets = [];
        foreach ($e->ticketTypes as $t) {
            $tickets[$t->type->value] = ['qty' => (int) $t->available_quantity, 'price' => (string) $t->price, 'description' => $t->description];
        }

        return [
            'title' => $e->title, 'description' => $e->description, 'location' => $e->location,
            'start_day' => $e->firstDay(), 'end_day' => $e->end_date ? $e->lastDay() : null,
            'start_time' => $e->start_time ? substr($e->start_time, 0, 5) : null, 'end_time' => $e->end_time ? substr($e->end_time, 0, 5) : null,
            'image_path' => $e->image_path, 'image_url' => $e->image_url, 'video_url' => $e->video_url,
            'display_order' => (int) $e->display_order, 'is_active' => (bool) $e->is_active,
            'sales_cutoff_time' => $e->sales_cutoff_time ? substr($e->sales_cutoff_time, 0, 5) : null,
            'checkin_cutoff_time' => $e->checkin_cutoff_time ? substr($e->checkin_cutoff_time, 0, 5) : null,
            'max_admission_per_order' => $e->max_admission_per_order, 'max_parking_per_order' => $e->max_parking_per_order,
            'per_person_cap' => $e->per_person_cap, 'hold_minutes' => $e->hold_minutes,
            'tickets' => $tickets,
        ];
    }

    private function run(callable $fn, string $ok): bool
    {
        try {
            $fn();
            Notification::make()->success()->title($ok)->send();

            return true;
        } catch (DomainError $e) {
            $errors = $e->extra['errors'] ?? [];
            Notification::make()->danger()->title($errors ? implode(' · ', $errors) : $e->userMessage())->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->danger()->title(__('admin.evt.failed'))->send();
        }

        return false;
    }

    // ---- Header actions ---------------------------------------------------------

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label(__('admin.evt.create'))->icon(Heroicon::OutlinedPlus)->color('gray')
                ->visible(fn () => $this->canManage())
                ->modalHeading(__('admin.evt.create'))->modalWidth(Width::ThreeExtraLarge)->modalSubmitActionLabel(__('admin.evt.save'))
                ->fillForm(['is_active' => true, 'display_order' => 0])
                ->schema(fn () => $this->eventSchema())
                ->action(function (array $data, Action $action) {
                    if (! $this->run(fn () => app(EventAdminActions::class)->save(null, $data, Actor::current()), __('admin.evt.created'))) {
                        $action->halt();
                    }
                }),
            Action::make('startSeason')->label(__('admin.evt.start_season'))->icon(Heroicon::OutlinedSparkles)
                ->visible(fn () => $this->canManage())
                ->modalHeading(__('admin.evt.start_season'))->modalDescription(__('admin.evt.start_season_desc'))
                ->modalIcon(Heroicon::OutlinedSparkles)->modalWidth(Width::ThreeExtraLarge)->modalSubmitActionLabel(__('admin.evt.start_season_submit'))
                ->fillForm(fn () => ['archive_previous' => true, 'start_day' => QatarClock::today(), 'start_time' => '14:00', 'end_time' => '22:00'])
                ->schema(fn () => [
                    ...$this->eventSchema(true),
                    Toggle::make('archive_previous')->label(__('admin.evt.archive_previous'))->helperText(__('admin.evt.archive_previous_hint'))->onColor('warning'),
                ])
                ->action(function (array $data, Action $action) {
                    $archive = (bool) ($data['archive_previous'] ?? false);
                    unset($data['archive_previous']);
                    $ok = $this->run(function () use ($data, $archive) {
                        $event = app(EventAdminActions::class)->startNewSeason($data, $archive, Actor::current());
                        $this->currentEventId = $event->id;
                    }, __('admin.evt.season_started'));
                    if (! $ok) {
                        $action->halt();
                    }
                    $this->season = 'current';
                }),
        ];
    }

    // ---- Row actions (rendered in the cards with arguments) ------------------------

    public function editEventAction(): Action
    {
        return Action::make('editEvent')->label(__('common.edit'))->icon(Heroicon::OutlinedPencilSquare)->color('gray')->size('sm')
            ->visible(fn () => $this->canManage())
            ->modalHeading(fn (array $arguments) => __('admin.evt.edit').' — '.Event::query()->whereKey($arguments['id'] ?? null)->value('title'))
            ->modalWidth(Width::ThreeExtraLarge)->modalSubmitActionLabel(__('admin.evt.save'))
            ->fillForm(fn (array $arguments) => $this->formData(Event::query()->with('ticketTypes')->findOrFail($arguments['id'])))
            ->schema(fn () => $this->eventSchema())
            ->action(function (array $data, array $arguments, Action $action) {
                $event = Event::query()->findOrFail($arguments['id']);
                if (! $this->run(fn () => app(EventAdminActions::class)->save($event, $data, Actor::current()), __('admin.common.saved'))) {
                    $action->halt();
                }
            });
    }

    public function duplicateEventAction(): Action
    {
        return Action::make('duplicateEvent')->label(__('admin.evt.duplicate'))->icon(Heroicon::OutlinedDocumentDuplicate)->color('gray')->size('sm')
            ->visible(fn () => $this->canManage())
            ->modalHeading(__('admin.evt.duplicate'))->modalWidth(Width::ThreeExtraLarge)->modalSubmitActionLabel(__('admin.evt.save'))
            ->fillForm(function (array $arguments) {
                $data = $this->formData(Event::query()->with('ticketTypes')->findOrFail($arguments['id']));
                $data['title'] = $data['title'].' '.__('admin.evt.copy_suffix');
                $data['is_active'] = false;

                return $data;
            })
            ->schema(fn () => $this->eventSchema())
            ->action(function (array $data, Action $action) {
                if (! $this->run(fn () => app(EventAdminActions::class)->save(null, $data, Actor::current()), __('admin.evt.created'))) {
                    $action->halt();
                }
            });
    }

    public function archiveEventAction(): Action
    {
        return Action::make('archiveEvent')->iconButton()
            ->icon(fn (array $arguments) => ($arguments['archived'] ?? false) ? Heroicon::OutlinedArrowUturnLeft : Heroicon::OutlinedArchiveBox)
            ->tooltip(fn (array $arguments) => __(($arguments['archived'] ?? false) ? 'admin.evt.restore' : 'admin.evt.archive'))
            ->color('gray')->visible(fn () => $this->canManage())
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => __(($arguments['archived'] ?? false) ? 'admin.evt.restore' : 'admin.evt.archive'))
            ->modalDescription(fn (array $arguments) => __(($arguments['archived'] ?? false) ? 'admin.evt.restore_desc' : 'admin.evt.archive_desc'))
            ->action(function (array $arguments) {
                $event = Event::query()->findOrFail($arguments['id']);
                $archive = ! $event->is_archived;
                $this->run(fn () => app(EventAdminActions::class)->setArchived($event, $archive, Actor::current()), __($archive ? 'admin.evt.archived' : 'admin.evt.restored'));
                if ($archive && $this->currentEventId === $event->id) {
                    $this->currentEventId = null;
                }
            });
    }

    public function deleteEventAction(): Action
    {
        return Action::make('deleteEvent')->iconButton()->icon(Heroicon::OutlinedTrash)->color('danger')->tooltip(__('admin.evt.delete'))
            ->visible(fn () => Actor::current()->can('events.delete') && (bool) rescue(fn () => app(FeatureFlagSettings::class)->show_delete_event_button, false, false))
            ->requiresConfirmation()->modalHeading(__('admin.evt.delete'))
            ->modalDescription(fn (array $arguments) => __('admin.evt.delete_desc', ['title' => Event::query()->whereKey($arguments['id'] ?? null)->value('title')]))
            ->action(fn (array $arguments) => $this->run(fn () => app(EventAdminActions::class)->delete(Event::query()->findOrFail($arguments['id']), Actor::current()), __('admin.evt.deleted')));
    }

    public function toggleActive(string $id): void
    {
        if (! $this->canManage()) {
            return;
        }
        $event = Event::query()->findOrFail($id);
        $this->run(fn () => app(EventAdminActions::class)->toggleActive($event, Actor::current()), __('admin.common.saved'));
    }

    public function updatedCurrentEventId(?string $value): void
    {
        $previous = app(EventSettings::class)->current_event_id;
        if (! $this->canManage() || ! $this->run(fn () => app(EventAdminActions::class)->setCurrent($value ?: null, Actor::current()), __('admin.evt.current_saved'))) {
            $this->currentEventId = $previous; // revert on error (F-ADM-EVT-022 rebuild)
        }
    }

    public function clearDate(): void
    {
        $this->date = null;
    }

    // ---- Data -------------------------------------------------------------------

    protected function getViewData(): array
    {
        $archived = $this->season === 'archive';
        $date = $this->date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) ? $this->date : null;

        $events = Event::query()->where('is_archived', $archived)
            ->when($date, fn ($q) => $q->onQatarDate($date))
            ->with(['ticketTypes' => fn ($q) => $q->orderBy('id')])
            ->orderBy('display_order')->orderBy('event_date')->get();

        $ids = $events->pluck('id');
        // One aggregate per event/type: confirmed holders (U-ADM-EVT-03 uses sold_quantity for held).
        $confirmed = $ids->isEmpty() ? collect() : DB::table('ticket_holders as h')->join('orders as o', 'o.id', '=', 'h.order_id')
            ->whereNull('o.deleted_at')->whereIn('o.event_id', $ids)->where('o.payment_status', 'confirmed')
            ->selectRaw('o.event_id as e, h.ticket_type as t, COUNT(*) as n')->groupBy('o.event_id', 'h.ticket_type')->get()
            ->groupBy('e')->map(fn (Collection $r) => $r->pluck('n', 't')->map(fn ($n) => (int) $n));
        $revenue = $ids->isEmpty() ? collect() : DB::table('orders')->whereNull('deleted_at')->whereIn('event_id', $ids)->where('payment_status', 'confirmed')
            ->selectRaw('event_id, SUM(total_amount) as a')->groupBy('event_id')->pluck('a', 'event_id');
        $scanned = $ids->isEmpty() ? collect() : DB::table('ticket_holders as h')->join('orders as o', 'o.id', '=', 'h.order_id')
            ->whereNull('o.deleted_at')->whereIn('o.event_id', $ids)->whereNotNull('h.checked_in_at')
            ->selectRaw('o.event_id, COUNT(*) as n')->groupBy('o.event_id')->pluck('n', 'event_id');

        return [
            'folders' => $this->folders($events),
            'confirmed' => $confirmed,
            'revenue' => $revenue,
            'scanned' => $scanned,
            'archived' => $archived,
            'filterDate' => $date,
            'selectable' => Event::query()->where('is_archived', false)->orderBy('event_date')->get(['id', 'title', 'event_date', 'end_date', 'is_active']),
            'canManage' => $this->canManage(),
            'today' => QatarClock::today(),
        ];
    }

    /**
     * F-ADM-EVT-005: consecutive months merge into one folder ("يناير - فبراير 2026").
     *
     * @return list<array{label:string, events:Collection<int,Event>}>
     */
    private function folders(Collection $events): array
    {
        $byMonth = $events->sortBy('event_date')->groupBy(function (Event $e) {
            $d = QatarClock::local($e->event_date);

            return $d->year * 12 + $d->month - 1;
        })->sortKeys();

        $runs = [];
        foreach ($byMonth as $idx => $group) {
            $last = array_key_last($runs);
            if ($last !== null && $runs[$last]['to'] === $idx - 1) {
                $runs[$last]['to'] = $idx;
                $runs[$last]['events'] = $runs[$last]['events']->concat($group);
            } else {
                $runs[] = ['from' => $idx, 'to' => $idx, 'events' => $group->values()];
            }
        }

        $name = fn (int $idx) => __('admin.evt.months.'.($idx % 12 + 1));
        $year = fn (int $idx) => intdiv($idx, 12);

        return array_map(function ($r) use ($name, $year) {
            $label = $r['from'] === $r['to']
                ? $name($r['from']).' '.$year($r['from'])
                : ($year($r['from']) === $year($r['to'])
                    ? $name($r['from']).' - '.$name($r['to']).' '.$year($r['to'])
                    : $name($r['from']).' '.$year($r['from']).' - '.$name($r['to']).' '.$year($r['to']));

            return ['label' => $label, 'events' => $r['events']->sortBy([['display_order', 'asc'], ['event_date', 'asc']])->values()];
        }, $runs);
    }
}
