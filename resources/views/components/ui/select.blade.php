@props(['invalid' => false, 'dark' => false])
<div class="relative">
    <select {{ $attributes->class([
        'block h-12 w-full appearance-none rounded-2xl border-0 pe-10 ps-4 text-[16px] ring-1 ring-inset transition-shadow duration-300 focus:outline-none focus:ring-2',
        'bg-ivory text-ink ring-bark/15 focus:bg-white focus:ring-maroon-500/60' => ! $dark,
        'bg-ops-bg text-ops-fg ring-white/10 focus:ring-gold-400/60' => $dark,
        '!ring-danger/60' => $invalid,
    ]) }}>{{ $slot }}</select>
    <span class="pointer-events-none absolute inset-y-0 end-0 grid w-10 place-items-center {{ $dark ? 'text-ops-muted' : 'text-clay' }}"><x-glyph name="chevron-down" size="16" /></span>
</div>
