@php
    $isAr = app()->getLocale() !== 'en';
    try { $brandData = \App\Support\PublicSettings::current(); } catch (\Throwable) { $brandData = []; }
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>@yield('code') · {{ $brandData['festival_name'] ?? __('common.app_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="grain min-h-dvh bg-cream font-sans text-ink antialiased">
    {{-- F-PUB-085: self-contained error shell (no Livewire/session dependency) --}}
    <main class="relative z-[2] mx-auto flex min-h-dvh max-w-xl flex-col items-center justify-center px-6 py-16 text-center">
        <a href="{{ url('/') }}" class="mb-12 inline-flex items-center gap-3">
            @if($brandData['logo_url'] ?? null)
                <img src="{{ $brandData['logo_url'] }}" alt="" class="h-12 w-auto">
            @else
                <span class="text-lg font-extrabold text-bark">{{ __('public.brand_text') }}</span>
            @endif
        </a>
        <p class="bg-gradient-to-b from-maroon-600 to-terracotta bg-clip-text text-7xl font-extrabold tracking-tight text-transparent sm:text-8xl">@yield('code')</p>
        <div class="mx-auto my-8 h-px w-40 bg-gradient-to-r from-transparent via-sand to-transparent"></div>
        <h1 class="text-2xl font-extrabold text-bark sm:text-3xl">@yield('title')</h1>
        <p class="mt-3 max-w-md leading-relaxed text-ink-soft">@yield('hint', __('common.error_hint'))</p>
        <div class="mt-10 flex flex-wrap justify-center gap-3">
            @yield('actions')
            <a href="{{ url('/') }}" class="inline-flex h-12 items-center gap-2 rounded-full bg-maroon-600 px-6 font-bold text-white shadow-elegant transition duration-500 hover:bg-maroon-700 active:scale-[.98]">
                {{ __('common.back_home') }}
            </a>
        </div>
    </main>
</body>
</html>
