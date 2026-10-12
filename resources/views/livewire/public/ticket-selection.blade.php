@php
    $accent = $brand['header_bg_color'] ?? '#D4B78A';
    $icons = ['vip' => 'crown', 'normal' => 'users', 'parking' => 'car'];
    $tile = ['vip' => 'bg-gold-100 text-gold-700 ring-gold-300/60', 'normal' => 'bg-maroon-50 text-maroon-600 ring-maroon-200', 'parking' => 'bg-gold-50 text-gold-600 ring-gold-200'];
    $low = (int) config('qcamelmc.orders.low_stock_threshold', 10);
@endphp
<div wire:poll.15s.visible="refreshStock" class="pb-36 md:pb-10">
    {{-- F-PUB-028 event header --}}
    <section class="mx-auto max-w-4xl px-4 pt-10 text-center sm:px-6 sm:pt-14">
        <div class="animate-rise">
            <x-ui.eyebrow>{{ $dateLabel }}</x-ui.eyebrow>
            <h1 class="mt-5 text-2xl font-extrabold tracking-tight text-bark sm:text-3xl md:text-4xl">{{ $title }}</h1>
            <div class="mt-3 flex flex-wrap items-center justify-center gap-x-5 gap-y-1 text-ink-soft">
                @if($location)<span class="inline-flex items-center gap-1.5"><x-glyph name="map-pin" size="16" class="text-terracotta" />{{ $location }}</span>@endif
                @if($timeLabel)<span class="inline-flex items-center gap-1.5"><x-glyph name="clock" size="16" class="text-terracotta" />{{ $timeLabel }}</span>@endif
            </div>
        </div>
        <div class="mx-auto mt-10 max-w-md gold-rule"></div>
        <h2 class="mt-10 text-2xl font-extrabold text-ink">{{ __('public.select.title') }}</h2>
        <p class="mt-1 text-ink-soft">{{ __('public.select.subtitle') }}</p>
        <p class="mt-3 inline-flex items-center gap-2 text-xs font-bold text-success">
            <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-success/60"></span><span class="relative inline-flex size-2 rounded-full bg-success"></span></span>
            {{ __('public.select.live') }}
        </p>
    </section>

    {{-- F-PUB-029 ticket type cards --}}
    <section class="mx-auto mt-10 flex max-w-4xl flex-col items-center gap-6 px-4 sm:px-6">
        @forelse($tickets as $i => $t)
            @php
                $q = (int) ($qty[$t['id']] ?? 0);
                [$canInc] = $this->canIncrease($t['id']);
                $kind = \App\Enums\TicketKind::from($t['type']);
            @endphp
            <article wire:key="t-{{ $t['id'] }}" class="reveal w-full sm:w-[90%] lg:w-[80%] {{ $t['sold_out'] ? 'opacity-60' : '' }}" style="transition-delay: {{ $i * 90 }}ms">
                <div class="bezel">
                    <div class="bezel-core overflow-hidden {{ $q > 0 ? 'ring-2 ring-maroon-500/40' : '' }} transition-shadow duration-500">
                        <div class="sadu relative flex items-center justify-between gap-3 px-6 py-3" style="background-color: {{ $accent }}">
                            <h3 class="text-base font-extrabold text-white drop-shadow-sm sm:text-lg">{{ $kind->longLabel() }}</h3>
                            @if($t['sold_out'])
                                <x-ui.badge tone="danger" class="!bg-danger !text-white">{{ __('public.select.sold_out') }}</x-ui.badge>
                            @elseif($t['remaining'] <= $low)
                                <x-ui.badge tone="gold" size="sm">{{ __('public.select.remaining_low', ['n' => $t['remaining']]) }}</x-ui.badge>
                            @endif
                        </div>

                        <div class="mx-6 ticket-perf-line"></div>

                        <div class="flex min-h-40 flex-col gap-5 p-6 sm:min-h-44 sm:flex-row sm:items-center sm:p-7">
                            <div class="flex flex-1 items-start gap-4">
                                <span class="grid size-14 shrink-0 place-items-center rounded-2xl ring-1 {{ $tile[$t['type']] ?? $tile['normal'] }}">
                                    <x-glyph :name="$icons[$t['type']] ?? 'ticket'" size="26" />
                                </span>
                                <div class="min-w-0 space-y-1.5">
                                    <x-ui.badge :tone="$t['type']" size="sm">{{ __('enums.ticket_kind_chip.'.$t['type']) }}</x-ui.badge>
                                    <p class="leading-relaxed text-ink-soft">{{ $t['sold_out'] ? __('public.select.sold_out_line') : $t['description'] }}</p>
                                    <p class="text-xl font-extrabold text-maroon-600">{{ \App\Support\Formats::money($t['price']) }}
                                        <span class="text-xs font-medium text-ink-soft">/ {{ __('public.select.per_ticket') }}</span></p>
                                </div>
                            </div>

                            <div class="flex flex-col items-center gap-2 sm:items-end">
                                @if($t['sold_out'])
                                    <span class="rounded-full bg-bark/10 px-4 py-2 text-sm font-bold text-bark">{{ __('public.select.unavailable') }}</span>
                                @else
                                    <x-ui.stepper :value="$q" dec="dec('{{ $t['id'] }}')" inc="inc('{{ $t['id'] }}')" :can-dec="$q > 0" :can-inc="$canInc" :label="$kind->longLabel()" />
                                    <p class="text-xs text-ink-soft">{{ $t['type'] === 'parking' ? __('public.select.hint_parking') : __('public.select.hint_admission') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <x-ui.card class="w-full sm:w-[90%] lg:w-[80%]">
                <x-ui.empty icon="ticket" :title="__('public.select.tickets_failed')" />
            </x-ui.card>
        @endforelse
    </section>

    {{-- F-PUB-031 total + continue: floating island bar on phones, inline card on desktop --}}
    <div class="fixed inset-x-0 bottom-0 z-30 px-3 pb-[max(.75rem,env(safe-area-inset-bottom))] md:static md:mx-auto md:mt-10 md:max-w-4xl md:px-6 md:pb-0">
        <div class="mx-auto rounded-[1.75rem] bg-white/95 p-3 shadow-float ring-1 ring-bark/10 backdrop-blur-xl md:w-[80%] md:bg-maroon-600/[0.04] md:p-5 md:shadow-none md:backdrop-blur-none">
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1 ps-2">
                    <p class="text-xs font-bold text-ink-soft">{{ __('public.select.total') }}
                        @if($this->count() > 0)<span class="text-maroon-600">· {{ __('public.select.summary_count', ['n' => $this->count()]) }}</span>@endif
                    </p>
                    <p class="truncate text-xl font-extrabold tabular-nums text-ink sm:text-2xl">{{ $total->format() }} <span class="text-sm font-bold text-ink-soft">{{ __('common.qar') }}</span></p>
                </div>
                <x-ui.button variant="primary" size="lg" trail="arrow" wire:click="proceed" loading="proceed" :disabled="$total->isZero()">
                    {{ __('public.select.continue') }}
                </x-ui.button>
            </div>
        </div>
    </div>

    <div class="mx-auto mt-8 flex max-w-4xl justify-center px-4">
        <x-ui.button variant="secondary" :href="route('home')" icon="arrow" class="ltr:[&_svg]:-scale-x-100">{{ __('public.select.back') }}</x-ui.button>
    </div>
</div>
