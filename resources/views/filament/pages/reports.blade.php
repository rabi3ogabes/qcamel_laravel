@use('App\Support\Formats')
<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">{{ $this->form }}</div>

        <aside class="bezel h-fit">
            <div class="space-y-5 rounded-[calc(1.75rem-0.375rem)] bg-white p-5 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-2xl bg-success-600/10 text-success-700"><x-filament::icon icon="heroicon-o-table-cells" class="size-6" /></span>
                    <div>
                        <p class="font-extrabold text-gray-950 dark:text-white">{{ __('admin.rpt.preview') }}</p>
                        <p class="text-xs text-gray-500">{{ __('admin.rpt.preview_sub') }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 text-center" wire:loading.class="opacity-50">
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-white/5"><p class="text-2xl font-extrabold tabular-nums">{{ $p['n'] }}</p><p class="text-xs text-gray-500">{{ __('admin.rpt.bookings') }}</p></div>
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-white/5"><p class="text-2xl font-extrabold tabular-nums">{{ $p['tickets'] }}</p><p class="text-xs text-gray-500">{{ __('admin.dash.tickets') }}</p></div>
                </div>
                <ul class="space-y-2 text-sm" wire:loading.class="opacity-50">
                    @foreach(['confirmed' => 'success', 'pending' => 'warning', 'cancelled' => 'danger'] as $s => $c)
                        <li class="flex items-center justify-between rounded-xl px-3 py-2 ring-1 ring-gray-950/5 dark:ring-white/10">
                            <x-filament::badge :color="$c">{{ \App\Enums\PaymentStatus::from($s)->label() }} · {{ $p['by'][$s]['n'] }}</x-filament::badge>
                            <span class="font-bold tabular-nums">{{ Formats::money($p['by'][$s]['a'], short: true) }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="gold-rule"></div>
                <div class="text-xs leading-relaxed text-gray-500">
                    <p class="mb-1 font-bold text-gray-700 dark:text-gray-300">{{ __('admin.rpt.includes') }}</p>
                    <p>{{ __('admin.rpt.includes_list') }}</p>
                    <p class="mt-2">{{ __('admin.rpt.sheets_hint') }}</p>
                    @unless($pii)<p class="mt-2 font-bold text-warning-700">{{ __('admin.rpt.ids_masked') }}</p>@endunless
                </div>
            </div>
        </aside>
    </div>
</x-filament-panels::page>
