@php
    $flag = function (?string $cc) {
        $cc = strtoupper((string) $cc);
        if (strlen($cc) !== 2 || $cc === 'XX') {
            return '🌍';
        }

        return mb_chr(0x1F1E6 + ord($cc[0]) - 65).mb_chr(0x1F1E6 + ord($cc[1]) - 65);
    };
    $sourceTone = ['direct' => 'info', 'google' => 'danger', 'facebook' => 'primary', 'instagram' => 'danger', 'whatsapp' => 'success', 'twitter' => 'info', 'telegram' => 'info', 'youtube' => 'danger', 'referral' => 'warning', 'internal' => 'gray'];
    $kpis = [
        ['total', 'heroicon-o-users', 'text-primary-600 bg-primary-600/10'],
        ['new', 'heroicon-o-user-plus', 'text-success-600 bg-success-600/10'],
        ['returning', 'heroicon-o-arrow-path-rounded-square', 'text-info-600 bg-info-600/10'],
        ['desktop', 'heroicon-o-computer-desktop', 'text-violet-600 bg-violet-600/10'],
        ['mobile', 'heroicon-o-device-phone-mobile', 'text-orange-600 bg-orange-600/10'],
        ['tablet', 'heroicon-o-device-tablet', 'text-cyan-600 bg-cyan-600/10'],
    ];
