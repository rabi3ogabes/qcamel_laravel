@props(['title' => null])
@php $isAr = app()->getLocale() !== 'en'; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}" data-copied="{{ __('common.copied') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#9C1638">
    <title>{{ $title ? $title.' · ' : '' }}{{ $brand['festival_name'] ?? __('common.app_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="grain min-h-dvh bg-cream font-sans text-ink antialiased">
    <div class="relative z-[2] grid min-h-dvh lg:grid-cols-[1.05fr_1fr]">
        {{-- Brand panel (desktop) --}}
        <aside class="sadu relative hidden overflow-hidden bg-gradient-to-br from-maroon-800 via-maroon-700 to-terracotta lg:flex lg:flex-col lg:justify-between lg:p-14">
            <div class="pointer-events-none absolute -end-24 -top-24 size-96 rounded-full bg-gold-400/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-32 -start-20 size-[28rem] rounded-full bg-maroon-950/40 blur-3xl"></div>
            <a href="{{ route('home') }}" class="relative inline-flex items-center gap-3 text-cream">
                @if($brand['logo_url'] ?? null)
                    <img src="{{ $brand['logo_url'] }}" alt="{{ __('common.logo') }}" class="h-14 w-auto rounded-xl bg-white/90 p-1.5">
                @else
                    <span class="grid size-12 place-items-center rounded-full bg-white/10 text-gold-300 ring-1 ring-white/20"><x-glyph name="sparkle" size="24" /></span>
                @endif
            </a>
            <div class="relative max-w-md">
                <p class="text-sm font-bold tracking-[0.2em] text-gold-300">{{ $brand['season_label'] ?? '' }}</p>
                <h2 class="mt-4 text-5xl font-extrabold leading-[1.15] text-white">{{ $brand['festival_name'] ?? __('common.app_name') }}</h2>
                <div class="my-8 h-px w-40 bg-gradient-to-r from-gold-400 to-transparent rtl:bg-gradient-to-l"></div>
                <ul class="space-y-4 text-cream/85">
                    @foreach([['shield', __('auth.panel_secure')], ['scan', __('auth.panel_ops')], ['chart', __('auth.panel_live')]] as [$ic, $line])
                        <li class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-full bg-white/10 text-gold-300 ring-1 ring-white/15"><x-glyph :name="$ic" size="18" /></span>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
            <p class="relative text-xs text-cream/60">© {{ now('Asia/Qatar')->year }} {{ $brand['copyright_text'] ?? __('common.all_rights') }} · {{ config('qcamelmc.version') }}</p>
        </aside>

        <main class="flex flex-col items-center justify-center px-4 py-10 sm:px-8">
            <a href="{{ route('home') }}" class="mb-8 lg:hidden">
                @if($brand['logo_url'] ?? null)
                    <img src="{{ $brand['logo_url'] }}" alt="{{ __('common.logo') }}" class="h-[53px] w-auto">
                @else
                    <span class="text-xl font-extrabold text-bark">{{ __('auth.title') }}</span>
                @endif
            </a>
            <div class="w-full max-w-md animate-rise">
                {{ $slot }}
            </div>
            <div class="mt-8 flex items-center gap-4 text-xs font-bold text-ink-soft">
                <a href="{{ route('lang.switch', $isAr ? 'en' : 'ar') }}" class="inline-flex items-center gap-1.5 hover:text-ink"><x-glyph name="globe" size="15" />{{ $isAr ? 'English' : 'العربية' }}</a>
            </div>
        </main>
    </div>
    <x-ui.toasts />
    @livewireScripts
</body>
</html>
