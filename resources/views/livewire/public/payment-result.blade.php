@php
    $tone = [
        'checking' => ['bg-maroon-50 text-maroon-600 ring-maroon-100', 'spinner'],
        'failed' => ['bg-danger/10 text-danger ring-danger/15', 'x-circle'],
        'delayed' => ['bg-gold-100 text-gold-700 ring-gold-200', 'clock'],
        'not_found' => ['bg-bark/10 text-bark ring-bark/10', 'search-x'],
    ][$phase];
    $o = $order;
    $statusTone = ['confirmed' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'cancelled' => 'danger'];
@endphp
<div @if($phase === 'checking') wire:poll.3s="tick" @endif class="mx-auto max-w-3xl px-4 pb-10 pt-10 sm:px-6 sm:pt-14">
    {{-- F-PUB-065 phase headline --}}
    <section class="animate-rise text-center" role="status" aria-live="polite">
        <div class="relative mx-auto size-20">
            @if($phase === 'checking')
                <span class="absolute inset-0 animate-ping rounded-full bg-maroon-200/50"></span>
            @endif
            <span class="relative grid size-20 place-items-center rounded-full ring-8 {{ $tone[0] }}">
                @if($tone[1] === 'spinner')
                    <x-ui.spinner size="34" />
                @else
                    <x-glyph :name="$tone[1]" size="36" stroke="1.4" />
                @endif
            </span>
        </div>
        <h1 class="mt-7 text-2xl font-extrabold tracking-tight text-ink sm:text-3xl">{{ __('public.result.'.$phase.'.title') }}</h1>
        <p class="mx-auto mt-3 max-w-lg leading-relaxed text-ink-soft">{{ __('public.result.'.$phase.'.desc') }}</p>

        @if($phase === 'checking')
            <div class="mx-auto mt-6 h-1 w-48 overflow-hidden rounded-full bg-bark/10">
                <div class="h-full origin-left rounded-full bg-maroon-600 transition-[width] duration-[3s] ease-linear rtl:origin-right" style="width: {{ min(100, (int) round($elapsed / 1.2)) }}%"></div>
            </div>
        @endif
    </section>

    @if($o && $phase !== 'not_found')
        {{-- F-PUB-067 order summary --}}
        <section class="mt-10">
            <x-ui.card>
                <div class="rounded-2xl bg-cream/70 p-5 text-center ring-1 ring-sand/60">
                    <p class="text-xs font-bold tracking-wide text-ink-soft">{{ __('public.result.reference') }}</p>
                    <div class="mt-1.5 flex items-center justify-center gap-2">
                        <span class="select-all font-mono text-xl font-extrabold text-ink" dir="ltr">{{ $reference }}</span>
                        <x-ui.copy :text="$reference" :label="__('public.result.copy_ref')" />
                    </div>
                </div>

                <dl class="mt-6 grid grid-cols-2 gap-x-6 gap-y-5">
                    <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.result.event') }}</dt><dd class="mt-1 font-bold text-ink">{{ $o['event']['title'] ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.result.event_date') }}</dt><dd class="mt-1 font-bold text-ink">{{ \App\Support\Formats::longDate($o['event']['event_date'] ?? null) }}</dd></div>
                    <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.result.event_location') }}</dt><dd class="mt-1 font-bold text-ink">{{ $o['event']['location'] ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.result.quantity') }}</dt><dd class="mt-1 font-bold text-ink">{{ $o['quantity'] }}</dd></div>
                    <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.result.amount') }}</dt><dd class="mt-1 font-extrabold text-maroon-600">{{ \App\Support\Formats::money($o['total_amount']) }}</dd></div>
                    <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.result.method') }}</dt><dd class="mt-1 font-bold text-ink">{{ $o['payment_method'] === 'sadad' ? __('public.result.method_sadad') : __('public.result.method_cash') }}</dd></div>
                    <div class="col-span-2"><dt class="text-xs font-bold text-ink-soft">{{ __('public.result.status') }}</dt>
                        <dd class="mt-1.5"><x-ui.badge :tone="$statusTone[$o['payment_status']] ?? 'warning'">{{ __('enums.public_payment_status.'.$o['payment_status']) }}</x-ui.badge></dd></div>
                </dl>

                {{-- NEW: hold countdown while pending --}}
                @if($o['payment_status'] === 'pending' && $o['payment_expires_at'])
                    <div x-data="{ left: Math.max(0, Math.floor((new Date(@js($o['payment_expires_at'])) - new Date()) / 1000)), fmt(s) { return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0') } }"
                         x-init="setInterval(() => left = Math.max(0, left - 1), 1000)"
                         class="mt-6 flex items-center justify-center gap-2 rounded-full bg-ivory px-4 py-2.5 text-sm font-bold text-ink ring-1 ring-bark/10">
                        <x-glyph name="clock" size="16" class="text-gold-600" />
                        <span x-show="left > 0">{{ __('public.result.hold_left', ['time' => '']) }}<span class="font-mono tabular-nums" dir="ltr" x-text="fmt(left)"></span></span>
                        <span x-show="left === 0" x-cloak class="text-danger">{{ __('public.result.hold_expired') }}</span>
                    </div>
                @endif
            </x-ui.card>
        </section>
    @endif

    @if($phase === 'delayed')
        <div class="mt-6 flex items-start gap-3 rounded-2xl bg-gold-50 p-4 text-gold-900 ring-1 ring-gold-300">
            <x-glyph name="alert" size="20" class="mt-0.5 shrink-0" />
            <p class="text-sm leading-relaxed">{{ __('public.result.delayed.desc') }}</p>
        </div>
    @endif

    {{-- F-PUB-068 actions --}}
    @if($phase !== 'checking')
        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
            @if($phase === 'delayed')
                <x-ui.button variant="primary" size="lg" icon="refresh" wire:click="checkAgain" loading="checkAgain">{{ __('public.result.check_again') }}</x-ui.button>
            @endif
            @if($this->canResume())
                <x-ui.button variant="secondary" size="lg" icon="card" wire:click="payAgain" loading="payAgain">{{ __('public.result.pay_again') }}</x-ui.button>
            @endif
            @if(in_array($phase, ['failed', 'not_found'], true))
                <x-ui.button variant="primary" size="lg" icon="ticket" :href="isset($o['event_id']) ? route('tickets.select', $o['event_id']) : route('home')">{{ __('public.result.new_booking') }}</x-ui.button>
            @endif
            <x-ui.button variant="secondary" size="lg" icon="home" :href="route('home')">{{ __('public.result.back_home') }}</x-ui.button>
        </div>
    @endif
</div>
