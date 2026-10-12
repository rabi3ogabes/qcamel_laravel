@php
    $st = $primary?->publicStatus();
    $isCash = $primary && $primary->payment_method->value === 'cash_pos';
@endphp
<div class="mx-auto max-w-3xl px-4 pb-6 pt-10 sm:px-6 sm:pt-14">
    @if(! $primary)
        <x-ui.card>
            <x-ui.empty icon="search-x" :title="__('public.result.not_found.title')" :hint="__('public.result.not_found.desc')">
                <x-ui.button :href="route('home')" icon="home">{{ __('common.back_home') }}</x-ui.button>
            </x-ui.empty>
        </x-ui.card>
    @elseif($st === 'confirmed')
        {{-- F-PUB-071 confirmed --}}
        <header class="animate-rise text-center">
            <div class="relative mx-auto size-24">
                <span class="absolute inset-0 rounded-full bg-success/15"></span>
                <span class="absolute inset-3 grid place-items-center rounded-full bg-success text-white shadow-[0_18px_40px_-14px_rgb(24_139_72/0.8)]">
                    <x-glyph name="check" size="38" stroke="2.4" />
                </span>
            </div>
            <h1 class="mt-7 text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">{{ __('public.confirmation.confirmed_title') }}</h1>
            <p class="mx-auto mt-3 max-w-md text-ink-soft">{{ __('public.confirmation.confirmed_sub') }}</p>
        </header>
        <div class="reveal mt-10">
            <x-public.invoice-card :order="$primary" />
        </div>
        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <x-ui.button variant="primary" size="lg" icon="download" :href="route('invoice.pdf', $primary->booking_reference)">{{ __('public.invoice.pdf') }}</x-ui.button>
            <x-ui.button variant="secondary" size="lg" icon="receipt" :href="route('invoice.show', $primary->booking_reference)">{{ __('public.open_invoice') }}</x-ui.button>
        </div>
    @elseif(in_array($st, ['failed', 'cancelled'], true))
        <x-ui.card class="animate-rise text-center">
            <span class="mx-auto grid size-20 place-items-center rounded-full bg-danger/10 text-danger ring-8 ring-danger/5"><x-glyph name="x-circle" size="36" stroke="1.4" /></span>
            <h1 class="mt-6 text-2xl font-extrabold text-ink">{{ __('public.confirmation.failed_title') }}</h1>
            <p class="mt-2 text-ink-soft">{{ __('public.confirmation.failed_desc') }}</p>
            <p class="mt-4 font-mono font-bold text-ink" dir="ltr">{{ $primary->booking_reference }}</p>
            <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                <x-ui.button :href="route('payment.result', ['ref' => $primary->booking_reference])" icon="clock">{{ __('public.confirmation.see_status') }}</x-ui.button>
                <x-ui.button variant="secondary" :href="route('home')" icon="home">{{ __('common.back_home') }}</x-ui.button>
            </div>
        </x-ui.card>
    @else
        {{-- F-PUB-072 pending / cash-at-venue --}}
        <header class="animate-rise text-center">
            <span class="mx-auto grid size-20 place-items-center rounded-full bg-gold-100 text-gold-600 ring-8 ring-gold-50"><x-glyph name="clock" size="36" stroke="1.4" /></span>
            <h1 class="mt-6 text-3xl font-extrabold tracking-tight text-ink">{{ $isCash ? __('public.confirmation.cash_title') : __('public.confirmation.pending_title') }}</h1>
            <p class="mt-2 text-ink-soft">{{ $isCash ? __('public.confirmation.cash_desc') : __('public.confirmation.pending_sub') }}</p>
        </header>

        <x-ui.card class="reveal mt-10">
            @unless($isCash)
                <div class="rounded-2xl border-s-4 border-gold-400 bg-gold-50/70 p-4">
                    <p class="font-extrabold text-ink">{{ __('public.confirmation.pending_notice_title') }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-ink-soft">{{ __('public.confirmation.pending_notice') }}</p>
                </div>
            @endunless

            <h2 class="mt-6 font-extrabold text-ink">{{ __('public.confirmation.details') }}</h2>
            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div class="col-span-2 flex items-center justify-between rounded-2xl bg-cream/70 p-4 ring-1 ring-sand/60">
                    <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.result.reference') }}</dt><dd class="mt-1 font-mono text-lg font-extrabold text-ink" dir="ltr">{{ $primary->booking_reference }}</dd></div>
                    <x-ui.copy :text="$primary->booking_reference" :label="__('public.result.copy_ref')" />
                </div>
                <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.confirmation.ticket_type') }}</dt><dd class="mt-1 font-bold text-ink">{{ __('enums.ticket_kind_long.'.$primary->ticket_type->value) }}</dd></div>
                <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.result.quantity') }}</dt><dd class="mt-1 font-bold text-ink">{{ $primary->quantity }}</dd></div>
                <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.result.amount') }}</dt><dd class="mt-1 font-extrabold text-maroon-600">{{ \App\Support\Formats::money($primary->total_amount) }}</dd></div>
                <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.result.method') }}</dt><dd class="mt-1 font-bold text-ink">{{ $isCash ? __('public.checkout.method_cash') : __('public.result.method_sadad') }}</dd></div>
                <div class="col-span-2"><dt class="text-xs font-bold text-ink-soft">{{ __('public.result.status') }}</dt><dd class="mt-1.5"><x-ui.badge tone="warning">{{ __('enums.public_payment_status.'.$st) }}</x-ui.badge></dd></div>
            </dl>

            <h3 class="mt-7 font-extrabold text-ink">{{ __('public.confirmation.next_steps') }}</h3>
            <ol class="mt-3 space-y-2.5">
                @foreach(__('public.confirmation.steps') as $i => $step)
                    <li class="flex items-start gap-3 text-sm leading-relaxed text-ink-soft">
                        <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-maroon-50 text-xs font-extrabold text-maroon-600">{{ $i + 1 }}</span>{{ $step }}
                    </li>
                @endforeach
            </ol>

            @unless($isCash)
                <div class="mt-7"><x-ui.button block :href="route('payment.result', ['ref' => $primary->booking_reference])" icon="refresh">{{ __('public.confirmation.see_status') }}</x-ui.button></div>
            @endunless
        </x-ui.card>
    @endif

    @if($others->isNotEmpty())
        <section class="mt-12">
            <h2 class="mb-4 text-sm font-extrabold text-ink-soft">{{ __('public.confirmation.more') }}</h2>
            <ul class="space-y-2.5">
                @foreach($others as $o)
                    @php $s = $o->publicStatus(); @endphp
                    <li class="flex items-center justify-between gap-3 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-bark/10">
                        <div class="min-w-0">
                            <p class="truncate font-bold text-ink">{{ $o->event?->title }}</p>
                            <p class="font-mono text-xs text-ink-soft" dir="ltr">{{ $o->booking_reference }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ui.badge size="sm" :tone="['confirmed' => 'success', 'pending' => 'warning'][$s] ?? 'danger'">{{ __('enums.public_payment_status.'.$s) }}</x-ui.badge>
                            <a href="{{ $s === 'confirmed' ? route('invoice.show', $o->booking_reference) : route('payment.result', ['ref' => $o->booking_reference]) }}" class="grid size-9 place-items-center rounded-full bg-ivory text-ink ring-1 ring-bark/10 transition hover:bg-cream" aria-label="{{ __('public.open_invoice') }}">
                                <x-glyph name="arrow" size="16" flip />
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="mt-10 text-center">
        <x-ui.button variant="ghost" :href="route('home')" icon="home">{{ __('common.back_home') }}</x-ui.button>
    </div>

    @if($analytics)
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            try { if (window.clarity) { window.clarity('set', 'purchase_amount', @js($analytics['amount'])); window.clarity('set', 'ticket_quantity', @js((string) $analytics['qty'])); window.clarity('set', 'booking_hash', @js($analytics['ref_hash'])); window.clarity('event', 'ticket_purchase'); window.clarity('upgrade', 'ticket_purchase'); } } catch (e) {}
        </script>
    @endif
</div>
