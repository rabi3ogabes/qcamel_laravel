@props(['overlay' => false, 'color' => '#D4B78A', 'texture' => null])
@php
    $isAr = app()->getLocale() !== 'en';
    $hasBookings = \App\Support\PublicSession::bookings() !== [];
@endphp
{{-- F-PUB-008 rebuilt as a floating glass "island": header_bg_color tints the pill, the texture repeats inside it. --}}
<header x-data="{ scrolled: false }" x-init="scrolled = window.scrollY > 12" @scroll.window.throttle.100ms="scrolled = window.scrollY > 12"
        class="sticky top-0 z-40 px-3 pt-3 sm:px-6 sm:pt-5 {{ $overlay ? '-mb-[84px] sm:-mb-[96px]' : '' }}">
    <div class="relative mx-auto flex h-[60px] max-w-6xl items-center justify-between gap-3 overflow-hidden rounded-full ps-3 pe-2 ring-1 ring-white/40 backdrop-blur-xl transition-[box-shadow,background-color] duration-700 ease-[cubic-bezier(0.32,0.72,0,1)] sm:h-[68px] sm:ps-5"
         :class="scrolled ? 'shadow-float' : 'shadow-soft'"
         style="background-color: color-mix(in srgb, {{ $color }} 82%, transparent)">
        @if($texture)
            <div class="pointer-events-none absolute inset-0 opacity-60" style="background: linear-gradient(to top, rgb(212 183 138 / .8), rgb(212 183 138 / .4)), url('{{ $texture }}') center / auto 100% repeat-x"></div>
        @endif
        <div class="pointer-events-none absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-terracotta/50 to-transparent"></div>

        <a href="{{ route('home') }}" class="relative flex items-center gap-3" aria-label="{{ $brand['festival_name'] ?? __('public.brand_text') }}">
            @if($brand['logo_url'] ?? null)
                <img src="{{ $brand['logo_url'] }}" alt="{{ __('common.logo') }}" class="h-10 w-auto sm:h-[46px]" fetchpriority="high" decoding="async">
            @else
                <span class="grid size-10 place-items-center rounded-full bg-maroon-600 text-gold-300 shadow-elegant"><x-glyph name="sparkle" size="20" /></span>
                <span class="text-lg font-extrabold tracking-tight text-bark sm:text-xl">{{ __('public.brand_text') }}</span>
            @endif
        </a>

        <nav class="relative flex items-center gap-1.5" aria-label="{{ __('common.menu') }}">
            <a href="{{ route('lang.switch', $isAr ? 'en' : 'ar') }}" hreflang="{{ $isAr ? 'en' : 'ar' }}"
               class="inline-flex h-10 items-center gap-1.5 rounded-full px-3 text-sm font-bold text-bark transition hover:bg-white/50">
                <x-glyph name="globe" size="17" />
                <span>{{ $isAr ? 'EN' : 'عربي' }}</span>
            </a>
            <button type="button" @click="$dispatch('open-dialog', 'my-bookings')"
                    class="relative inline-flex h-10 items-center gap-2 rounded-full bg-white/75 px-3 text-sm font-bold text-ink shadow-soft ring-1 ring-white transition duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-white sm:px-4">
                <x-glyph name="ticket" size="18" />
                <span class="hidden sm:inline">{{ __('public.my_bookings') }}</span>
                @if($hasBookings)
                    <span class="absolute -end-0.5 -top-0.5 size-2.5 rounded-full bg-maroon-600 ring-2 ring-white"></span>
                @endif
            </button>
        </nav>
    </div>
</header>
