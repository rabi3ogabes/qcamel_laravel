{{-- ADM-SHL: quick launcher for the staff apps (same session, permission-filtered) --}}
@php
    $actor = \App\Domain\Shared\Actor::current();
    $apps = collect([
        ['route' => 'staff.pos', 'perm' => 'pos.sell', 'icon' => 'heroicon-o-banknotes', 'label' => 'link_pos'],
        ['route' => 'staff.scanner', 'perm' => 'scanner.admit', 'icon' => 'heroicon-o-qr-code', 'label' => 'link_scanner'],
        ['route' => 'staff.live', 'perm' => 'live.view', 'icon' => 'heroicon-o-signal', 'label' => 'link_live'],
        ['route' => 'staff.gate', 'perm' => 'gate.view', 'icon' => 'heroicon-o-building-office-2', 'label' => 'link_gate'],
        ['route' => 'staff.receipts', 'perm' => 'receipts.capture', 'icon' => 'heroicon-o-document-text', 'label' => 'link_receipts'],
        ['route' => 'staff.attendance', 'perm' => 'attendance.view', 'icon' => 'heroicon-o-user-group', 'label' => 'link_attendance'],
    ])->filter(fn ($a) => $actor->can($a['perm']));
@endphp
@if($apps->isNotEmpty())
    <x-filament::dropdown placement="bottom-end" width="xs">
        <x-slot name="trigger">
            <x-filament::icon-button icon="heroicon-o-squares-2x2" color="gray" size="lg" :label="__('staff.hub_title')" />
        </x-slot>
        <x-filament::dropdown.header icon="heroicon-o-sparkles">{{ __('staff.hub_title') }}</x-filament::dropdown.header>
        <x-filament::dropdown.list>
            @foreach($apps as $a)
                <x-filament::dropdown.list.item :href="route($a['route'])" tag="a" :icon="$a['icon']">{{ __('staff.'.$a['label']) }}</x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
        <x-filament::dropdown.list>
            <x-filament::dropdown.list.item :href="route('staff.hub')" tag="a" icon="heroicon-o-home">{{ __('staff.hub_title') }}</x-filament::dropdown.list.item>
        </x-filament::dropdown.list>
    </x-filament::dropdown>
@endif
