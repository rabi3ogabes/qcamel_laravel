@props(['pad' => 'p-6 sm:p-8', 'inner' => ''])
{{-- Double-bezel shell + core (machined tray, concentric radii) --}}
<div {{ $attributes->class('bezel') }}>
    <div class="bezel-core {{ $pad }} {{ $inner }}">
        {{ $slot }}
    </div>
</div>
