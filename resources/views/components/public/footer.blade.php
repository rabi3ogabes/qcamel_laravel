@props(['texture' => null])
@php
    $year = now(config('qcamelmc.timezone', 'Asia/Qatar'))->year;
    $name = $brand['festival_name'] ?? __('public.home.title_fallback');
@endphp
{{-- F-PUB-011 festival footer (qcamelmc.org): clay band, white festival mark, copyright, camel caravan edge. --}}
<footer class="fx-site relative z-[2] mt-8 bg-fx-clay text-white" aria-label="{{ __('public.footer.aria') }}">
    <div class="mx-auto flex max-w-7xl flex-col items-center gap-7 px-5 pb-8 pt-12 text-center sm:px-8 sm:pt-16">
        <a href="{{ route('home') }}" aria-label="{{ $name }}">
            <img src="{{ asset('images/mafareed/footer-logo.png') }}" alt="{{ $name }}" class="block w-[190px] sm:w-[220px]" loading="lazy" decoding="async">
        </a>
        <p class="text-sm text-fx-ink-deep">{{ $brand['copyright_text'] ?? __('common.all_rights') }} © {{ $year }} - {{ __('public.footer.country') }}</p>
        <p class="inline-flex items-center gap-2 text-xs text-fx-ink-deep/75">
            <x-glyph name="shield" size="14" />{{ __('public.footer.secure') }} · {{ __('public.footer.tagline') }} · {{ config('qcamelmc.version') }}
        </p>
    </div>
    <div class="fx-footer-camels" aria-hidden="true"></div>
</footer>
