<?php

namespace App\Livewire\Public;

use App\Livewire\Public\Concerns\ManagesCart;
use App\Models\Event;
use App\Support\Formats;
use App\Support\PublicSession;
use App\Support\PublicSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * F-PUB-023…033: ticket selection with server-side access checks, live
 * availability (poll; Reverb when configured) and the session cart.
 */
#[Layout('components.layouts.public', ['beforeFooter' => true])]
class TicketSelection extends Component
{
    use ManagesCart;

    #[Locked]
    public string $title = '';

    #[Locked]
    public ?string $location = null;

    #[Locked]
    public string $dateLabel = '';

    #[Locked]
    public ?string $timeLabel = null;

    public function mount(string $event)
    {
        $model = Event::query()->find($event);
        abort_if(! $model, 404);

        // F-PUB-023 (a) → (b) → (c), redirect home with a flashed toast.
        if (! $model->is_active || $model->is_archived) {
            return $this->leave('public.select.not_bookable');
        }
        $current = PublicSettings::get('current_event_id');
        if ($current && $current !== $model->id) {
            return $this->leave('public.select.not_current');
        }
        if (! $model->canPurchaseTickets()) {
            return $this->leave('public.select.not_bookable');
        }

        $this->eventId = $model->id;
        $this->title = $model->title;
        $this->location = $model->location;
        $this->dateLabel = Formats::longDate($model->event_date);
        $start = Formats::time12($model->start_time);
        $end = Formats::time12($model->end_time);
        $this->timeLabel = trim($start.($start && $end ? ' – ' : '').$end) ?: null;

        $cart = PublicSession::cart();
        if ($cart['event_id'] === $model->id) {
            $this->qty = $cart['lines'];
        }
        $this->loadAvailability($model);

        if (count($this->tickets) !== 3) {
            $this->toast('warning', __('public.select.incomplete_types'));
        }
        // A remembered cart can exceed what is left now — trim it quietly.
        foreach ($this->tickets as $t) {
            if (isset($this->qty[$t['id']]) && $this->qty[$t['id']] > $t['remaining']) {
                $this->qty[$t['id']] = $t['remaining'];
            }
        }
        $this->qty = array_filter($this->qty, fn ($q) => $q > 0);
    }

    private function leave(string $key)
    {
        session()->flash('toast', ['type' => 'error', 'message' => __($key)]);

        return $this->redirectRoute('home');
    }

    /** F-PUB-027: refresh remaining counts (poll / realtime). */
    public function refreshStock(): void
    {
        $event = Event::query()->find($this->eventId);
        if (! $event || ! $event->isPublicSellable()) {
            $this->leave('public.select.not_bookable');

            return;
        }
        $this->loadAvailability($event, expire: false);
    }

    public function inc(string $id): void
    {
        if ($this->increase($id)) {
            $this->dispatch('sfx-tap');
        }
    }

    public function dec(string $id): void
    {
        $this->decrease($id);
    }

    /** F-PUB-031: store the selection (ids + quantities only) and continue. */
    public function proceed()
    {
        $this->refreshStock();
        if ($this->count() === 0) {
            $this->toast('error', __('public.select.pick_one'));

            return null;
        }
        foreach ($this->tickets as $t) {
            if (($this->qty[$t['id']] ?? 0) > $t['remaining']) {
                $this->toast('error', __('public.select.max_available'));

                return null;
            }
        }
        PublicSession::putCart($this->eventId, $this->qty);

        return $this->redirectRoute('checkout');
    }

    public function render()
    {
        return view('livewire.public.ticket-selection', [
            'total' => $this->total(),
            'admission' => $this->admissionTotal(),
        ])->title($this->title);
    }
}
