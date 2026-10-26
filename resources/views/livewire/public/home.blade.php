@php
    $name = $brand['festival_name'] ?? __('public.home.title_fallback');
    $heroText = trim((string) ($brand['hero_text'] ?? ''));
    $hero = $brand['hero_image_url'] ?? asset('images/mafareed/hero.jpg');
    $cardBg = asset('images/mafareed/card-camels.jpg');
@endphp
<div class="fx-site">
    {{-- F-PUB-009 hero (qcamelmc.org): festival photo under the navy → turquoise shade, title at the bottom start --}}
    <section class="fx-hero relative overflow-hidden" aria-labelledby="festival-title">
        <img src="{{ $hero }}" alt="{{ __('public.home.hero_alt', ['name' => $name]) }}" class="absolute inset-0 h-full w-full object-cover object-center" fetchpriority="high" decoding="async">
        <div class="fx-hero-shade absolute inset-0"></div>
        <div class="fx-hero relative mx-auto flex max-w-7xl flex-col justify-end px-5 py-14 text-white sm:px-8 sm:py-20">
            <p class="animate-rise mb-4 text-sm font-semibold">{{ __('public.home.hero_eyebrow') }}</p>
            <h1 id="festival-title" class="animate-rise max-w-3xl whitespace-pre-line text-3xl font-bold leading-tight sm:text-5xl" style="animation-delay: 80ms">{{ $heroText !== '' ? $heroText : $name }}</h1>
        </div>
    </section>

    {{-- F-PUB-018 events grid --}}
    <section id="events" class="mx-auto max-w-7xl px-5 pb-24 pt-10 sm:px-8 sm:pb-32 sm:pt-14" aria-labelledby="events-title">
        <div class="mb-8 flex flex-wrap items-center gap-3">
            <x-glyph name="ticket" size="26" stroke="1.75" class="shrink-0 text-fx-clay" />
            <h2 id="events-title" class="text-2xl font-bold text-fx-clay">{{ __('public.home.events_title') }}</h2>
            <img src="{{ asset('images/mafareed/divider.webp') }}" alt="" aria-hidden="true" class="fx-divider ms-auto">
        </div>

        @if(empty($events))
            <div class="mx-auto max-w-xl rounded-lg border border-fx-line bg-white p-8 shadow-sm">
                <x-ui.empty icon="calendar" :title="__('public.home.empty')" :hint="__('public.home.empty_hint')" />
            </div>
        @else
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach($events as $i => $e)
                    @php $open = now()->lt($e['cutoff']); @endphp
                    <article wire:key="ev-{{ $e['id'] }}" class="relative flex min-w-0 flex-col overflow-hidden rounded-lg border border-fx-line bg-white shadow-sm transition-[box-shadow,transform] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-y-0.5 hover:shadow-md">
                        <div class="relative flex flex-1 flex-col p-5 sm:p-6">
                            <img src="{{ $e['cover'] ?? $cardBg }}" alt="" aria-hidden="true" loading="lazy" decoding="async" class="pointer-events-none absolute inset-0 h-full w-full object-cover object-[center_35%]">
                            <div class="fx-card-wash pointer-events-none absolute inset-0"></div>

                            @if($e['is_today'])
                                <span class="absolute start-4 top-4 z-10 rounded-full bg-fx-clay px-3 py-1 text-xs font-bold text-white shadow-sm">{{ __('public.home.today') }}</span>
                            @endif

                            <div class="relative z-10 flex flex-1 flex-col text-center">
                                <h3 class="break-words text-2xl font-bold text-fx-clay">{{ $e['title'] }}</h3>
                                @if($e['description'])
                                    <p class="mt-3 line-clamp-3 text-base leading-7 text-black">{{ $e['description'] }}</p>
                                @endif

                                <div class="my-6 space-y-3 text-sm text-black">
                                    <p class="flex items-start justify-center gap-2"><x-glyph name="calendar" size="16" class="mt-0.5 shrink-0" />{{ $e['date_label'] }}</p>
                                    @if($e['start'] || $e['end'])
                                        <p dir="ltr" class="tabular-nums">{{ $e['start'] }}@if($e['start'] && $e['end']) – @endif{{ $e['end'] }}</p>
                                    @endif
                                    @if($e['location'])
                                        <p class="flex items-start justify-center gap-2"><x-glyph name="map-pin" size="16" class="mt-0.5 shrink-0" /><span class="break-words">{{ $e['location'] }}</span></p>
                                    @endif
                                    @if($e['from_price'] !== null)
                                        <p class="text-xs text-black/60">{{ __('public.home.from_price') }} <span class="font-bold text-fx-ink">{{ \App\Support\Formats::money($e['from_price']) }}</span></p>
                                    @endif
                                </div>

                                <div class="mt-auto">
                                    @if($open)
                                        <a href="{{ route('tickets.select', $e['id']) }}" class="fx-btn inline-flex h-11 w-full items-center justify-center gap-2 whitespace-nowrap rounded-md px-8 text-sm">
                                            <x-glyph name="ticket" size="16" />{{ __('public.home.book_now') }}
                                        </a>
                                    @else
                                        <span class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-md border-2 border-fx-line bg-neutral-100 px-8 text-sm font-bold text-neutral-500">
                                            <x-glyph name="lock" size="16" />{{ __('public.home.closed') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- F-PUB-022 pagination (9 per page, only when needed) --}}
            @if($pages > 1)
                <nav class="mt-12 flex flex-col items-center gap-4" aria-label="pagination">
                    <p class="text-sm font-bold text-fx-ink">{{ __('common.page_of', ['page' => $page, 'total' => $pages]) }}</p>
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <button type="button" wire:click="goTo({{ $page - 1 }})" @disabled($page <= 1) class="inline-flex h-10 items-center gap-1 rounded-md px-4 text-sm font-bold text-fx-ink ring-1 ring-fx-clay/60 transition hover:bg-fx-clay/10 disabled:opacity-40">
                            <x-glyph name="chevron" size="16" :flip="false" class="ltr:-scale-x-100" />{{ __('common.previous') }}
                        </button>
                        @for($p = 1; $p <= $pages; $p++)
                            <button type="button" wire:click="goTo({{ $p }})" @class(['size-10 rounded-md text-sm font-bold transition', 'bg-fx-clay text-white shadow-sm' => $p === $page, 'text-fx-ink ring-1 ring-fx-clay/60 hover:bg-fx-clay/10' => $p !== $page]) @if($p === $page) aria-current="page" @endif>{{ $p }}</button>
                        @endfor
                        <button type="button" wire:click="goTo({{ $page + 1 }})" @disabled($page >= $pages) class="inline-flex h-10 items-center gap-1 rounded-md px-4 text-sm font-bold text-fx-ink ring-1 ring-fx-clay/60 transition hover:bg-fx-clay/10 disabled:opacity-40">
                            {{ __('common.next') }}<x-glyph name="chevron" size="16" class="rtl:-scale-x-100" />
                        </button>
                    </div>
                </nav>
            @endif
        @endif
    </section>
</div>
