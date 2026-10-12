@props(['texture' => null])
{{-- F-PUB-011: coral footer, optional repeat-x texture, copyright, version, trust row. --}}
<footer class="relative z-[2] mt-20 overflow-hidden bg-coral text-white">
    @if($texture)
        <div class="pointer-events-none absolute inset-0" style="background: linear-gradient(to top, rgb(231 126 90 / .8), rgb(231 126 90 / .4)), url('{{ $texture }}') center / auto 100% repeat-x"></div>
    @endif
    <div class="sadu pointer-events-none absolute inset-0 opacity-60"></div>
    <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/60 to-transparent"></div>

    <div class="relative mx-auto flex max-w-6xl flex-col gap-6 px-6 py-8 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1 text-center md:text-start">
            <p class="text-sm">© {{ now(config('qcamelmc.timezone', 'Asia/Qatar'))->year }} {{ $brand['copyright_text'] ?? __('common.all_rights') }}</p>
            <p class="text-xs text-white/70">{{ __('public.footer.tagline') }} · {{ config('qcamelmc.version') }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-center gap-4">
            <span class="inline-flex items-center gap-2 text-sm font-bold"><x-glyph name="shield" size="20" />{{ __('public.footer.secure') }}</span>
            <span class="hidden h-8 w-px bg-white/30 sm:block"></span>
            <div class="flex items-center gap-2" aria-label="Visa, Mastercard, Apple Pay, Sadad">
                {{-- Neutral payment marks (replaceable from settings media) --}}
                <span class="grid h-8 place-items-center rounded-md bg-white px-2.5 text-[13px] font-black italic tracking-tight text-[#1a1f71]">VISA</span>
                <span class="grid h-8 place-items-center rounded-md bg-white px-2">
                    <svg width="30" height="18" viewBox="0 0 30 18" aria-hidden="true"><circle cx="11" cy="9" r="7" fill="#EB001B"/><circle cx="19" cy="9" r="7" fill="#F79E1B" fill-opacity=".9"/></svg>
                </span>
                <span class="grid h-8 place-items-center rounded-md bg-ink px-2.5 text-[12px] font-bold text-white">Apple&nbsp;Pay</span>
                <span class="hidden h-8 w-px bg-white/30 sm:block"></span>
                <span class="grid h-8 place-items-center rounded-md bg-white px-2.5 text-[12px] font-black tracking-wide text-[#0b6b3a]">SADAD</span>
            </div>
        </div>
    </div>
</footer>
