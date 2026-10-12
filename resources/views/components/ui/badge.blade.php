@props(['tone' => 'neutral', 'icon' => null, 'size' => 'md'])
@php
    $tones = [
        'neutral' => 'bg-bark/10 text-bark ring-bark/10',
        'success' => 'bg-success/10 text-success ring-success/20',
        'danger' => 'bg-danger/10 text-danger ring-danger/20',
        'warning' => 'bg-gold-100 text-gold-800 ring-gold-300/50',
        'gold' => 'bg-gold-400 text-ink ring-gold-500/30',
        'maroon' => 'bg-maroon-600 text-white ring-maroon-700/20',
        'info' => 'bg-sky-50 text-sky-800 ring-sky-200',
        'vip' => 'bg-gold-100 text-gold-800 ring-gold-300/60',
        'normal' => 'bg-maroon-50 text-maroon-700 ring-maroon-200/70',
        'parking' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'dark' => 'bg-white/10 text-ops-fg ring-white/10',
        'dark-success' => 'bg-emerald-500/15 text-emerald-300 ring-emerald-400/25',
        'dark-danger' => 'bg-red-500/15 text-red-300 ring-red-400/25',
        'dark-warning' => 'bg-amber-400/15 text-amber-200 ring-amber-300/25',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1.5 whitespace-nowrap rounded-full font-bold ring-1 ring-inset', $tones[$tone] ?? $tones['neutral'], $size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-3 py-1 text-xs']) }}>
    @if($icon)<x-glyph :name="$icon" size="14" stroke="2" />@endif
    {{ $slot }}
</span>
