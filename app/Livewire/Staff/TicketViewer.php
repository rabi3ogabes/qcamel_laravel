<?php

namespace App\Livewire\Staff;

use App\Domain\Booking\Queries\PublicOrderQueries;
use App\Domain\Messaging\AutomationRelay;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Domain\Tickets\CheckInService;
use App\Domain\Tickets\QrService;
use App\Models\TicketHolder;
use App\Support\Text;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * F-QR-013…024: staff ticket viewer & printer. One multi-page print layout
 * (one ticket per page) instead of n pop-ups; live presence via polling.
 */
#[Layout('components.layouts.staff')]
class TicketViewer extends Component
{
    #[Locked]
    public string $reference = '';

    #[Url(as: 'only')]
    public ?string $only = null;

    #[Url(as: 'print')]
    public bool $autoPrint = false;

    public function mount(string $reference): void
    {
        $this->reference = Text::reference($reference) ?? '';
    }

    public function regenerate(QrService $qr): void
    {
        if (! Actor::current()->isAdmin()) {
            $this->dispatch('toast', type: 'error', message: __('errors.forbidden'));

            return;
        }
        try {
            $res = $qr->regenerateBooking($this->reference);
            $this->dispatch('toast', type: 'success', message: __('ops.viewer.regenerated', ['n' => $res['updated'] ?? 0]));
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e instanceof DomainError ? $e->userMessage() : __('ops.viewer.qr_failed'));
        }
    }

    public function sendOne(string $holderId, AutomationRelay $relay): void
    {
        $h = TicketHolder::query()->with('order')->find($holderId);
        if (! $h || $h->order?->booking_reference !== $this->reference) {
            return;
        }
        $res = rescue(fn () => $relay->send('send_single_ticket', $h->order, $h, Actor::current()), ['success' => false], false);
        ($res['success'] ?? false)
            ? $this->dispatch('toast', type: 'success', message: $res['message'] ?? __('ops.pos.sent_whatsapp'))
            : $this->dispatch('toast', type: 'error', message: $res['message'] ?? __('errors.default'));
    }

    public function undo(string $holderId, CheckInService $svc): void
    {
        try {
            $res = $svc->reset($holderId, Actor::current(), 'ticket viewer');
            $this->dispatch('toast', type: ($res['success'] ?? false) ? 'success' : 'error', message: $res['message'] ?? __('ops.scanner.undo_failed'));
        } catch (DomainError $e) {
            $this->dispatch('toast', type: 'error', message: $e->userMessage());
        }
    }

    public function render(PublicOrderQueries $q, QrService $qr)
    {
        $actor = Actor::current();
        $order = $this->reference !== '' ? $q->invoice($this->reference, $actor) : null;
        $holders = $order?->holders->sortBy([['position', 'asc'], ['qr_code', 'asc']])->values() ?? collect();
        if ($this->only) {
            $holders = $holders->where('id', $this->only)->values();
        }

        return view('livewire.staff.ticket-viewer', [
            'order' => $order,
            'holders' => $holders,
            'qrs' => $holders->mapWithKeys(fn ($h) => [$h->id => $h->qr_code ? $qr->svg(CheckInService::signedPayload($h->code()), 300, 'H', 2) : null])->all(),
            'isAdmin' => $actor->isAdmin(),
            'pii' => $actor->canSeePii(),
            'legacy' => $order?->isLegacyReference() ?? false,
        ])->title(__('ops.viewer.title').' '.$this->reference);
    }
}
