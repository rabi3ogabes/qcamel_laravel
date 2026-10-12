<?php

namespace App\Livewire\Public;

use App\Models\Order;
use App\Support\PublicSession;
use App\Support\Text;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * F-PUB-070…073 rebuild: the primary booking (route/`?ref=` or the latest
 * remembered one) plus the session's other bookings (≤ 10), newest first.
 */
#[Layout('components.layouts.public', ['track' => false])]
class Confirmation extends Component
{
    #[Locked]
    public ?string $reference = null;

    public function mount(?string $reference = null)
    {
        $ref = Text::reference($reference ?? request()->query('ref')) ?? PublicSession::latest();
        if ($ref === null) {
            return redirect()->route('home');
        }
        $this->reference = $ref;
        PublicSession::remember($ref);

        return null;
    }

    public function render()
    {
        $refs = array_reverse(PublicSession::bookings());
        $orders = Order::query()
            ->whereIn('booking_reference', $refs)
            ->with(['customer', 'event', 'holders'])
            ->get()
            ->sortBy(fn (Order $o) => array_search($o->booking_reference, $refs, true))
            ->values();
        $primary = $orders->firstWhere('booking_reference', $this->reference);
        $others = $orders->reject(fn (Order $o) => $o->booking_reference === $this->reference)->values();

        // F-PUB-073 rebuild: one purchase event per order (session flag), hashed reference.
        $analytics = [];
        if ($primary && $primary->payment_status->value === 'confirmed' && ! session()->has('tracked.'.$primary->booking_reference)) {
            session()->put('tracked.'.$primary->booking_reference, true);
            $analytics = ['ref_hash' => substr(hash('sha256', $primary->booking_reference), 0, 16), 'amount' => (string) $primary->total_amount, 'qty' => $primary->quantity, 'method' => $primary->payment_method->value];
        }

        return view('livewire.public.confirmation', compact('primary', 'others', 'analytics'))
            ->title($primary && $primary->payment_status->value === 'confirmed' ? __('public.confirmation.confirmed_title') : __('public.confirmation.pending_title'));
    }
}
