@props([
    'title' => null,
    'theme' => 'light',   // light | dark (scanner, gate board, live boards)
    'scripts' => null,
])
@php
    $isAr = app()->getLocale() !== 'en';
    $dark = $theme === 'dark';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}" class="{{ $dark ? 'dark' : '' }}" data-copied="{{ __('common.copied') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="{{ $dark ? '#120708' : '#9C1638' }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="{{ route('staff.manifest') }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $brand['festival_name'] ?? __('common.app_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{ $head ?? '' }}
</head>
<body @class(['min-h-dvh font-sans antialiased', 'grain bg-cream text-ink' => ! $dark, 'bg-ops-bg text-ops-fg' => $dark]) data-track="0">
    @if($dark)
        <div class="pointer-events-none fixed inset-0 z-0" aria-hidden="true">
            <div class="absolute -top-40 start-1/2 size-[42rem] -translate-x-1/2 rounded-full bg-maroon-700/20 blur-3xl rtl:translate-x-1/2"></div>
            <div class="absolute bottom-0 end-0 size-[30rem] rounded-full bg-gold-500/10 blur-3xl"></div>
        </div>
    @endif

    <div class="relative z-[2] flex min-h-dvh flex-col">
        {{ $header ?? '' }}
        <main class="flex-1">
            {{ $slot }}
        </main>
    </div>

    <x-ui.toasts :dark="$dark" />
    @livewireScripts
    {{ $scripts }}
</body>
</html>
