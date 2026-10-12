@php
    $heroText = trim((string) ($brand['hero_text'] ?? ''));
    $accent = $brand['header_bg_color'] ?? '#A85740';
@endphp
<div>
    {{-- F-PUB-009 hero: settings image (or a crafted desert scene) under the terracotta → cream overlay --}}
    <section class="relative isolate overflow-hidden">
        @if($brand['hero_image_url'] ?? null)
            <img src="{{ $brand['hero_image_url'] }}" alt="" class="block w-full object-contain" fetchpriority="high" decoding="async">
        @else
            <div class="relative h-[78dvh] min-h-[520px] w-full overflow-hidden bg-gradient-to-b from-terracotta via-[#c98a5f] to-sand-light" aria-hidden="true">
                <div class="absolute start-1/2 top-[22%] size-[38vmin] -translate-x-1/2 rounded-full bg-gradient-to-b from-gold-200 to-gold-400 opacity-90 blur-[1px] rtl:translate-x-1/2"></div>
                <div class="absolute start-1/2 top-[22%] size-[60vmin] -translate-x-1/2 rounded-full bg-gold-300/30 blur-3xl rtl:translate-x-1/2"></div>
                <svg class="absolute inset-x-0 bottom-0 h-[55%] w-full" viewBox="0 0 1440 520" preserveAspectRatio="none">
                    <path d="M0 260C180 200 320 190 520 250s360 90 560 30 300-80 360-70v330H0Z" fill="#c4865c" fill-opacity=".55"/>
                    <path d="M0 330c220-60 420-70 640-10s420 70 800-20v220H0Z" fill="#b8754f" fill-opacity=".7"/>
                    <path d="M0 410c260-40 520-30 760 10s440 20 680-30v130H0Z" fill="#e8d8bd"/>
                </svg>
            </div>
        @endif
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-terracotta/60 via-terracotta/40 to-cream"></div>

        <div class="absolute inset-0 flex items-center justify-center px-6 pt-20">
            <div class="max-w-3xl text-center">
                @if($heroText !== '')
                    <h1 class="whitespace-pre-line text-4xl font-extrabold leading-[1.15] text-white drop-shadow-[0_4px_24px_rgb(42_26_18/0.45)] md:text-6xl">{{ $heroText }}</h1>
                @else
                    <div class="animate-rise space-y-5">
                        <x-ui.eyebrow class="!bg-white/20 !text-white !ring-white/30">{{ $brand['season_label'] ?? __('public.home.eyebrow') }}</x-ui.eyebrow>
                        <h1 class="text-4xl font-extrabold leading-[1.1] tracking-tight text-white drop-shadow-[0_4px_24px_rgb(42_26_18/0.4)] sm:text-5xl md:text-7xl">
                            {{ $brand['festival_name'] ?? __('public.home.title_fallback') }}
                        </h1>
                        <p class="mx-auto max-w-xl text-base text-white/90 drop-shadow md:text-lg">{{ __('public.home.subtitle') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- F-PUB-018 events grid overlapping the hero --}}
    <section id="events" class="relative z-10 mx-auto -mt-24 max-w-7xl px-4 md:-mt-32 sm:px-6" aria-labelledby="events-title">
        <h2 id="events-title" class="sr-only">{{ __('public.home.events_title') }}</h2>

        @if(empty($events))
            <x-ui.card class="mx-auto max-w-xl">
                <x-ui.empty icon="calendar" :title="__('public.home.empty')" :hint="__('public.home.empty_hint')" />
            </x-ui.card>
        @else
            <div class="flex flex-wrap justify-center gap-8">
                @foreach($events as $i => $e)
                    @php $open = now()->lt($e['cutoff']); @endphp
                    <article wire:key="ev-{{ $e['id'] }}" class="reveal group w-full md:w-[calc(50%-1rem)] lg:w-[calc(33.333%-1.334rem)]" style="transition-delay: {{ min($i, 5) * 80 }}ms">
                        <div class="bezel h-full transition-transform duration-700 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-y-1">
                            <div class="bezel-core flex h-full flex-col overflow-hidden ring-1 ring-sand/70 transition-shadow duration-700 group-hover:shadow-float">
                                <div class="h-2 w-full" style="background-color: {{ $accent }}"></div>

                                <div class="relative">
                                    @if($e['cover'])
                                        <img src="{{ $e['cover'] }}" alt="" loading="lazy" decoding="async" class="h-64 w-full object-cover transition-transform duration-[1.2s] ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:scale-[1.03]">
                                    @else
                                        <div class="sadu relative flex h-40 items-end bg-gradient-to-br from-maroon-700 via-maroon-600 to-terracotta p-6">
                                            <x-glyph name="ticket" size="40" stroke="1" class="text-gold-300/70" />
                                        </div>
                                    @endif
                                    <div class="absolute start-4 top-4 flex flex-col items-center rounded-2xl bg-white/95 px-3 py-2 text-center shadow-soft ring-1 ring-sand/60">
                                        <span class="text-[11px] font-bold text-terracotta">{{ $e['weekday'] }}</span>
                                        <span class="text-base font-extrabold leading-tight text-ink">{{ $e['day'] }}</span>
                                    </div>
                                    @if($e['is_today'])
                                        <x-ui.badge tone="gold" class="absolute end-4 top-4" icon="sparkle">{{ __('public.home.today') }}</x-ui.badge>
                                    @endif
                                </div>

                                <div class="flex flex-1 flex-col p-7 sm:p-8">
                                    <h3 class="text-center text-3xl font-extrabold leading-snug text-bark">{{ $e['title'] }}</h3>
                                    @if($e['description'])
                                        <p class="mt-3 line-clamp-3 text-center font-bold leading-relaxed text-bark/85">{{ $e['description'] }}</p>
                                    @endif

                                    <div class="my-6 gold-rule"></div>

                                    <dl class="space-y-3.5 text-[15px]">
                                        @if($e['start'] || $e['end'])
                                            <div class="flex items-start gap-3">
                                                <dt class="flex shrink-0 items-center gap-2 font-bold text-ink"><x-glyph name="clock" size="18" class="text-terracotta" />{{ __('public.home.timing') }}</dt>
                                                <dd class="text-clay">
                                                    @if($e['start']){{ __('public.home.start', ['t' => $e['start']]) }}@endif
                                                    @if($e['start'] && $e['end'])<span class="text-sand"> | </span>@endif
                                                    @if($e['end']){{ __('public.home.end', ['t' => $e['end']]) }}@endif
                                                </dd>
                                            </div>
                                        @endif
                                        @if($e['location'])
                                            <div class="flex items-start gap-3">
                                                <dt class="flex shrink-0 items-center gap-2 font-bold text-ink"><x-glyph name="map-pin" size="18" class="text-terracotta" />{{ __('public.home.location') }}</dt>
                                                <dd class="text-clay">{{ $e['location'] }}</dd>
                                            </div>
                                        @endif
                                    </dl>

                                    <div class="mt-auto pt-7">
                                        @if($e['from_price'] !== null)
                                            <p class="mb-3 text-center text-sm text-ink-soft">{{ __('public.home.from_price') }}
                                                <span class="text-lg font-extrabold text-maroon-600">{{ \App\Support\Formats::money($e['from_price']) }}</span></p>
                                        @endif
                                        @if($open)
                                            <x-ui.button :href="route('tickets.select', $e['id'])" variant="primary" size="lg" block icon="ticket" trail="arrow" class="!bg-terracotta hover:!bg-[#94492f]">
                                                {{ __('public.home.book_now') }}
                                            </x-ui.button>
                                        @else
                                            <x-ui.button variant="dark" size="lg" block icon="lock" disabled class="!bg-bark !opacity-60">{{ __('public.home.closed') }}</x-ui.button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- F-PUB-022 pagination (9 per page, only when needed) --}}
            @if($pages > 1)
                <nav class="mt-12 flex flex-col items-center gap-4" aria-label="pagination">
                    <p class="text-sm font-bold text-bark">{{ __('common.page_of', ['page' => $page, 'total' => $pages]) }}</p>
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <button type="button" wire:click="goTo({{ $page - 1 }})" @disabled($page <= 1) class="inline-flex h-10 items-center gap-1 rounded-full px-4 text-sm font-bold text-bark ring-1 ring-terracotta/60 transition hover:bg-white disabled:opacity-40">
                            <x-glyph name="chevron" size="16" :flip="false" class="ltr:-scale-x-100" />{{ __('common.previous') }}
                        </button>
                        @for($p = 1; $p <= $pages; $p++)
                            <button type="button" wire:click="goTo({{ $p }})" @class(['size-10 rounded-full text-sm font-bold transition', 'bg-terracotta text-white shadow-soft' => $p === $page, 'text-bark ring-1 ring-terracotta/60 hover:bg-white' => $p !== $page]) @if($p === $page) aria-current="page" @endif>{{ $p }}</button>
                        @endfor
                        <button type="button" wire:click="goTo({{ $page + 1 }})" @disabled($page >= $pages) class="inline-flex h-10 items-center gap-1 rounded-full px-4 text-sm font-bold text-bark ring-1 ring-terracotta/60 transition hover:bg-white disabled:opacity-40">
                            {{ __('common.next') }}<x-glyph name="chevron" size="16" class="rtl:-scale-x-100" />
                        </button>
                    </div>
                </nav>
            @endif
        @endif
    </section>

    {{-- How it works --}}
    <section class="mx-auto mt-28 max-w-6xl px-4 sm:px-6">
        <div class="reveal mb-10 text-center">
            <x-ui.eyebrow>{{ __('public.footer.tagline') }}</x-ui.eyebrow>
            <h2 class="mt-4 text-3xl font-extrabold tracking-tight text-bark md:text-4xl">{{ __('public.home.steps_title') }}</h2>
        </div>
        <ol class="grid gap-5 md:grid-cols-3">
            @foreach(__('public.home.steps') as $n => $step)
                <li class="reveal" style="transition-delay: {{ $n * 100 }}ms">
                    <x-ui.card pad="p-7" class="h-full">
                        <div class="flex items-center gap-4">
                            <span class="grid size-12 place-items-center rounded-full bg-maroon-600 text-white shadow-elegant">
                                <x-glyph :name="['ticket', 'shield', 'qr'][$n]" size="22" />
                            </span>
                            <span class="font-mono text-sm font-bold text-sand">0{{ $n + 1 }}</span>
                        </div>
                        <h3 class="mt-5 text-xl font-extrabold text-ink">{{ $step['t'] }}</h3>
                        <p class="mt-2 leading-relaxed text-ink-soft">{{ $step['d'] }}</p>
                    </x-ui.card>
                </li>
            @endforeach
        </ol>
    </section>
</div>
