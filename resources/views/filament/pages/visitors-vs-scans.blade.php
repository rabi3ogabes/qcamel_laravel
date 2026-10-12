<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-filament::tabs>
            @foreach([1, 7, 30] as $d)
                <x-filament::tabs.item :active="$days === $d" wire:click="$set('days', {{ $d }})">{{ __('admin.vis.range.'.$d) }}</x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
        <x-filament::button color="gray" icon="heroicon-m-arrow-path" wire:click="$refresh">{{ __('admin.ntf.refresh') }}</x-filament::button>
    </div>

    <div class="grid gap-4 md:grid-cols-3" wire:loading.class="opacity-60">
        @foreach([
            ['visitors', $data['visitors'], 'heroicon-o-eye', 'text-primary-600 bg-primary-600/10'],
            ['scans', $data['scans'], 'heroicon-o-qr-code', 'text-success-600 bg-success-600/10'],
            ['rate', $data['rate'].'%', 'heroicon-o-arrow-trending-up', 'text-gold-600 bg-gold-400/15'],
        ] as [$k, $v, $icon, $tone])
            <div class="bezel">
                <div class="flex items-center gap-4 rounded-[calc(1.75rem-0.375rem)] bg-white p-5 dark:bg-gray-900">
                    <span class="grid size-12 place-items-center rounded-2xl {{ $tone }}"><x-filament::icon :icon="$icon" class="size-6" /></span>
                    <div>
                        <p class="text-3xl font-extrabold tabular-nums text-gray-950 dark:text-white">{{ $v }}</p>
                        <p class="text-sm text-gray-500">{{ __('admin.vvs.kpi.'.$k) }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @livewire(\App\Filament\Widgets\VisitorsVsScansChart::class, ['days' => $days], key('vvs-chart-'.$days))

    <p class="text-xs text-gray-500">{{ __('admin.vvs.note') }}</p>
</x-filament-panels::page>
