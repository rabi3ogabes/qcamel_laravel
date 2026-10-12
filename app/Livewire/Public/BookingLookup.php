<?php

namespace App\Livewire\Public;

use App\Models\Order;
use App\Support\PhoneNumber;
use App\Support\PublicSession;
use App\Support\Text;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * NEW (PUB upgrades): re-open a booking from another device with the
 * reference + the last 4 phone digits. Rate limited per IP; answers are
 * identical for "no such reference" and "wrong digits".
 */
#[Layout('components.layouts.public', ['track' => false])]
class BookingLookup extends Component
{
    public string $reference = '';

    public string $last4 = '';

    public ?string $error = null;

    public function find()
    {
        $this->error = null;
        $ref = Text::reference(strtoupper(trim($this->reference)));
        $digits = PhoneNumber::normalizeDigits($this->last4);
        if ($ref === null || ! preg_match('/^\d{4}$/', $digits)) {
            $this->error = __('public.lookup.invalid');

            return null;
        }
        $key = 'booking-lookup:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            $this->error = __('errors.too_many_requests');

            return null;
        }
        RateLimiter::hit($key, 600);

        $order = Order::query()->where('booking_reference', $ref)->with('customer:id,phone')->first(['id', 'booking_reference', 'customer_id', 'payment_status']);
        $phone = PhoneNumber::digits($order?->customer?->phone);
        if (! $order || $phone === '' || ! hash_equals(substr($phone, -4), $digits)) {
            $this->error = __('public.lookup.not_found');

            return null;
        }
        RateLimiter::clear($key);
        PublicSession::remember($order->booking_reference);

        return $order->payment_status->value === 'confirmed'
            ? $this->redirectRoute('invoice.show', $order->booking_reference)
            : $this->redirectRoute('payment.result', ['ref' => $order->booking_reference]);
    }

    public function render()
    {
        return view('livewire.public.booking-lookup')->title(__('public.lookup.title'));
    }
}
