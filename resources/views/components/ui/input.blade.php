@props(['icon' => null, 'invalid' => false, 'dark' => false])
<div class="relative">
    @if($icon)
        <span class="pointer-events-none absolute inset-y-0 start-0 grid w-11 place-items-center {{ $dark ? 'text-ops-muted' : 'text-clay' }}"><x-glyph :name="$icon" size="18" /></span>
    @endif
    <input {{ $attributes->class([
        'block h-12 w-full rounded-2xl border-0 px-4 text-[16px] ring-1 ring-inset transition-shadow duration-300 focus:outline-none focus:ring-2 disabled:opacity-60',
        'bg-ivory text-ink ring-bark/15 placeholder:text-ink-soft/45 focus:bg-white focus:ring-maroon-500/60' => ! $dark,
        'bg-ops-bg text-ops-fg ring-white/10 placeholder:text-ops-muted/60 focus:ring-gold-400/60' => $dark,
        'ps-11' => $icon,
        '!ring-danger/60' => $invalid,
    ]) }}>
</div>