@endphp
<x-filament-panels::page>
    <div wire:poll.30s.visible class="space-y-6">
        {{-- F-ADM-VIS-004 live KPIs --}}
        <div class="flex items-center gap-2 text-sm font-bold text-success-700 dark:text-success-400">
            <span class="relative flex size-2.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-success-500 opacity-60"></span><span class="relative inline-flex size-2.5 rounded-full bg-success-500"></span></span>
            {{ __('admin.vis.live_now', ['m' => config('qcamelmc.visitors.active_minutes')]) }}
        </div>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            @foreach($kpis as [$key, $icon, $tone])
                <div class="rounded-[1.25rem] bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <span class="grid size-9 place-items-center rounded-xl {{ $tone }}"><x-filament::icon :icon="$icon" class="size-5" /></span>
                    <p class="mt-3 text-2xl font-extrabold tabular-nums text-gray-950 dark:text-white">{{ $live[$key] }}</p>
                    <p class="text-xs text-gray-500">{{ __('admin.vis.kpi.'.$key) }}</p>
                </div>
            @endforeach
        </div>

        {{-- F-ADM-VIS-005…007 breakdowns --}}
        <div class="grid gap-4 lg:grid-cols-3">
            @foreach(['countries' => 'heroicon-o-globe-alt', 'sources' => 'heroicon-o-arrow-top-right-on-square', 'pages' => 'heroicon-o-map-pin'] as $key => $icon)
                <x-filament::section :heading="__('admin.vis.'.$key)" :icon="$icon" compact>
                    <ul class="max-h-44 space-y-1.5 overflow-y-auto text-sm">
                        @forelse($live[$key] as $label => $n)
                            <li class="flex items-center justify-between gap-2">
                                @if($key === 'countries')
                                    @php [$cc, $name] = explode('|', $label, 2); @endphp
                                    <span class="truncate">{{ $flag($cc) }} {{ $name }}</span>
                                @elseif($key === 'sources')
                                    <x-filament::badge size="sm" :color="$sourceTone[$label] ?? 'gray'">{{ __('admin.vis.source.'.$label) !== 'admin.vis.source.'.$label ? __('admin.vis.source.'.$label) : $label }}</x-filament::badge>
                                @else
                                    <span class="truncate font-mono text-xs" dir="ltr" title="{{ $label }}">{{ $label }}</span>
                                @endif
                                <span class="shrink-0 font-bold tabular-nums">{{ $n }}</span>
                            </li>
                        @empty
                            <li class="py-4 text-center text-gray-400">{{ __('admin.vis.no_data') }}</li>
                        @endforelse
                    </ul>
                </x-filament::section>
            @endforeach
        </div>

        {{-- F-ADM-VIS-008 detail table --}}
        <x-filament::section :heading="__('admin.vis.details')" icon="heroicon-o-table-cells">
            @if($live['rows']->isEmpty())
                <p class="py-8 text-center text-gray-400">{{ __('admin.vis.none_now') }}</p>
            @else
                <div class="-mx-2 overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="text-xs text-gray-500">
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                @foreach(['country', 'city', 'ip', 'device', 'browser', 'os', 'page', 'source', 'type', 'last'] as $c)
                                    <th class="px-2 py-2 text-start font-bold">{{ __('admin.vis.col.'.$c) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($live['rows'] as $v)
                                <tr wire:key="v-{{ $v->id }}">
                                    <td class="px-2 py-2">{{ $flag($v->country_code) }} {{ $v->country ?: __('admin.vis.unknown') }}</td>
                                    <td class="px-2 py-2">{{ $v->city ?: '—' }}</td>
                                    <td class="px-2 py-2 font-mono text-xs" dir="ltr">{{ \App\Filament\Pages\Visitors::maskIp($v->ip_address) }}</td>
                                    <td class="px-2 py-2">{{ __('admin.vis.kpi.'.($v->device_type ?: 'desktop')) }}</td>
                                    <td class="px-2 py-2">{{ $v->browser ?: '—' }}</td>
                                    <td class="px-2 py-2">{{ $v->os ?: '—' }}</td>
                                    <td class="max-w-[12rem] truncate px-2 py-2 font-mono text-xs" dir="ltr" title="{{ $v->current_page }}">{{ $v->current_page }}</td>
                                    <td class="px-2 py-2"><x-filament::badge size="sm" :color="$sourceTone[$v->traffic_source] ?? 'gray'">{{ $v->traffic_source }}</x-filament::badge></td>
                                    <td class="px-2 py-2"><x-filament::badge size="sm" :color="$v->is_new_visitor ? 'success' : 'info'">{{ $v->is_new_visitor ? __('admin.vis.new') : __('admin.vis.returning') }}</x-filament::badge></td>
                                    <td class="px-2 py-2 text-xs text-gray-500">{{ \App\Support\Formats::ago($v->last_seen_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{-- U-ADM-VIS-01/02 range analytics --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
            <h2 class="text-lg font-extrabold text-gray-950 dark:text-white">{{ __('admin.vis.range_title') }}</h2>
            <x-filament::tabs>
                @foreach([1, 7, 30] as $d)
                    <x-filament::tabs.item :active="$days === $d" wire:click="$set('days', {{ $d }})">{{ __('admin.vis.range.'.$d) }}</x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
        </div>

        @php
            $steps = [['visits', $funnel['visits']], ['checkout', $funnel['checkout']], ['orders', $funnel['orders']], ['paid', $funnel['paid']], ['scanned', $funnel['scanned']]];
            $top = max(1, $funnel['visits']);
        @endphp
        <x-filament::section :heading="__('admin.vis.funnel')" icon="heroicon-o-funnel" :description="__('admin.vis.funnel_hint')">
            <div class="grid gap-3 sm:grid-cols-5">
                @foreach($steps as $i => [$k, $n])
                    @php $pct = (int) round($n / $top * 100); @endphp
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-white/5">
                        <p class="text-xs font-bold text-gray-500">{{ $i + 1 }}. {{ __('admin.vis.step.'.$k) }}</p>
                        <p class="mt-1 text-2xl font-extrabold tabular-nums">{{ $n }}</p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10"><div class="h-full rounded-full bg-gradient-to-l from-primary-600 to-gold-400" style="width: {{ min(100, $pct) }}%"></div></div>
                        <p class="mt-1 text-[11px] tabular-nums text-gray-500">{{ $i === 0 ? __('admin.vis.unique_n', ['n' => $unique]) : $pct.'%' }}</p>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-filament::section :heading="__('admin.vis.top_pages')" icon="heroicon-o-document-text" compact>
                <ul class="space-y-1.5 text-sm">
                    @forelse($pages as $p)
                        <li class="flex items-center justify-between gap-3"><span class="truncate font-mono text-xs" dir="ltr">{{ $p['path'] }}</span><span class="shrink-0 tabular-nums text-gray-500"><b class="text-gray-950 dark:text-white">{{ $p['views'] }}</b> · {{ __('admin.vis.unique_n', ['n' => $p['unique']]) }}</span></li>
                    @empty
                        <li class="py-4 text-center text-gray-400">{{ __('admin.vis.no_data') }}</li>
                    @endforelse
                </ul>
            </x-filament::section>
            <x-filament::section :heading="__('admin.vis.top_countries')" icon="heroicon-o-globe-europe-africa" compact>
                <ul class="space-y-1.5 text-sm">
                    @forelse($countries as $c)
                        <li class="flex items-center justify-between gap-3"><span class="truncate">{{ $flag($c['code']) }} {{ in_array($c['code'], ['XX', ''], true) || $c['country'] === 'Unknown' ? __('admin.vis.unknown') : $c['country'] }}</span><span class="shrink-0 tabular-nums text-gray-500"><b class="text-gray-950 dark:text-white">{{ $c['total'] }}</b> · {{ __('admin.vis.unique_n', ['n' => $c['unique']]) }}</span></li>
                    @empty
                        <li class="py-4 text-center text-gray-400">{{ __('admin.vis.no_data') }}</li>
                    @endforelse
                </ul>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
