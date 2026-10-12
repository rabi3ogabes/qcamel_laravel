@props(['value' => 0, 'dec' => null, 'inc' => null, 'canDec' => true, 'canInc' => true, 'size' => 'md', 'label' => '', 'dark' => false])
@php $btn = $size === 'sm' ? 'size-9' : 'size-11'; @endphp
<div {{ $attributes->class(['inline-flex items-center gap-1 rounded-full p-1 ring-1 ring-inset', 'bg-ivory ring-bark/10' => ! $dark, 'bg-ops-bg ring-white/10' => $dark]) }} role="group" aria-label="{{ $label }}">
    <button type="button" @if($dec) wire:click="{{ $dec }}" @endif @disabled(! $canDec) aria-label="{{ __('public.select.decrease') }}"
        class="{{ $btn }} grid place-items-center rounded-full shadow-soft ring-1 transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] active:scale-90 disabled:opacity-35 disabled:shadow-none {{ $dark ? 'bg-ops-elevated text-ops-fg ring-white/10 hover:bg-ops-line' : 'bg-white text-ink ring-bark/10 hover:bg-cream' }}">
        <x-glyph name="minus" size="16" stroke="2" />
    </button>
    <span class="min-w-9 text-center text-lg font-extrabold tabular-nums {{ $dark ? 'text-ops-fg' : 'text-ink' }}" aria-live="polite">{{ $value }}</span>
    <button type="button" @if($inc) wire:click="{{ $inc }}" @endif aria-disabled="{{ $canInc ? 'false' : 'true' }}" aria-label="{{ __('public.select.increase') }}"
        class="{{ $btn }} grid place-items-center rounded-full bg-maroon-600 text-white shadow-elegant transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-maroon-700 active:scale-90 {{ $canInc ? '' : 'opacity-35 shadow-none' }}">
        <x-glyph name="plus" size="16" stroke="2" />
    </button>
</div>
