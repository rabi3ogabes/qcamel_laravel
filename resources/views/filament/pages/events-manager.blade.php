@use('App\Support\Formats')
<x-filament-panels::page>
    @php
        $kindIcon = ['vip' => '👑', 'normal' => '🎫', 'parking' => '🚗'];
        $bar = fn (int $pct) => $pct > 80 ? 'from-danger-500 to-danger-400' : ($pct >= 50 ? 'from-warning-500 to-gold-400' : 'from-success-600 to-success-400');
        $tone = fn (int $pct) => $pct > 80 ? 'danger' : ($pct >= 50 ? 'warning' : 'success');
    @endphp

    {{-- F-ADM-EVT-022/023: the event customers see --}}
    <div class="bezel">
        <div class="flex flex-col gap-4 rounded-[calc(1.75rem-0.375rem)] bg-gradient-to-l from-primary-50 to-white p-5 sm:flex-row sm:items-center dark:from-primary-500/10 dark:to-transparent">
            <div class="flex flex-1 items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-gold-400/20 text-gold-600 ring-1 ring-gold-400/40">
                    <x-filament::icon icon="heroicon-s-star" class="size-5" />
                </span>
                <div>
                    <p class="font-extrabold text-gray-950 dark:text-white">{{ __('admin.evt.current') }}</p>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.evt.current_hint') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2 sm:w-96">
                <x-filament::input.wrapper class="flex-1" :disabled="! $canManage">
                    <x-filament::input.select wire:model.live="currentEventId" :disabled="! $canManage">
                        <option value="">{{ __('admin.evt.current_all') }}</option>
                        @foreach($selectable as $e)
                            <option value="{{ $e->id }}">{{ Formats::dayMonth($e->event_date) }} — {{ $e->title }}{{ $e->is_active ? '' : ' ('.__('admin.evt.inactive').')' }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                @if($currentEventId && $canManage)
                    <x-filament::button color="gray" size="sm" wire:click="$set('currentEventId', null)">{{ __('admin.evt.current_clear') }}</x-filament::button>
                @endif
            </div>
        </div>
    </div>

    {{-- F-ADM-EVT-003/004: views + date filter --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-filament::tabs>
            <x-filament::tabs.item :active="! $archived" wire:click="$set('season', 'current')" icon="heroicon-o-calendar">{{ __('common.current') }}</x-filament::tabs.item>
            <x-filament::tabs.item :active="$archived" wire:click="$set('season', 'archive')" icon="heroicon-o-archive-box">{{ __('common.archive') }}</x-filament::tabs.item>
        </x-filament::tabs>
        <div class="flex items-center gap-2">
            <x-filament::input.wrapper prefix-icon="heroicon-o-calendar-days">
                <x-filament::input type="date" wire:model.live="date" :aria-label="__('admin.evt.filter_date')" />
            </x-filament::input.wrapper>
            @if($filterDate)
                <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" wire:click="clearDate" :label="__('admin.evt.filter_clear')" />
            @endif
        </div>
    </div>

    @forelse($folders as $i => $folder)
        <section x-data="{ open: @js(! $archived) }" wire:key="folder-{{ $season }}-{{ $folder['label'] }}" class="space-y-4">
            <button type="button" x-on:click="open = ! open" class="group flex w-full items-center gap-3 text-start">
                <span class="grid size-9 place-items-center rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-folder" class="size-5 text-gold-600" x-show="! open" />
                    <x-filament::icon icon="heroicon-o-folder-open" class="size-5 text-gold-600" x-show="open" x-cloak />
                </span>
                <span class="text-lg font-extrabold text-gray-950 dark:text-white">{{ $folder['label'] }}</span>
                <x-filament::badge color="gray">{{ trans_choice('admin.evt.n_events', $folder['events']->count(), ['n' => $folder['events']->count()]) }}</x-filament::badge>
                <span class="gold-rule hidden flex-1 sm:block"></span>
                <x-filament::icon icon="heroicon-m-chevron-down" class="size-5 text-gray-400 transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]" x-bind:class="open && 'rotate-180'" />
            </button>

            <div x-show="open" x-collapse class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach($folder['events'] as $e)
                    @php
                        $conf = $confirmed[$e->id] ?? collect();
                        $types = $e->ticketTypes->sortBy(fn ($t) => $t->type->sortWeight())->values();
                        $sumCap = (int) $types->sum('available_quantity');
                        $sumHeld = (int) $types->sum('sold_quantity');
                        $sumConf = (int) $types->sum(fn ($t) => $conf[$t->type->value] ?? 0);
                        $totalPct = $sumCap > 0 ? (int) round($sumHeld / $sumCap * 100) : 0;
                        $isCurrent = $currentEventId === $e->id;
                        $cover = $e->coverUrl();
                        $multi = $e->end_date && $e->lastDay() !== $e->firstDay();
                        $live = ! $archived && $e->isToday($today);
                    @endphp
                    <article wire:key="ev-{{ $e->id }}" class="bezel">
                        <div class="flex h-full flex-col overflow-hidden rounded-[calc(1.75rem-0.375rem)] bg-white dark:bg-gray-900">
                            {{-- Cover --}}
                            <div class="relative h-40 overflow-hidden bg-gradient-to-br from-primary-700 via-primary-600 to-gold-500">
                                @if($cover)
                                    <img src="{{ $cover }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover">
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"></div>
                                <div class="absolute inset-x-4 top-4 flex items-center justify-between gap-2">
                                    <span class="rounded-full bg-black/40 px-2.5 py-1 text-[11px] font-bold tabular-nums text-white ring-1 ring-white/20 backdrop-blur">#{{ $e->display_order ?: 0 }}</span>
                                    <div class="flex gap-1.5">
                                        @if($isCurrent)<span class="rounded-full bg-gold-400 px-2.5 py-1 text-[11px] font-extrabold text-gray-950">★ {{ __('admin.evt.current_badge') }}</span>@endif
                                        @if($live)<span class="flex items-center gap-1 rounded-full bg-success-500 px-2.5 py-1 text-[11px] font-extrabold text-white"><span class="size-1.5 animate-pulse rounded-full bg-white"></span>{{ __('admin.evt.live') }}</span>@endif
                                    </div>
                                </div>
                                <div class="absolute inset-x-4 bottom-3">
                                    <h3 class="line-clamp-1 text-lg font-extrabold text-white">{{ $e->title }}</h3>
                                    <p class="flex items-center gap-1 text-xs text-white/80"><x-filament::icon icon="heroicon-m-map-pin" class="size-3.5" />{{ $e->location }}</p>
                                </div>
                            </div>

                            <div class="flex flex-1 flex-col gap-4 p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="text-sm">
                                        <p class="font-bold text-gray-950 dark:text-white">
                                            {{ Formats::longDate($e->event_date, false) }}@if($multi) — {{ Formats::longDate($e->end_date, false) }}@endif
                                        </p>
                                        @if($e->start_time)
                                            <p class="mt-0.5 text-xs text-gray-500" dir="ltr">{{ Formats::time12($e->start_time) }}@if($e->end_time) – {{ Formats::time12($e->end_time) }}@endif</p>
                                        @endif
                                    </div>
                                    @if(! $archived)
                                        <label class="flex shrink-0 cursor-pointer items-center gap-2 text-xs font-bold">
                                            <span @class(['text-success-600' => $e->is_active, 'text-gray-400' => ! $e->is_active])>{{ $e->is_active ? __('admin.evt.active') : __('admin.evt.inactive') }}</span>
                                            <button type="button" role="switch" aria-checked="{{ $e->is_active ? 'true' : 'false' }}" @disabled(! $canManage)
                                                wire:click="toggleActive('{{ $e->id }}')" wire:loading.attr="disabled"
                                                @class(['relative h-6 w-11 rounded-full transition-colors duration-300', 'bg-success-500' => $e->is_active, 'bg-gray-300 dark:bg-white/20' => ! $e->is_active])>
                                                <span @class(['absolute top-0.5 size-5 rounded-full bg-white shadow transition-all duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]', 'start-[1.375rem]' => $e->is_active, 'start-0.5' => ! $e->is_active])></span>
                                            </button>
                                        </label>
                                    @else
                                        <x-filament::badge color="gray" icon="heroicon-m-archive-box">{{ __('admin.evt.archived_badge') }}</x-filament::badge>
                                    @endif
                                </div>

                                @if($e->description)
                                    <p class="line-clamp-2 text-sm text-gray-500 dark:text-gray-400">{{ $e->description }}</p>
                                @endif

                                {{-- F-ADM-EVT-008/009 + U-03: confirmed and held --}}
                                @if($types->isNotEmpty())
                                    <div class="space-y-3 rounded-2xl bg-gray-50 p-4 dark:bg-white/5">
                                        @foreach($types as $t)
                                            @php
                                                $cap = (int) $t->available_quantity; $held = (int) $t->sold_quantity; $c = (int) ($conf[$t->type->value] ?? 0);
                                                $pctHeld = $cap > 0 ? min(100, (int) round($held / $cap * 100)) : 0;
                                                $pctConf = $cap > 0 ? min(100, (int) round($c / $cap * 100)) : 0;
                                            @endphp
                                            <div>
                                                <div class="flex items-center justify-between gap-2 text-xs">
                                                    <span class="font-bold">{{ $kindIcon[$t->type->value] }} {{ $t->type->label() }} <span class="font-normal text-gray-400">· {{ Formats::money($t->price, short: true) }}</span></span>
                                                    <span class="tabular-nums text-gray-500">
                                                        <b class="text-gray-950 dark:text-white">{{ $c }}</b> {{ __('admin.evt.confirmed_short') }}
                                                        @if($held > $c)<span class="text-warning-600">+{{ $held - $c }} {{ __('admin.evt.pending_short') }}</span>@endif
                                                        / {{ $cap }}
                                                    </span>
                                                </div>
                                                <div class="relative mt-1.5 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10" title="{{ $pctHeld }}%">
                                                    <div class="absolute inset-y-0 start-0 rounded-full bg-warning-300/70 dark:bg-warning-400/40" style="width: {{ $pctHeld }}%"></div>
                                                    <div class="absolute inset-y-0 start-0 rounded-full bg-gradient-to-l {{ $bar($pctHeld) }}" style="width: {{ $pctConf }}%"></div>
                                                </div>
                                            </div>
                                        @endforeach
                                        <div class="flex items-center justify-between border-t border-gray-200 pt-3 text-xs dark:border-white/10">
                                            <span class="font-bold">{{ __('admin.evt.total') }}</span>
                                            <span class="flex items-center gap-2 tabular-nums">
                                                <span>{{ $sumConf }} / {{ $sumCap }}</span>
                                                <x-filament::badge :color="$tone($totalPct)" size="sm">{{ $totalPct }}%</x-filament::badge>
                                            </span>
                                        </div>
                                    </div>
                                @else
                                    <p class="rounded-2xl border border-dashed border-gray-300 p-4 text-center text-xs text-gray-500 dark:border-white/15">{{ __('admin.evt.no_types') }}</p>
                                @endif

                                {{-- U-ADM-EVT-02: KPIs --}}
                                <dl class="grid grid-cols-2 gap-2 text-center">
                                    <div class="rounded-xl bg-success-50 p-2.5 dark:bg-success-500/10"><dt class="text-[11px] font-bold text-success-700 dark:text-success-400">{{ __('admin.evt.revenue') }}</dt><dd class="text-sm font-extrabold tabular-nums">{{ Formats::money($revenue[$e->id] ?? 0, short: true) }}</dd></div>
                                    <div class="rounded-xl bg-primary-50 p-2.5 dark:bg-primary-500/10"><dt class="text-[11px] font-bold text-primary-700 dark:text-primary-300">{{ __('admin.evt.scanned') }}</dt><dd class="text-sm font-extrabold tabular-nums">{{ (int) ($scanned[$e->id] ?? 0) }} / {{ $sumConf }}</dd></div>
                                </dl>

                                <div class="mt-auto flex items-center justify-between gap-2 border-t border-gray-100 pt-4 dark:border-white/10">
                                    <div class="flex gap-2">
                                        {{ ($this->editEventAction)(['id' => $e->id]) }}
                                        {{ ($this->duplicateEventAction)(['id' => $e->id]) }}
                                    </div>
                                    <div class="flex items-center gap-1">
                                        {{ ($this->archiveEventAction)(['id' => $e->id, 'archived' => (bool) $e->is_archived]) }}
                                        {{ ($this->deleteEventAction)(['id' => $e->id]) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <x-filament::section>
            <div class="flex flex-col items-center gap-3 py-12 text-center">
                <span class="grid size-14 place-items-center rounded-2xl bg-gold-400/15 text-gold-600"><x-filament::icon icon="heroicon-o-calendar-days" class="size-7" /></span>
                <p class="font-extrabold">{{ $filterDate ? __('admin.evt.empty_date') : __('admin.evt.empty') }}</p>
                @if(! $filterDate && ! $archived && $canManage)
                    <p class="max-w-sm text-sm text-gray-500">{{ __('admin.evt.empty_hint') }}</p>
                @endif
            </div>
        </x-filament::section>
    @endforelse

</x-filament-panels::page>
