@props(['overlay' => false, 'color' => '#D4B78A', 'texture' => null])
@php
    $isAr = app()->getLocale() !== 'en';
    $hasBookings = \App\Support\PublicSession::bookings() !== [];
    $name = $brand['festival_name'] ?? __('public.home.title_fallback');
    $logo = $brand['logo_url'] ?? asset('images/mafareed/logo.png');
@endphp
{{-- F-PUB-008 festival header (qcamelmc.org): white bar, festival logo at the start, club mark at the end,
     sadu heritage strip underneath. The admin "header image" replaces the strip's tile. --}}
<header class="fx-site relative z-40 bg-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-3 sm:px-8">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3" aria-label="{{ $name }}">
            <img src="{{ $logo }}" alt="{{ $name }}" class="h-16 w-auto min-w-[110px] shrink-0 object-contain sm:h-20" fetchpriority="high" decoding="async">
        </a>

        <div class="flex shrink-0 items-center gap-2 sm:gap-5">
            <nav class="flex items-center gap-1" aria-label="{{ __('common.menu') }}">
                <a href="{{ route('lang.switch', $isAr ? 'en' : 'ar') }}" hreflang="{{ $isAr ? 'en' : 'ar' }}"
                   class="inline-flex h-10 items-center gap-1.5 rounded-full px-3 text-sm font-bold text-fx-ink transition duration-300 hover:bg-fx-ink/5">
                    <x-glyph name="globe" size="17" />
                    <span>{{ $isAr ? 'EN' : 'عربي' }}</span>
                </a>
                <button type="button" @click="$dispatch('open-dialog', 'my-bookings')"
                        class="relative inline-flex h-10 items-center gap-2 rounded-full px-3 text-sm font-bold text-fx-ink ring-1 ring-fx-line transition duration-300 hover:bg-fx-ink/5 sm:px-4">
                    <x-glyph name="ticket" size="18" />
                    <span class="hidden sm:inline">{{ __('public.my_bookings') }}</span>
                    @if($hasBookings)
                        <span class="absolute -end-0.5 -top-0.5 size-2.5 rounded-full bg-fx-clay ring-2 ring-white"></span>
                    @endif
                </button>
            </nav>
            <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ __('public.club_name') }}">
                <img src="{{ asset('images/mafareed/qcmc-logo.svg') }}" alt="{{ __('public.club_name') }}" class="h-9 w-auto sm:h-12" loading="lazy" decoding="async">
            </a>
        </div>
    </div>
    <div class="fx-heritage-strip" aria-hidden="true" @if($texture) style="background-image: url('{{ $texture }}')" @endif></div>
</header>
