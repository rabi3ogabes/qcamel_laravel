@php
    // NEW (PUB upgrades): "My bookings" from the session (≤ 10), newest first.
    $refs = array_reverse(\App\Support\PublicSession::bookings());
    $orders = $refs === [] ? collect() : \App\Models\Order::query()
        ->whereIn('booking_reference', $refs)
        ->with('event:id,title,event_date')
        ->get(['id', 'booking_reference', 'event_id', 'payment_status', 'payment_note', 'payment_method', 'total_amount', 'quantity', 'created_at'])
        ->sortBy(fn ($o) => array_search($o->booking_reference, $refs, true));
@endphp
<x-ui.dialog name="my-bookings" :title="__('public.my_bookings')">
    @if($orders->isEmpty())
        <x-ui.empty icon="ticket" :title="__('public.my_bookings_empty')" class="!py-8">
            <x-ui.button variant="secondary" :href="route('booking.lookup')" icon="search" size="sm">{{ __('public.find_booking') }}</x-ui.button>
        </x-ui.empty>
    @else
        <p class="-mt-2 mb-4 text-sm text-ink-soft">{{ __('public.my_bookings_hint') }}</p>
        <ul class="space-y-2.5">
            @foreach($orders as $o)
                @php $st = $o->publicStatus(); @endphp
                <li class="rounded-2xl bg-ivory p-4 ring-1 ring-bark/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-bold text-ink">{{ $o->event?->title ?? '—' }}</p>
                            <p class="mt-0.5 font-mono text-xs text-ink-soft" dir="ltr">{{ $o->booking_reference }}</p>
                        </div>
                        <x-ui.badge size="sm" :tone="['confirmed' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'cancelled' => 'danger'][$st] ?? 'neutral'">
                            {{ __('enums.public_payment_status.'.$st) }}
                        </x-ui.badge>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($st === 'confirmed')
                            <x-ui.button size="sm" variant="primary" :href="route('invoice.show', $o->booking_reference)" icon="receipt">{{ __('public.open_invoice') }}</x-ui.button>
                        @else
                            <x-ui.button size="sm" variant="secondary" :href="route('payment.result', ['ref' => $o->booking_reference])" icon="clock">{{ __('public.open_status') }}</x-ui.button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="mt-5 text-center">
            <a href="{{ route('booking.lookup') }}" class="text-sm font-bold text-maroon-600 underline-offset-4 hover:underline">{{ __('public.find_booking') }}</a>
        </div>
    @endif
</x-ui.dialog>
