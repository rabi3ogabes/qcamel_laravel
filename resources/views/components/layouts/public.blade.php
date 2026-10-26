@props([
    'title' => null,
    'description' => null,
    'header' => 'page',      // home | page | none
    'whatsapp' => false,     // F-PUB-012: home only
    'beforeFooter' => false, // F-PUB-010
    'surface' => 'cream',    // cream | white
    'track' => true,         // presence heartbeat + Clarity eligibility
])
@php
    $locale = app()->getLocale();
    $isAr = $locale !== 'en';
    $siteTitle = ($isAr ? ($brand['site_title_ar'] ?? null) : ($brand['site_title_en'] ?? null)) ?: __('public.meta.title');
    $siteDesc = ($isAr ? ($brand['site_description_ar'] ?? null) : ($brand['site_description_en'] ?? null)) ?: __('public.meta.description');
    $pageTitle = $title ? $title.' · '.$siteTitle : $siteTitle;
    $metaDesc = $description ?: $siteDesc;
    $ogImage = $brand['og_image_url'] ?? null ?: asset('og-image.jpg');
    $headerColor = $brand['header_bg_color'] ?? '#D4B78A';
    $headerTexture = $brand['header_bg_image_url'] ?? null;
    $wa = \App\Support\PhoneNumber::digits($brand['support_whatsapp'] ?? '');
    // F-PUB-087 rebuild: Clarity only on /, /tickets/*, /checkout — never on pages with personal data.
    $clarity = $track && ($brand['clarity_enabled'] ?? false) && preg_match('/^[a-z0-9]{4,20}$/i', (string) ($brand['clarity_project_id'] ?? ''))
        && (request()->routeIs('home', 'tickets.select', 'checkout'));
    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}" data-copied="{{ __('common.copied') }}" class="scroll-pt-28">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDesc }}">
    <meta name="author" content="{{ __('public.meta.author') }}">
    <meta name="theme-color" content="#9C1638">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $metaDesc }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:locale" content="{{ $isAr ? 'ar_QA' : 'en_US' }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $metaDesc }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    @if(! $track)<meta name="robots" content="noindex, nofollow">@endif
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="alternate" hreflang="ar" href="{{ request()->fullUrlWithQuery(['lang' => 'ar']) }}">
    <link rel="alternate" hreflang="en" href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{ $head ?? '' }}
</head>
<body @class(['grain min-h-dvh font-sans text-ink antialiased selection:bg-gold-200 selection:text-ink', 'bg-cream' => $surface === 'cream', 'bg-ivory' => $surface === 'white', 'bg-white' => $surface === 'plain'])
      data-track="{{ $track ? '1' : '0' }}" style="--header-bg: {{ $headerColor }}">
    <a href="#main" class="sr-only z-[70] rounded-full bg-ink px-4 py-2 text-cream focus:not-sr-only focus:fixed focus:start-4 focus:top-4">{{ __('public.skip_to_content') }}</a>

    @if($header !== 'none')
        <x-public.header :overlay="$header === 'home'" :color="$headerColor" :texture="$headerTexture" />
    @endif

    <main id="main" @class(['relative z-[2]', 'fx-page' => $header !== 'none'])>
        {{ $slot }}
    </main>

    {{-- F-PUB-010: heritage pattern band before the footer (admin image or the bundled festival tile) --}}
    @if($beforeFooter)
        <div class="fx-pre-footer relative z-[2] mt-16 h-[clamp(150px,28vw,290px)] w-full" aria-hidden="true"
             style="background-image: url('{{ $brand['before_footer_image_url'] ?? asset('images/mafareed/before-footer.png') }}')"></div>
    @endif

    <x-public.footer :texture="$headerTexture" />

    @if($whatsapp && $wa !== '')
        <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('public.whatsapp_aria') }}"
           class="group fixed bottom-[max(1.5rem,env(safe-area-inset-bottom))] end-6 z-50 inline-flex items-center gap-2 rounded-full bg-[#25D366] p-3.5 text-white shadow-[0_18px_40px_-12px_rgb(37_211_102/0.65)] ring-4 ring-white/70 transition duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:scale-105 hover:bg-[#20BA5A] sm:px-5">
            <x-glyph name="whatsapp" size="24" />
            <span class="hidden text-sm font-bold sm:inline">{{ __('public.whatsapp') }}</span>
        </a>
    @endif

    <x-public.bookings-drawer />
    <x-ui.toasts />

    @livewireScripts

    @if($clarity)
        <x-public.clarity :id="$brand['clarity_project_id']" :consent="(bool) ($brand['clarity_require_consent'] ?? true)" />
    @endif

    {{-- F-PUB-006: one-time purge of the retired SPA's caches lives in app.js. --}}
    {{ $scripts ?? '' }}
</body>
</html>
