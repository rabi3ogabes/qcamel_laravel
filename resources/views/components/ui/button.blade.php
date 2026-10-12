@props([
    'variant' => 'primary', // primary | gold | secondary | ghost | danger | success | dark | outline-light
    'size' => 'md',         // sm | md | lg | xl
    'href' => null,
    'icon' => null,         // leading icon
    'trail' => null,        // trailing "button-in-button" icon (e.g. arrow)
    'loading' => null,      // wire:target for the spinner
    'block' => false,
])
@php
    $base = 'group/btn relative inline-flex select-none items-center justify-center gap-2.5 rounded-full font-bold tracking-tight transition-[transform,background-color,box-shadow,color,opacity] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50';
    $variants = [
        'primary' => 'bg-maroon-600 text-white shadow-elegant hover:bg-maroon-700',
        'gold' => 'bg-gold-400 text-ink shadow-gold hover:bg-gold-300',
        'secondary' => 'bg-white text-ink ring-1 ring-bark/15 hover:ring-bark/30 hover:bg-ivory shadow-soft',
        'ghost' => 'text-ink hover:bg-bark/5',
        'danger' => 'bg-danger text-white hover:brightness-110',
        'success' => 'bg-success text-white hover:brightness-110',
        'dark' => 'bg-ink text-cream hover:bg-ink/90',
        'outline-light' => 'text-white ring-1 ring-white/40 hover:bg-white/10',
    ];
    $sizes = [
        'sm' => 'h-9 px-4 text-sm',
        'md' => 'h-11 px-5 text-[15px]',
        'lg' => 'h-14 px-7 text-base',
        'xl' => 'h-16 px-8 text-lg',
    ];
    $trailWrap = [
        'primary' => 'bg-white/15', 'gold' => 'bg-ink/10', 'secondary' => 'bg-bark/10', 'ghost' => 'bg-bark/10',
        'danger' => 'bg-white/15', 'success' => 'bg-white/15', 'dark' => 'bg-white/10', 'outline-light' => 'bg-white/15',
    ][$variant] ?? 'bg-black/5';
    $classes = $base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']).($block ? ' w-full' : '').($trail ? ' pe-2' : '');
    $tag = $href ? 'a' : 'button';
    $iconSize = $size === 'sm' ? 16 : 20;
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @else type="{{ $attributes->get('type', 'button') }}" @endif {{ $attributes->except('type')->class($classes) }}>
    @if($loading)
        <x-ui.spinner wire:loading wire:target="{{ $loading }}" :size="$iconSize" />
    @endif
    @if($icon)
        @if($loading)
            <span wire:loading.remove wire:target="{{ $loading }}" class="contents"><x-glyph :name="$icon" :size="$iconSize" /></span>
        @else
            <x-glyph :name="$icon" :size="$iconSize" />
        @endif
    @endif
    <span>{{ $slot }}</span>
    @if($trail)
        <span class="ms-1 grid {{ $size === 'sm' ? 'size-7' : 'size-9' }} place-items-center rounded-full {{ $trailWrap }} transition-transform duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover/btn:-translate-y-px group-hover/btn:scale-105 ltr:group-hover/btn:translate-x-0.5 rtl:group-hover/btn:-translate-x-0.5">
            <x-glyph :name="$trail" size="16" :flip="in_array($trail, ['arrow', 'chevron'], true)" />
        </span>
    @endif
</{{ $tag }}>
