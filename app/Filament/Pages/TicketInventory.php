<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\LiveBookingsQuery;
use App\Domain\Booking\Actions\RecycleBinActions;
use App\Domain\Booking\Actions\UpdateTicketTypeAction;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Filament\Concerns\InNavigationGroup;
use App\Models\Event;
use App\Models\TicketType;
use App\Settings\BusinessSettings;
use App\Support\QatarClock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * ADM-TKT — inventory per event: capacity / held / confirmed / remaining from
 * one aggregate, payment breakdown, guarded edit, recount, daily sales.
 */
class TicketInventory extends Page
{
    use InNavigationGroup;

    protected string $view = 'filament.pages.ticket-inventory';

    protected static string $navGroup = 'tickets';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'inventory';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    #[Url]
    public string $season = 'current';

    #[Url(as: 'event')]
    public ?string $eventId = null;

    public bool $showDaily = true;

    public static function getNavigationLabel(): string
    {
        return __('admin.tkt.title');
    }

    public function getTitle(): string
    {
        return __('admin.tkt.title');
    }

    public static function canAccess(): bool
    {
        return Actor::current()->can('tickets.view');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recount')->label(__('admin.tkt.recount'))->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->tooltip(__('admin.tkt.recount_hint'))
                ->visible(fn () => Actor::current()->can('stock.recount'))
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $n = app(RecycleBinActions::class)->recount(Actor::current());
                        Notification::make()->success()->title($n > 0 ? __('tickets.recount_fixed', ['n' => $n]) : __('tickets.recount_ok'))->send();
                    } catch (DomainError $e) {
                        Notification::make()->danger()->title($e->userMessage())->send();
                    }
                }),
        ];
    }

    public function editTicketAction(): Action
    {
        return Action::make('editTicket')->label(__('common.edit'))->icon(Heroicon::OutlinedPencilSquare)->iconButton()->color('gray')
            ->visible(fn () => Actor::current()->can('tickets.edit'))
            ->modalHeading(__('admin.tkt.edit'))
            ->fillForm(function (array $arguments) {
                $t = TicketType::query()->findOrFail($arguments['id']);

                return ['type' => $t->type->longLabel(), 'price' => (string) $t->price, 'available_quantity' => $t->available_quantity, 'description' => $t->description];
            })
            ->schema(fn (array $arguments) => [
                TextInput::make('type')->label(__('admin.tkt.type'))->disabled()->dehydrated(false),
                TextInput::make('price')->label(__('admin.tkt.price'))->numeric()->minValue(0)->step('0.01')->required()->suffix(__('common.qar_short')),
                TextInput::make('available_quantity')->label(__('admin.tkt.capacity'))->integer()->required()
                    ->minValue(fn () => (int) TicketType::query()->whereKey($arguments['id'] ?? null)->value('sold_quantity'))
                    ->helperText(fn () => __('admin.tkt.held_hint', ['n' => (int) TicketType::query()->whereKey($arguments['id'] ?? null)->value('sold_quantity')])),
                Textarea::make('description')->label(__('admin.tkt.description'))->rows(3)->maxLength(500)
                    ->placeholder(__('admin.tkt.description_ph'))->helperText(__('admin.tkt.description_hint')),
            ])
            ->action(function (array $data, array $arguments) {
                try {
                    $t = TicketType::query()->findOrFail($arguments['id']);
                    app(UpdateTicketTypeAction::class)->execute($t, (string) $data['price'], (int) $data['available_quantity'], $data['description'] ?? null, Actor::current());
                    Notification::make()->success()->title(__('admin.common.saved'))->send();
                } catch (DomainError $e) {
                    Notification::make()->danger()->title($e->userMessage())->send();
                }
            });
    }

    protected function getViewData(): array
    {
        $events = Event::query()->where('is_archived', $this->season === 'archive')->orderBy('event_date')->orderBy('display_order')->get(['id', 'title', 'event_date', 'end_date', 'location']);
        if (! $this->eventId || ! $events->contains('id', $this->eventId)) {
            $today = QatarClock::today();
            $this->eventId = ($events->first(fn ($e) => $e->lastDay() >= $today) ?? $events->first())?->id;
        }
        $event = $events->firstWhere('id', $this->eventId);

        $types = $event ? TicketType::query()->where('event_id', $event->id)->get()->sortBy(fn ($t) => $t->type->sortWeight())->values() : collect();
        $breakdown = $event ? DB::table('ticket_holders as h')->join('orders as o', 'o.id', '=', 'h.order_id')->whereNull('o.deleted_at')
            ->where('o.event_id', $event->id)
            ->selectRaw('h.ticket_type as t, o.payment_status as s, COUNT(*) as n')->groupBy('h.ticket_type', 'o.payment_status')->get()
            ->groupBy('t')->map(fn ($rows) => $rows->pluck('n', 's')->map(fn ($n) => (int) $n)->all())->all() : [];
        $revenue = $event ? DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereNull('o.deleted_at')
            ->where('o.event_id', $event->id)->where('o.payment_status', 'confirmed')
            ->selectRaw('i.type as t, SUM(i.quantity) as q, SUM(i.line_total) as a')->groupBy('i.type')->get()->keyBy('t')->all() : [];

        return [
            'events' => $events,
            'event' => $event,
            'types' => $types,
            'breakdown' => $breakdown,
            'revenue' => $revenue,
            'daily' => $this->showDaily ? app(LiveBookingsQuery::class)->dailySales(null, $this->season === 'archive') : null,
            'low' => (int) rescue(fn () => app(BusinessSettings::class)->low_stock_threshold, 10, false),
        ];
    }
}
