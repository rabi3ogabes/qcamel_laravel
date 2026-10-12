@props(['text', 'label' => null])
<button type="button" x-data="copyText(@js($text))" @click="copy()" aria-label="{{ $label ?? __('common.copy') }}"
    {{ $attributes->class('grid size-9 place-items-center rounded-full text-clay transition hover:bg-bark/10 hover:text-ink active:scale-90') }}>
    <span x-show="!done"><x-glyph name="copy" size="17" /></span>
    <span x-show="done" x-cloak class="text-success"><x-glyph name="check" size="17" stroke="2" /></span>
</button>
