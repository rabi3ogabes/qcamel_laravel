@use('App\Support\Formats')
<x-filament-panels::page>
    <div wire:poll.10s.visible class="space-y-6">
        {{-- F-ADM-NTF-001/003: header + Qatar-day filter --}}
        <div class="bezel">
            <div class="flex flex-col gap-4 rounded-[calc(1.75rem-0.375rem)] bg-gradient-to-l from-primary-50 to-white p-5 lg:flex-row lg:items-center dark:from-primary-500/10 dark:to-gray-900">
                <div class="flex flex-1 items-center gap-3">
                    <span class="relative grid size-12 shrink-0 place-items-center rounded-2xl bg-primary-600 text-white shadow-lg shadow-primary-600/30">
                        <x-filament::icon icon="heroicon-o-bell-alert" class="size-6" />
                        @if($unread)
                            <span class="absolute -end-1.5 -top-1.5 grid min-w-5 place-items-center rounded-full bg-danger-600 px-1 text-[11px] font-extrabold tabular-nums ring-2 ring-white dark:ring-gray-900">{{ $unread > 99 ? '+99' : $unread }}</span>
                        @endif
                    </span>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::tabs class="!p-0 !shadow-none !ring-0">
                            @foreach(['today', 'yesterday', 'day', 'all'] as $r)
                                <x-filament::tabs.item :active="$range === $r" wire:click="$set('range', '{{ $r }}')">{{ __('admin.ntf.range.'.$r) }}</x-filament::tabs.item>
                            @endforeach
                        </x-filament::tabs>
                        @if($range === 'day')
                            <x-filament::input.wrapper class="w-44">
                                <x-filament::input type="date" wire:model.live="day" dir="ltr" :max="\App\Support\QatarClock::today()" />
                            </x-filament::input.wrapper>
                        @endif
                        @if($activeDay)
                            <x-filament::badge color="gray" icon="heroicon-m-clock">{{ Formats::longDate($activeDay) }}</x-filament::badge>
                        @endif
                    </div>
                </div>
                <div class="flex gap-2">
                    <x-filament::button color="gray" icon="heroicon-m-arrow-path" wire:click="$refresh" wire:loading.attr="disabled">{{ __('admin.ntf.refresh') }}</x-filament::button>
                    <x-filament::button icon="heroicon-m-check-badge" wire:click="markAllRead" :disabled="! $unread">{{ __('admin.ntf.mark_read') }}</x-filament::button>
                </div>
            </div>
        </div>

        {{-- F-ADM-NTF-004…006: POS vs online --}}
        <div class="grid gap-6 xl:grid-cols-2">
            @foreach($columns as $key => $col)
                @php $isPos = $key === 'pos'; @endphp
                <section wire:key="col-{{ $key }}" @class(['overflow-hidden rounded-[1.5rem] ring-1', 'bg-gold-50/60 ring-gold-200 dark:bg-gold-400/5 dark:ring-gold-400/20' => $isPos, 'bg-primary-50/50 ring-primary-100 dark:bg-primary-500/5 dark:ring-primary-500/20' => ! $isPos])>
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-black/5 p-5 dark:border-white/10">
                        <div class="flex items-center gap-3">
                            <span @class(['grid size-10 place-items-center rounded-xl', 'bg-gold-400/20 text-gold-700' => $isPos, 'bg-primary-600/10 text-primary-600' => ! $isPos])>
                                <x-filament::icon :icon="$isPos ? 'heroicon-o-building-storefront' : 'heroicon-o-credit-card'" class="size-5" />
                            </span>
                            <div>
                                <h2 class="font-extrabold text-gray-950 dark:text-white">{{ __('admin.ntf.'.$key) }} <span class="text-sm font-bold text-gray-400 tabular-nums">({{ $col['total'] }})</span></h2>
                                <p class="text-xs text-gray-500">{{ __('admin.ntf.'.$key.'_sub') }}</p>
                            </div>
                        </div>
                        <div class="text-end">
                            <p class="text-lg font-extrabold tabular-nums text-success-700 dark:text-success-400">{{ Formats::money($col['confirmed'], short: true) }}</p>
                            <p class="text-[11px] text-gray-500">{{ __('admin.ntf.confirmed_n', ['n' => $col['nConfirmed']]) }}@if((float) $col['pending'] > 0) · <span class="text-warning-600">{{ __('admin.ntf.pending_amount', ['a' => Formats::money($col['pending'], short: true)]) }}</span>@endif</p>
                        </div>
                    </header>

                    <div class="max-h-[70vh] overflow-y-auto p-4">
                        @if($col['rows']->isEmpty())
                            <div class="flex flex-col items-center gap-2 py-16 text-center text-gray-400">
                                <x-filament::icon icon="heroicon-o-bell-slash" class="size-8" />
                                <p class="text-sm font-bold">{{ __('admin.ntf.empty') }}</p>
                            </div>
                        @else
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach($col['rows'] as $o)
                                    @php $new = ! $seen || $o->created_at->greaterThan($seen); @endphp
                                    <button type="button" wire:key="n-{{ $o->id }}" wire:click="open('{{ $o->id }}')"
                                        @class(['group relative overflow-hidden rounded-2xl bg-white p-4 text-start shadow-sm ring-1 transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-900', 'ring-primary-300 dark:ring-primary-500/40' => $new, 'ring-gray-950/5 dark:ring-white/10' => ! $new])>
                                        <span @class(['absolute inset-y-0 start-0 w-1', 'bg-gold-400' => $isPos, 'bg-primary-600' => ! $isPos])></span>
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="truncate font-bold text-gray-950 dark:text-white">{{ $o->customer?->name ?: __('admin.ntf.unknown') }}</p>
                                            @if($new)<span class="shrink-0 animate-pulse rounded-full bg-danger-600 px-2 py-0.5 text-[10px] font-extrabold text-white">{{ __('admin.ntf.new') }}</span>@endif
                                        </div>
                                        <div class="mt-2 flex items-center gap-2">
                                            <x-filament::badge size="sm" :color="$statusColor($o->payment_status)">{{ $o->payment_status?->label() }}</x-filament::badge>
                                            <span class="flex items-center gap-1 text-xs text-gray-500"><x-filament::icon icon="heroicon-m-ticket" class="size-3.5" />{{ $o->quantity }}</span>
                                        </div>
                                        <div class="mt-3 flex items-end justify-between gap-2">
                                            <span class="text-base font-extrabold tabular-nums text-primary-700 dark:text-primary-300">{{ Formats::money($o->total_amount, short: true) }}</span>
                                            <span class="text-[11px] tabular-nums text-gray-400" title="{{ Formats::longDateTime($o->created_at) }}">{{ Formats::dayMonth($o->created_at) }} · {{ Formats::timeOf($o->created_at) }}</span>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                            @if($col['total'] > $col['rows']->count())
                                <div class="mt-4 text-center">
                                    <x-filament::button color="gray" size="sm" wire:click="loadMore" icon="heroicon-m-chevron-down">
                                        {{ __('admin.ntf.showing', ['n' => $col['rows']->count(), 'total' => $col['total']]) }} — {{ __('admin.ntf.more') }}
                                    </x-filament::button>
                                </div>
                            @endif
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    {{-- F-ADM-NTF-011: details --}}
    <x-filament::modal id="ntf-details" width="lg" :close-by-clicking-away="true">
        @if($open)
            <x-slot name="heading">
                {{ $open->payment_method?->value === 'cash_pos' ? __('admin.ntf.pos_booking') : __('admin.ntf.online_booking') }}
            </x-slot>
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::badge :color="$statusColor($open->payment_status)">{{ $open->payment_status?->label() }}</x-filament::badge>
                    <x-filament::badge color="gray" icon="heroicon-m-hashtag"><span dir="ltr" class="font-mono">{{ $open->booking_reference }}</span></x-filament::badge>
                </div>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5"><dt class="text-xs text-gray-500">{{ __('admin.dash.customer') }}</dt><dd class="font-bold">{{ $open->customer?->name ?: __('admin.ntf.unknown') }}</dd></div>
                    <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5"><dt class="text-xs text-gray-500">{{ __('admin.dash.phone') }}</dt><dd class="font-bold" dir="ltr">{{ $open->customer?->phone ?: '—' }}</dd></div>
                    <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5"><dt class="text-xs text-gray-500">{{ __('admin.dash.tickets') }}</dt>
                        <dd class="font-bold">{{ $open->holders->groupBy(fn ($h) => $h->ticket_type?->value ?? $h->ticket_type)->map(fn ($g, $t) => $g->count().' '.(\App\Enums\TicketKind::tryFrom((string) $t)?->label() ?? $t))->implode(' + ') ?: $open->quantity }}</dd></div>
                    <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5"><dt class="text-xs text-gray-500">{{ __('admin.dash.amount') }}</dt><dd class="font-extrabold text-primary-700 dark:text-primary-300">{{ Formats::money($open->total_amount) }}</dd></div>
                    <div class="col-span-2 rounded-xl bg-gray-50 p-3 dark:bg-white/5"><dt class="text-xs text-gray-500">{{ __('admin.dash.event') }}</dt><dd class="font-bold">{{ $open->event?->title ?? '—' }}</dd></div>
                    <div class="col-span-2 rounded-xl bg-gray-50 p-3 dark:bg-white/5"><dt class="text-xs text-gray-500">{{ __('admin.dash.when') }}</dt><dd class="font-bold">{{ Formats::longDateTime($open->created_at) }}</dd></div>
                </dl>
            </div>
            <x-slot name="footerActions">
                <x-filament::button tag="a" :href="$orderUrl($open)" icon="heroicon-m-arrow-top-right-on-square">{{ __('admin.ntf.open_order') }}</x-filament::button>
                <x-filament::button tag="a" color="gray" target="_blank" :href="route('invoice.show', $open->booking_reference)" icon="heroicon-m-document-text">{{ __('admin.ntf.invoice') }}</x-filament::button>
            </x-slot>
        @endif
    </x-filament::modal>
</x-filament-panels::page>
