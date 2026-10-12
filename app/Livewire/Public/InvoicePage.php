<?php

namespace App\Livewire\Public;

use App\Domain\Booking\Queries\PublicOrderQueries;
use App\Domain\Shared\Actor;
use App\Support\Text;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * F-PUB-074…083: public invoice by reference (the reference is the
 * capability). Refreshes while visible so a gate scan flips a ticket to
 * "used" without reload. Rate limited by the route (throttle:invoice).
 */
#[Layout('components.layouts.public', ['header' => 'none', 'track' => false])]
class InvoicePage extends Component
{
    #[Locked]
    public string $reference = '';

    public function mount(string $reference): void
    {
        $this->reference = Text::reference($reference) ?? '';
    }

    public function render()
    {
        $order = $this->reference !== '' ? app(PublicOrderQueries::class)->invoice($this->reference, Actor::current()) : null;

        return view('livewire.public.invoice-page', ['order' => $order])
            ->title(__('public.invoice.title').($order ? ' '.$order->booking_reference : ''));
    }
}
