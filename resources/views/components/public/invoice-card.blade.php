@props(['order', 'id' => null])
@php
    /** @var \App\Models\Order $order */
    $qr = app(\App\Domain\Tickets\QrService::class);
    $paid = $order->payment_status->value === 'confirmed';
    $c = $order->customer;
    $holders = $order->holders->filter(fn ($h) => filled($h->qr_code))->sortBy([['position', 'asc'], ['qr_code', 'asc']])->values();
    $brandName = $brand['festival_name'] ?? __('common.app_name');
    $phone = $c ? \App\Support\PhoneNumber::mask($c->phone, $c->country_code) : '—';
@endphp
{{-- F-PUB-076…080: invoice card shared by the invoice page, confirmation and staff pop-ups --}}
<article @if($id) id="{{ $id }}" @endif {{ $attributes->class('relative overflow-hidden rounded-[2rem] bg-white shadow-elegant ring-1 ring-gold-300/50') }}>
    <div class="h-1.5 w-full bg-gradient-to-l from-gold-400 via-maroon-600 to-gold-400"></div>

    <div class="p-6 sm:p-9">
        <header class="text-center">
            @if($brand['logo_url'] ?? null)
                <img src="{{ $brand['logo_url'] }}" alt="{{ __('common.logo') }}" crossorigin="anonymous" class="mx-auto h-14 w-auto">
            @else
                <span class="mx-auto grid size-14 place-items-center rounded-full bg-maroon-600 text-gold-300 shadow-elegant"><x-glyph name="sparkle" size="26" /></span>
            @endif
            <h2 class="mt-4 text-2xl font-extrabold text-ink">{{ __('public.invoice.title') }}</h2>
            <p class="mt-1 font-mono text-sm text-ink-soft" dir="ltr">{{ $order->booking_reference }}</p>
            @if($paid)
                <x-ui.badge tone="success" icon="check-circle" class="mt-3">{{ __('public.invoice.paid') }}</x-ui.badge>
            @else
                <x-ui.badge tone="warning" icon="clock" class="mt-3">{{ __('enums.public_payment_status.'.$order->publicStatus()) }}</x-ui.badge>
            @endif
        </header>

        <div class="my-7 gold-rule"></div>

        <dl class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
            <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.invoice.name') }}</dt><dd class="mt-1 font-bold text-ink">{{ $c?->name ?? '—' }}</dd></div>
            <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.invoice.phone') }}</dt><dd class="mt-1 font-bold text-ink" dir="ltr">{{ $phone }}</dd></div>
            <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.invoice.nationality') }}</dt><dd class="mt-1 font-bold text-ink">{{ \App\Support\Countries::label($c?->nationality) ?: '-' }}</dd></div>
            <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.invoice.event') }}</dt><dd class="mt-1 font-bold text-ink">{{ $order->event?->title ?? '—' }}</dd></div>
            <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.invoice.date') }}</dt><dd class="mt-1 font-bold text-ink">{{ \App\Support\Formats::longDate($order->event?->event_date) }}</dd></div>
            <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.invoice.type') }}</dt><dd class="mt-1 font-bold text-ink">{{ __('enums.ticket_kind.'.$order->ticket_type->value) }}</dd></div>
        </dl>

        <div @class(['mt-7 grid grid-cols-2 gap-4 rounded-[1.5rem] p-5 ring-1', 'bg-success/[0.06] ring-success/20' => $paid, 'bg-gold-50 ring-gold-200' => ! $paid])>
            <div>
                <p class="text-xs font-bold text-ink-soft">{{ $paid ? __('public.invoice.paid_count') : __('public.invoice.count') }}</p>
                <p class="mt-1 text-3xl font-extrabold tabular-nums text-ink">{{ $order->quantity }}</p>
            </div>
            <div class="text-end">
                <p class="text-xs font-bold text-ink-soft">{{ __('public.invoice.total') }}</p>
                <p class="mt-1 text-2xl font-extrabold tabular-nums text-maroon-600">{{ \App\Support\Formats::money($order->total_amount, short: true) }}</p>
            </div>
        </div>

        <dl class="mt-6 grid gap-3 text-sm sm:grid-cols-3">
            <div class="flex justify-between gap-2 sm:block"><dt class="font-bold text-ink-soft">{{ __('public.invoice.method') }}</dt><dd class="font-bold text-ink sm:mt-1">{{ $order->isPos() || $order->isCashAtVenue() ? __('public.invoice.method_pos') : __('public.invoice.method_sadad') }}</dd></div>
            <div class="flex justify-between gap-2 sm:block"><dt class="font-bold text-ink-soft">{{ __('public.invoice.sadad_no') }}</dt><dd class="font-mono font-bold text-ink sm:mt-1" dir="ltr">{{ $order->payment_id ?: '-' }}</dd></div>
            <div class="flex justify-between gap-2 sm:block"><dt class="font-bold text-ink-soft">{{ __('public.invoice.paid_at') }}</dt><dd class="font-bold text-ink sm:mt-1">{{ $order->confirmed_at ? \App\Support\Formats::longDateTime($order->confirmed_at) : '-' }}</dd></div>
        </dl>

        @if($holders->isNotEmpty())
            <section class="mt-9">
                <h3 class="flex items-center gap-2 font-extrabold text-ink"><x-glyph name="qr" size="20" class="text-terracotta" />{{ __('public.invoice.qr_title') }}</h3>
                <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                    @foreach($holders as $n => $h)
                        @php $used = $h->is_present && $h->checked_in_at; $label = __('enums.ticket_kind.'.$h->ticket_type->value); @endphp
                        <figure wire:key="qr-{{ $h->id }}" class="flex flex-col items-center gap-2">
                            @if($used)
                                <div class="flex aspect-square w-full flex-col items-center justify-center gap-1.5 rounded-2xl bg-danger/[0.06] p-3 text-center ring-1 ring-danger/25">
                                    <x-glyph name="check-circle" size="28" class="text-danger" />
                                    <p class="text-xs font-extrabold text-danger">{{ __('public.invoice.used') }}</p>
                                    <p class="text-[11px] text-ink-soft">{{ \App\Support\Formats::dateTime($h->checked_in_at) }}</p>
                                    @if($h->checked_in_by_name)<p class="text-[11px] text-ink-soft">{{ __('public.invoice.used_by', ['name' => $h->checked_in_by_name]) }}</p>@endif
                                </div>
                            @else
                                <button type="button" x-data @click="$dispatch('open-dialog', 'qr-{{ $h->id }}')" aria-label="{{ __('public.invoice.zoom') }}"
                                    class="group relative grid aspect-square w-full cursor-zoom-in place-items-center rounded-2xl bg-white p-3 ring-1 ring-bark/15 transition duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:scale-[1.04] hover:shadow-soft">
                                    <div class="size-28 [&>svg]:size-full">{!! $qr->svg($h->qr_code, 224) !!}</div>
                                    <span class="absolute inset-0 grid place-items-center rounded-2xl bg-ink/0 text-white opacity-0 transition group-hover:bg-ink/35 group-hover:opacity-100"><x-glyph name="zoom" size="26" /></span>
                                </button>
                                <x-ui.dialog :name="'qr-'.$h->id" max-width="sm:max-w-sm">
                                    <div class="-mx-6 -mt-10 mb-5 h-1.5 bg-gradient-to-l from-gold-400 via-maroon-600 to-gold-400 sm:-mx-8"></div>
                                    <div class="text-center">
                                        <p class="text-lg font-extrabold text-ink">{{ $label }} — <span class="font-mono" dir="ltr">{{ $order->booking_reference }}</span></p>
                                        <p class="mt-2 font-bold text-ink">{{ $h->name ?: $c?->name }}</p>
                                        <p class="font-mono text-sm text-ink-soft" dir="ltr">{{ \App\Support\PhoneNumber::mask($h->phone, $h->country_code) }}</p>
                                        <div class="mx-auto mt-5 size-72 max-w-full rounded-3xl bg-white p-4 ring-1 ring-bark/10 [&>svg]:size-full">{!! $qr->svg($h->qr_code, 576) !!}</div>
                                        <p class="mt-4 text-sm text-ink-soft">{{ __('public.invoice.show_at_gate') }}</p>
                                    </div>
                                </x-ui.dialog>
                            @endif
                            <figcaption class="text-xs font-bold text-ink-soft">{{ $label }} · {{ __('public.invoice.ticket_n', ['n' => $n + 1]) }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif

        <p class="mt-9 text-center text-sm font-bold text-terracotta">{{ __('public.invoice.thanks', ['brand' => $brandName]) }}</p>
    </div>
</article>
