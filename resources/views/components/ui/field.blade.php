@props(['label' => null, 'for' => null, 'required' => false, 'error' => null, 'help' => null, 'optional' => null, 'dark' => false])
<div {{ $attributes->class('space-y-1.5') }}>
    @if($label)
        <label @if($for) for="{{ $for }}" @endif class="flex items-baseline justify-between gap-2 text-sm font-bold {{ $dark ? 'text-ops-fg' : 'text-ink' }}">
            <span>{{ $label }}@if($required)<span class="text-maroon-500"> *</span>@endif</span>
            @if($optional)<span class="text-xs font-medium opacity-60">{{ $optional }}</span>@endif
        </label>
    @endif
    {{ $slot }}
    @if($error && $errors->has($error))
        <p class="flex items-center gap-1.5 text-[13px] font-medium text-danger" role="alert"><x-glyph name="alert" size="14" />{{ $errors->first($error) }}</p>
    @elseif($help)
        <p class="text-xs {{ $dark ? 'text-ops-muted' : 'text-ink-soft/80' }}">{{ $help }}</p>
    @endif
</div>
