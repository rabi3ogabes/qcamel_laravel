@props(['icon' => 'sparkle', 'title', 'hint' => null, 'dark' => false])
<div {{ $attributes->class('flex flex-col items-center justify-center gap-4 px-6 py-16 text-center') }}>
    <span class="grid size-16 place-items-center rounded-full {{ $dark ? 'bg-white/5 text-ops-muted ring-1 ring-white/10' : 'bg-white text-terracotta shadow-soft ring-1 ring-sand/60' }}">
        <x-glyph :name="$icon" size="28" stroke="1.25" />
    </span>
    <div class="space-y-1.5">
        <p class="text-xl font-extrabold {{ $dark ? 'text-ops-fg' : 'text-bark' }}">{{ $title }}</p>
        @if($hint)<p class="mx-auto max-w-sm text-sm {{ $dark ? 'text-ops-muted' : 'text-ink-soft' }}">{{ $hint }}</p>@endif
    </div>
    {{ $slot }}
</div>
