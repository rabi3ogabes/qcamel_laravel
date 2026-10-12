<x-filament-panels::page>
    @php
        $kindIcon = ['vip' => '👑', 'normal' => '🎫', 'parking' => '🚗'];
        $kindRing = ['vip' => 'ring-gold-300/60', 'normal' => 'ring-maroon-200', 'parking' => 'ring-sky-200'];
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-filament::tabs>
            <x-filament::tabs.item :active="$season === 'current'" wire:click="$set('season', 'current')" icon="heroicon-o-calendar">{{ __('common.current') }}</x-filament::tabs.item>
            <x-filament::tabs.item :active="$season === 'archive'" wire:click="$set('season', 'archive')" icon="heroicon-o-archive-box">{{ __('common.archive') }}</x-filament::tabs.item>
        </x-filament::tabs>
        <a href="{{ \App\Filament\Resources\BookedTickets\BookedTicketResource::getUrl() }}" class="text-sm font-bold text-primary-600 hover:underline">{{ __('admin.tkt.booked_link') }} ←</a>
    </div>

    @if($events->isEmpty())
        <x-filament::section><p class="py-10 text-center text-gray-500">{{ __('admin.tkt.empty') }}</p></x-filament::section>
    @else
        {{-- Event tabs (grouped by event, Arabic Qatar dates) --}}
        <div class="flex gap-2 overflow-x-auto pb-1">
            @foreach($events as $e)
                <button type="button" wire:click="$set('eventId', '{{ $e->id }}')" wire:key="ev-{{ $e->id }}"
                    @class(['shrink-0 rounded-2xl px-4 py-2.5 text-start ring-1 transition', 'bg-primary-600 text-white ring-primary-600 shadow-lg' => $event?->id === $e->id, 'bg-white ring-gray-200 hover:ring-primary-300 dark:bg-white/5 dark:ring-white/10' => $event?->id !== $e->id])>
                    <span class="block text-xs font-bold opacity-80">{{ \App\Support\Formats::weekday($e->event_date) }}</span>
                    <span class="block text-sm font-extrabold">{{ \App\Support\Formats::dayMonth($e->event_date) }}</span>
                    <span class="block max-w-[10rem] truncate text-xs opacity-80">{{ $e->title }}</span>
                </button>
            @endforeach
        </div>

        @if($event)
            <x-filament::section :heading="$event->title" :description="\App\Support\Formats::longDate($event->event_date).' · '.$event->location" icon="heroicon-o-ticket">
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach($types as $t)
                        @php
                            $held = (int) $t->sold_quantity; $cap = (int) $t->available_quantity; $rem = $cap - $held;
                            $pct = $cap > 0 ? (int) round($held / $cap * 100) : 100;
                            $b = $breakdown[$t->type->value] ?? [];
                            $tone = $pct >= 90 ? 'text-danger-600' : ($pct >= 70 ? 'text-warning-600' : 'text-success-600');
                        @endphp
                        <div wire:key="tt-{{ $t->id }}" class="rounded-[1.5rem] bg-white p-5 shadow-sm ring-1 {{ $kindRing[$t->type->value] }} dark:bg-white/5">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-lg font-extrabold">{{ $kindIcon[$t->type->value] }} {{ $t->type->longLabel() }}</p>
                                    <p class="text-sm font-bold text-primary-600">{{ \App\Support\Formats::money($t->price) }}</p>
                                </div>
                                <div class="flex items-center gap-1">
                                    @if($rem < 0)
                                        <x-filament::badge color="danger">{{ __('tickets.over_capacity') }}</x-filament::badge>
                                    @elseif($rem === 0)
                                        <x-filament::badge color="gray">{{ __('tickets.sold_out') }}</x-filament::badge>
                                    @elseif($rem <= $low)
                                        <x-filament::badge color="danger">{{ __('tickets.limited') }}</x-filament::badge>
                                    @endif
                                    {{ ($this->editTicketAction)(['id' => $t->id]) }}
                                </div>
                            </div>
                            <dl class="mt-4 grid grid-cols-4 gap-2 text-center">
                                <div class="rounded-xl bg-gray-50 p-2 dark:bg-white/5"><dt class="text-[11px] text-gray-500">{{ __('admin.tkt.capacity_short') }}</dt><dd class="text-lg font-extrabold tabular-nums">{{ $cap }}</dd></div>
                                <div class="rounded-xl bg-gray-50 p-2 dark:bg-white/5"><dt class="text-[11px] text-gray-500">{{ __('admin.tkt.held') }}</dt><dd class="text-lg font-extrabold tabular-nums">{{ $held }}</dd></div>
                                <div class="rounded-xl bg-gray-50 p-2 dark:bg-white/5"><dt class="text-[11px] text-gray-500">{{ __('admin.tkt.confirmed') }}</dt><dd class="text-lg font-extrabold tabular-nums">{{ $b['confirmed'] ?? 0 }}</dd></div>
                                <div class="rounded-xl bg-gray-50 p-2 dark:bg-white/5"><dt class="text-[11px] text-gray-500">{{ __('admin.tkt.remaining') }}</dt><dd class="text-lg font-extrabold tabular-nums {{ $tone }}">{{ $rem }}</dd></div>
                            </dl>
                            <div class="mt-3 flex items-center justify-between text-xs"><span class="text-gray-500">{{ __('admin.tkt.sold_pct') }}</span><span class="font-bold {{ $tone }}">{{ $pct }}%</span></div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"><div class="h-full rounded-full bg-gradient-to-l from-primary-500 to-gold-400" style="width: {{ min(100, $pct) }}%"></div></div>
                            <p class="mt-4 text-xs font-bold text-gray-500">{{ __('admin.tkt.payment_state') }}</p>
                            <div class="mt-2 grid grid-cols-3 gap-2 text-center text-xs">
                                <div class="rounded-lg bg-success-50 py-1.5 font-bold text-success-700 dark:bg-success-500/10"><span class="block text-[11px] opacity-80">{{ __('admin.tkt.paid') }}</span><span class="block text-base tabular-nums">{{ $b['confirmed'] ?? 0 }}</span></div>
                                <div class="rounded-lg bg-warning-50 py-1.5 font-bold text-warning-700 dark:bg-warning-500/10"><span class="block text-[11px] opacity-80">{{ __('admin.tkt.pending') }}</span><span class="block text-base tabular-nums">{{ $b['pending'] ?? 0 }}</span></div>
                                <div class="rounded-lg bg-danger-50 py-1.5 font-bold text-danger-700 dark:bg-danger-500/10"><span class="block text-[11px] opacity-80">{{ __('admin.tkt.failed') }}</span><span class="block text-base tabular-nums">{{ $b['cancelled'] ?? 0 }}</span></div>
                            </div>
                            @if(isset($revenue[$t->type->value]))
                                <p class="mt-3 text-xs text-gray-500">{{ __('admin.tkt.revenue') }}: <span class="font-bold text-gray-900 dark:text-white">{{ \App\Support\Formats::money($revenue[$t->type->value]->a, short: true) }}</span></p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- Daily sales (stored line prices, Qatar dates) --}}
        <x-filament::section :heading="__('admin.tkt.daily')" icon="heroicon-o-chart-bar" collapsible>
            <x-slot name="afterHeader">
                <x-filament::link tag="button" wire:click="$toggle('showDaily')">{{ $showDaily ? __('admin.tkt.hide') : __('admin.tkt.show') }}</x-filament::link>
            </x-slot>
            @if($daily && $daily['rows'])
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="text-xs text-gray-500">
                            <tr><th rowspan="2" class="px-3 py-2 text-start">{{ __('ops.live.daily_date') }}</th>
                                @foreach(['vip', 'normal', 'parking'] as $tp)<th colspan="3" class="px-3 py-2">{{ __('enums.ticket_kind.'.$tp) }}</th>@endforeach
                                <th rowspan="2" class="px-3 py-2">{{ __('admin.tkt.daily_total') }}</th></tr>
                            <tr>@foreach(['vip', 'normal', 'parking'] as $tp)<th class="px-2 py-1">{{ __('ops.live.daily_count') }}</th><th class="px-2 py-1">{{ __('ops.live.daily_price') }}</th><th class="px-2 py-1">{{ __('ops.live.daily_amount') }}</th>@endforeach</tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-center dark:divide-white/5">
                            @foreach($daily['rows'] as $r)
                                <tr>
                                    <td class="px-3 py-2 text-start font-bold">{{ \App\Support\Formats::longDate($r['date'].' 12:00:00') }}</td>
                                    @foreach(['vip', 'normal', 'parking'] as $tp)
                                        @php $c = $r['types'][$tp] ?? null; @endphp
                                        <td class="px-2 py-2 tabular-nums">{{ $c['count'] ?? '-' }}</td>
                                        <td class="px-2 py-2 tabular-nums text-gray-500">{{ isset($c['price']) ? \App\Support\Formats::amount($c['price']) : '-' }}</td>
                                        <td class="px-2 py-2 tabular-nums text-danger-600">{{ isset($c['amount']) ? \App\Support\Formats::amount($c['amount']) : '-' }}</td>
                                    @endforeach
                                    <td class="px-3 py-2 font-extrabold tabular-nums">{{ \App\Support\Formats::money($r['total']) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-50 font-extrabold dark:bg-white/5">
                                <td class="px-3 py-2 text-start">{{ __('ops.live.daily_grand') }}</td>
                                @foreach(['vip', 'normal', 'parking'] as $tp)
                                    <td class="px-2 py-2 tabular-nums">{{ $daily['totals'][$tp]['count'] }}</td><td></td><td class="px-2 py-2 tabular-nums text-danger-600">{{ \App\Support\Formats::amount($daily['totals'][$tp]['amount']) }}</td>
                                @endforeach
                                <td class="px-3 py-2 tabular-nums text-primary-600">{{ \App\Support\Formats::money($daily['totals']['total']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @elseif($showDaily)
                <p class="py-6 text-center text-sm text-gray-500">{{ __('ops.live.daily_empty') }}</p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
