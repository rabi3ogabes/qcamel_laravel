<?php

namespace App\Livewire\Staff;

use App\Domain\Analytics\LiveBookingsQuery;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketHolder;
use App\Settings\EventSettings;
use App\Support\Countries;
use App\Support\Formats;
use App\Support\QatarClock;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * LIVE — live bookings board (F-LIVE-001…031). 10 s polling (Reverb when
 * configured) with new-sale / check-in toasts and sounds for BOTH online and
 * POS sales (D: online sales were silent in the original).
 */
#[Layout('components.layouts.staff', ['theme' => 'dark'])]
class LiveBookings extends Component
{
    #[Url(as: 'd')]
    public ?string $date = null;

    #[Url(as: 'all')]
    public bool $all = false;

    #[Session(key: 'live.view')]
    public string $view = 'cards';

    public string $search = '';

    public bool $showDaily = false;

    #[Locked]
    public ?string $sinceOrder = null;

    #[Locked]
    public ?string $sinceCheckin = null;

    public function mount(LiveBookingsQuery $q): void
    {
        if (! $this->date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date)) {
            $this->date = $q->defaultDate();
        }
        $this->sinceOrder = now()->toIso8601String();
        $this->sinceCheckin = now()->toIso8601String();
    }

    public function openDaily(): void
    {
        $this->showDaily = true;
        $this->dispatch('open-dialog', 'daily-sales');
    }

    public function showAll(): void
    {
        $this->all = true;
    }

    public function updatedDate(): void
    {
        $this->all = false;
    }

    /** Poll tick: emit new-sale and check-in notifications since the last tick. */
    public function tick(): void
    {
        $now = now()->toIso8601String();
        $scopeDate = $this->all ? null : $this->date;
        $eventIds = $scopeDate ? Event::query()->onQatarDate($scopeDate)->pluck('id')->all() : null;

        $orders = Order::query()->where('payment_status', 'confirmed')
            ->where('confirmed_at', '>', $this->sinceOrder)
            ->when($eventIds !== null, fn ($q) => $q->whereIn('event_id', $eventIds))
            ->with(['customer:id,name,nationality', 'posUser:id,name,icon', 'event:id,event_date'])
            ->oldest('confirmed_at')->limit(6)->get();
        foreach ($orders as $o) {
            $nat = $o->customer?->nationality;
            if ($o->isPos()) {
                $this->dispatch('live-sale', kind: 'pos',
                    title: __('ops.live.pos_toast_title'),
                    line1: Countries::flag($nat, '🌍').' '.($o->customer?->name ?: __('ops.live.customer')).' ('.($nat ? Countries::label($nat) : __('ops.live.unknown_nationality')).')',
                    line2: '🎫 '.$o->quantity.' '.trans_choice('ops.live.tickets_word', $o->quantity),
                    line3: ($o->posUser?->icon ?: '👤').' '.__('ops.live.pos_toast_by', ['name' => $o->posUser?->name ?: __('ops.live.staff')]));
            } else {
                $this->dispatch('live-sale', kind: 'online',
                    title: Countries::flag($nat, '🌍').' '.__('ops.live.online_toast', ['nationality' => $nat ? Countries::label($nat) : __('ops.live.unknown_nationality')]),
                    line1: __('ops.live.online_toast_line', ['n' => $o->quantity, 'date' => Formats::dayMonth($o->event?->event_date)]),
                    line2: null, line3: null);
            }
        }

        $checkins = TicketHolder::query()->where('is_present', true)->where('checked_in_at', '>', $this->sinceCheckin)
            ->when($eventIds !== null, fn ($q) => $q->whereHas('order', fn ($o) => $o->whereIn('event_id', $eventIds)))
            ->oldest('checked_in_at')->limit(5)->get(['id', 'name', 'ticket_type', 'nationality']);
        foreach ($checkins as $h) {
            $this->dispatch('live-checkin', type: $h->ticket_type->value, name: $h->name, flag: Countries::flag($h->nationality, '🌍'), label: $h->ticket_type->label());
        }

        $this->sinceOrder = $now;
        $this->sinceCheckin = $now;
    }

    public function render(LiveBookingsQuery $q)
    {
        $scopeDate = $this->all ? null : $this->date;
        $season = rescue(fn () => app(EventSettings::class)->season_start_date, null, false);

        return view('livewire.staff.live-bookings', [
            's' => $q->summary($scopeDate),
            'holders' => $q->holders($scopeDate, $this->search),
            'daily' => $this->showDaily ? $q->dailySales($season) : null,
            'dateLabel' => $this->all ? __('common.all') : Formats::longDate($this->date.' 12:00:00'),
            'today' => QatarClock::today(),
        ])->title(__('ops.live.title'));
    }
}
