@php
    $kindIcon = ['vip' => 'crown', 'normal' => 'ticket', 'parking' => 'car'];
    $kindTone = ['vip' => 'from-gold-300 to-gold-500 text-ink', 'normal' => 'from-emerald-500 to-emerald-700 text-white', 'parking' => 'from-sky-500 to-sky-700 text-white'];
    $chips = ['vip' => 'dark-warning', 'normal' => 'dark-success', 'parking' => 'dark'];
    $channelCfg = [
        'online' => ['icon' => 'globe', 'label' => __('ops.live.online'), 'sub' => __('ops.live.online_total'), 'ring' => 'ring-sky-400/25', 'text' => 'text-sky-300'],
        'pos' => ['icon' => 'cash', 'label' => __('ops.live.pos'), 'sub' => __('ops.live.pos_total'), 'ring' => 'ring-orange-400/25', 'text' => 'text-orange-300'],
        'venue' => ['icon' => 'card', 'label' => __('ops.live.venue_cash'), 'sub' => __('ops.live.venue_cash'), 'ring' => 'ring-gold-400/25', 'text' => 'text-gold-300'],
    ];
@endphp
<x-slot:header>
    <x-staff.header :title="__('ops.live.title')" :subtitle="$dateLabel" icon="live" dark sound fullscreen status>
        <button type="button" x-data="{ on: (() => { try { return localStorage.getItem('qc:live:notify') !== '0' } catch { return true } })() }"
            @click="on = !on; try { localStorage.setItem('qc:live:notify', on ? '1' : '0') } catch {}; window.dispatchEvent(new CustomEvent('live-notify', { detail: on }))"
            :title="on ? @js(__('ops.live.notify_off')) : @js(__('ops.live.notify_on'))"
            class="grid size-10 place-items-center rounded-full transition hover:bg-white/10" :class="on ? 'text-sky-300' : 'opacity-50'">
            <x-glyph name="user-check" size="19" />
        </button>
        <button type="button" wire:click="openDaily" class="inline-flex h-10 items-center gap-2 rounded-full bg-white/10 px-3 text-sm font-bold ring-1 ring-white/15 hover:bg-white/15">
            <x-glyph name="chart" size="17" /><span class="hidden sm:inline">{{ __('ops.live.stats') }}</span>
        </button>
    </x-staff.header>
</x-slot:header>

<div wire:poll.10s.visible="tick" class="mx-auto max-w-[1600px] px-3 pb-16 pt-5 sm:px-5"
     x-data="{ notify: (() => { try { return localStorage.getItem('qc:live:notify') !== '0' } catch { return true } })() }"
     x-on:live-notify.window="notify = $event.detail"
     x-on:live-sale.window="
        const d = $event.detail;
        if (d.kind === 'pos') window.qc.sfx.cash(); else window.qc.sfx.chime();
        $store.toasts.push({ type: 'info', title: d.title, message: [d.line1, d.line2, d.line3].filter(Boolean).join('\n'), duration: d.kind === 'pos' ? 6000 : 5000 });"
     x-on:live-checkin.window="
        if (!notify) return;
        const d = $event.detail;
        window.qc.sfx.checkin(d.type);
        $store.toasts.push({ type: 'success', title: d.name + ' ' + d.flag, message: d.label + '\n' + @js(__('ops.live.checkin_toast')), duration: 5000 });">

    {{-- KPI tiles (F-LIVE-007 rebuild: revenue instead of the duplicate "confirmed") --}}
    <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-[1.4rem] bg-ops-surface p-4 ring-1 ring-white/10">
            <p class="text-xs font-bold text-ops-muted">{{ __('ops.live.date') }}</p>
            <input type="date" wire:model.live="date" dir="ltr" class="mt-2 h-10 w-full rounded-xl border-0 bg-ops-bg px-3 text-sm text-ops-fg ring-1 ring-white/10 [color-scheme:dark]">
            <button type="button" wire:click="showAll" @class(['mt-2 w-full rounded-lg py-1 text-xs font-bold', 'bg-gold-400 text-ink' => $all, 'text-gold-300 hover:bg-white/5' => ! $all])>{{ __('ops.live.show_all') }}</button>
        </div>
        @foreach([['total_bookings', number_format($s['bookings']), 'text-sky-300', 'users'], ['revenue', \App\Support\Formats::money($s['revenue'], short: true), 'text-gold-300', 'cash'], ['total_tickets', number_format($s['tickets']), 'text-purple-300', 'ticket'], ['present', number_format($s['present']), 'text-orange-300', 'user-check'], ['last_hour', number_format($s['last_hour']), 'text-cyan-300', 'clock']] as [$k, $v, $c, $ic])
            <div class="relative overflow-hidden rounded-[1.4rem] bg-ops-surface p-4 ring-1 ring-white/10">
                <x-glyph :name="$ic" size="54" stroke="1" class="absolute -end-2 -top-2 text-white/[0.05]" />
                <p class="text-xs font-bold text-ops-muted">{{ __('ops.live.'.$k) }}</p>
                <p class="mt-2 truncate text-3xl font-extrabold tabular-nums {{ $c }}">{{ $v }}</p>
            </div>
        @endforeach
    </section>

    {{-- Channels (F-LIVE-009) + types (F-LIVE-010) --}}
    <section class="mt-4 grid gap-3 lg:grid-cols-3">
        @foreach($channelCfg as $ch => $cfg)
            @php $c = $s['channels'][$ch] ?? ['total' => 0, 'types' => []]; @endphp
            @continue($ch === 'venue' && ($c['total'] ?? 0) === 0)
            <div class="rounded-[1.4rem] bg-ops-surface p-5 ring-1 {{ $cfg['ring'] }}">
                <div class="flex items-center justify-between">
                    <span class="inline-flex items-center gap-2 font-bold {{ $cfg['text'] }}"><x-glyph :name="$cfg['icon']" size="18" />{{ $cfg['label'] }}</span>
                    <span class="text-3xl font-extrabold tabular-nums">{{ $c['total'] ?? 0 }}</span>
                </div>
                <p class="text-xs text-ops-muted">{{ $cfg['sub'] }}</p>
                <ul class="mt-3 space-y-1.5 text-sm">
                    @foreach(collect($c['types'] ?? [])->sortByDesc('total') as $t => $v)
                        <li class="flex items-center justify-between rounded-xl bg-ops-bg/60 px-3 py-2">
                            <span class="font-bold">{{ __('enums.ticket_kind.'.$t) }}</span>
                            <span class="text-ops-muted">{{ __('ops.live.booked', ['n' => $v['total']]) }} · <span class="text-emerald-300">{{ __('ops.live.attended', ['n' => $v['present']]) }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        <div class="rounded-[1.4rem] bg-ops-surface p-5 ring-1 ring-white/10">
            <p class="font-bold">{{ __('ops.live.by_type') }}</p>
            <div class="mt-3 space-y-2">
                @foreach($s['types'] as $t => $v)
                    <div class="flex items-center gap-3 rounded-xl bg-ops-bg/60 p-2.5">
                        <span class="grid size-9 place-items-center rounded-xl bg-gradient-to-br {{ $kindTone[$t] }}"><x-glyph :name="$kindIcon[$t]" size="17" /></span>
                        <span class="flex-1 text-sm font-bold">{{ __('enums.ticket_kind.'.$t) }}</span>
                        <span class="text-sm tabular-nums">{{ $v['total'] }}@if($v['capacity']) <span class="text-ops-muted">/ {{ $v['capacity'] }}</span>@endif</span>
                        <span class="text-sm font-bold tabular-nums text-emerald-300">{{ $v['present'] }} ✓</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mt-4 grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div>
            {{-- Search + view toggle --}}
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <div class="min-w-56 flex-1"><x-ui.input dark wire:model.live.debounce.300ms="search" icon="search" :placeholder="__('ops.live.search_ph')" /></div>
                <div class="flex rounded-full bg-ops-surface p-1 ring-1 ring-white/10">
                    <button type="button" wire:click="$set('view', 'cards')" @class(['rounded-full px-4 py-2 text-sm font-bold', 'bg-white/10' => $view === 'cards', 'text-ops-muted' => $view !== 'cards'])>{{ __('ops.live.cards') }}</button>
                    <button type="button" wire:click="$set('view', 'table')" @class(['rounded-full px-4 py-2 text-sm font-bold', 'bg-white/10' => $view === 'table', 'text-ops-muted' => $view !== 'table'])>{{ __('ops.live.table') }}</button>
                </div>
            </div>

            @if($holders->isEmpty())
                <div class="rounded-[1.4rem] bg-ops-surface ring-1 ring-white/10"><x-ui.empty dark icon="ticket" :title="__('ops.live.empty')" /></div>
            @elseif($view === 'cards')
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    @foreach($holders as $h)
                        <article wire:key="lh-{{ $h['id'] }}" @class(['relative rounded-[1.4rem] bg-ops-surface p-4 ring-1 transition', 'ring-emerald-400/30' => $h['present'], 'ring-white/10' => ! $h['present'], 'animate-recent-pulse ring-2 ring-gold-400/50' => $h['recent']])>
                            <span class="absolute -end-2 -top-2 grid min-w-8 place-items-center rounded-full bg-maroon-600 px-2 py-1 text-xs font-extrabold text-white shadow-soft">{{ $h['seq'] }}</span>
                            <p class="truncate rounded-full bg-white/5 px-2 py-0.5 text-[11px] text-ops-muted">{{ $h['event'] }}</p>
                            <div class="mt-2 flex items-start gap-2">
                                <p class="min-w-0 flex-1 truncate text-lg font-extrabold">{{ $h['name'] }} <span>{{ $h['flag'] }}</span></p>
                                <span class="flex shrink-0 items-center gap-1 text-ops-muted">
                                    @if($h['is_pos'])
                                        <span title="POS - {{ $h['pos_user'] }}" class="text-orange-300"><x-glyph name="cash" size="17" /></span>
                                    @elseif($h['sent'])
                                        <span title="{{ __('ops.live.invoice_sent') }}" class="text-emerald-300"><x-glyph name="whatsapp" size="16" /></span>
                                    @else
                                        <span title="{{ __('ops.live.invoice_not_sent') }}" class="opacity-50"><x-glyph name="clock" size="16" /></span>
                                    @endif
                                    @if($h['downloaded'])<span title="{{ __('ops.live.invoice_downloaded') }}" class="text-sky-300"><x-glyph name="download" size="16" /></span>@endif
                                </span>
                            </div>
                            <p class="font-mono text-xs text-ops-muted" dir="ltr">{{ $h['phone'] }}</p>
                            <div class="mt-1 flex items-center gap-1">
                                <span class="truncate font-mono text-xs text-ops-muted" dir="ltr">{{ $h['reference'] }}</span>
                                <button type="button" x-data="copyText(@js($h['reference']))" @click="copy()" class="opacity-60 hover:opacity-100" title="{{ __('common.copy') }}"><x-glyph name="copy" size="13" /></button>
                            </div>
                            @if($h['lifetime'] > 0)
                                <p class="mt-2 inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-bold {{ $h['lifetime'] > 1 ? 'bg-gold-400/15 text-gold-200' : 'bg-white/5 text-ops-muted' }}" title="{{ __('ops.live.lifetime_hint') }}">{{ __('ops.live.lifetime') }} <span class="tabular-nums">{{ $h['lifetime'] }}</span></p>
                            @endif
                            <div class="mt-3 flex items-center justify-between">
                                <x-ui.badge size="sm" :tone="$chips[$h['type']] ?? 'dark'" :icon="$kindIcon[$h['type']]">{{ $h['type_label'] }}</x-ui.badge>
                                <span @class(['text-xs font-bold', 'text-emerald-300' => $h['present'], 'text-ops-muted' => ! $h['present']])>{{ $h['present'] ? __('ops.live.attending') : __('ops.live.not_attending') }}</span>
                            </div>
                            <p class="mt-2 border-t border-white/5 pt-2 text-[11px] text-ops-muted">{{ __('ops.live.booked_at', ['time' => \App\Support\Formats::dateTime($h['created_at'])]) }}@if($h['present'] && $h['checked_in_at']) · ✓ {{ \App\Support\Formats::timeOf($h['checked_in_at']) }}@endif</p>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="overflow-x-auto rounded-[1.4rem] bg-ops-surface ring-1 ring-white/10">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-ops-muted">
                            <tr class="border-b border-white/5">
                                <th class="px-4 py-3 text-start">#</th>
                                <th class="px-4 py-3 text-start">{{ __('ops.live.col_name') }}</th>
                                <th class="hidden px-4 py-3 text-start sm:table-cell">{{ __('ops.live.col_phone') }}</th>
                                <th class="hidden px-4 py-3 text-start md:table-cell">{{ __('ops.live.col_nationality') }}</th>
                                <th class="hidden px-4 py-3 text-start lg:table-cell">{{ __('ops.live.col_ref') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('ops.live.lifetime') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('ops.live.col_type') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('ops.live.col_attendance') }}</th>
                                <th class="hidden px-4 py-3 text-start xl:table-cell">{{ __('ops.live.col_confirmed') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($holders as $h)
                                <tr wire:key="lt-{{ $h['id'] }}" @class(['bg-emerald-500/[0.06]' => $h['present'], 'bg-gold-400/[0.06]' => $h['recent'] && ! $h['present']])>
                                    <td class="px-4 py-2.5 tabular-nums text-ops-muted">{{ $h['seq'] }}</td>
                                    <td class="px-4 py-2.5 font-bold">{{ $h['name'] }} <span class="md:hidden">{{ $h['flag'] }}</span></td>
                                    <td class="hidden px-4 py-2.5 font-mono text-xs text-ops-muted sm:table-cell" dir="ltr">{{ $h['phone'] }}</td>
                                    <td class="hidden px-4 py-2.5 md:table-cell">{{ $h['flag'] }} <span class="hidden lg:inline">{{ $h['nationality'] }}</span></td>
                                    <td class="hidden px-4 py-2.5 font-mono text-xs lg:table-cell" dir="ltr">{{ $h['reference'] }}</td>
                                    <td class="px-4 py-2.5"><span class="rounded-md px-2 py-0.5 text-xs font-bold tabular-nums {{ $h['lifetime'] > 1 ? 'bg-gold-400/15 text-gold-200' : 'bg-white/5 text-ops-muted' }}">{{ $h['lifetime'] }}</span></td>
                                    <td class="px-4 py-2.5"><x-ui.badge size="sm" :tone="$chips[$h['type']] ?? 'dark'">{{ $h['type_label'] }}</x-ui.badge></td>
                                    <td class="px-4 py-2.5 text-xs font-bold {{ $h['present'] ? 'text-emerald-300' : 'text-ops-muted' }}">{{ $h['present'] ? __('ops.live.attending') : __('ops.live.not_attending') }}</td>
                                    <td class="hidden px-4 py-2.5 text-xs text-ops-muted xl:table-cell">{{ \App\Support\Formats::dateTime($h['confirmed_at']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Nationalities (F-LIVE-011) --}}
        <aside class="rounded-[1.4rem] bg-ops-surface p-5 ring-1 ring-white/10 xl:sticky xl:top-24">
            <p class="font-bold">{{ __('ops.live.by_nationality') }}</p>
            <ul class="mt-3 max-h-[60vh] space-y-1.5 overflow-y-auto pe-1 text-sm">
                @forelse($s['nationalities'] as $n)
                    <li class="flex items-center gap-2 rounded-xl bg-ops-bg/60 px-3 py-2">
                        <span class="text-lg">{{ $n['flag'] }}</span>
                        <span class="min-w-0 flex-1 truncate font-bold">{{ $n['name'] }}</span>
                        <span class="text-xs text-sky-300" title="{{ __('ops.live.online') }}">{{ $n['online'] }}</span>
                        <span class="text-xs text-orange-300" title="{{ __('ops.live.pos') }}">{{ $n['pos'] }}</span>
                        <span class="text-xs text-emerald-300" title="{{ __('ops.live.present') }}">{{ $n['present'] }}✓</span>
                        <span class="rounded-md bg-white/10 px-2 py-0.5 text-xs font-extrabold tabular-nums">{{ $n['total'] }}</span>
                    </li>
                @empty
                    <li class="text-ops-muted">—</li>
                @endforelse
            </ul>
        </aside>
    </section>

    {{-- Daily sales (F-LIVE-028/029 rebuild: stored line prices, Qatar dates) --}}
    <x-ui.dialog name="daily-sales" :title="__('ops.live.stats_title')" max-width="sm:max-w-5xl" dark>
        @if($daily && $daily['rows'])
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="text-xs text-ops-muted">
                        <tr><th rowspan="2" class="px-3 py-2 text-start">{{ __('ops.live.daily_date') }}</th>
                            @foreach(['vip', 'normal', 'parking'] as $t)<th colspan="3" class="px-3 py-2">{{ __('enums.ticket_kind.'.$t) }}</th>@endforeach
                            <th rowspan="2" class="px-3 py-2">{{ __('ops.live.daily_total') }}</th></tr>
                        <tr>@foreach(['vip', 'normal', 'parking'] as $t)<th class="px-2 py-1">{{ __('ops.live.daily_count') }}</th><th class="px-2 py-1">{{ __('ops.live.daily_price') }}</th><th class="px-2 py-1">{{ __('ops.live.daily_amount') }}</th>@endforeach</tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-center">
                        @foreach($daily['rows'] as $r)
                            <tr>
                                <td class="px-3 py-2 text-start font-bold">{{ \App\Support\Formats::longDate($r['date'].' 12:00:00') }}</td>
                                @foreach(['vip', 'normal', 'parking'] as $t)
                                    @php $c = $r['types'][$t] ?? null; @endphp
                                    <td class="px-2 py-2 tabular-nums">{{ $c['count'] ?? '-' }}</td>
                                    <td class="px-2 py-2 tabular-nums text-ops-muted">{{ isset($c['price']) ? (int) $c['price'] : '-' }}</td>
                                    <td class="px-2 py-2 tabular-nums text-red-300">{{ isset($c['amount']) ? \App\Support\Formats::amount($c['amount']) : '-' }}</td>
                                @endforeach
                                <td class="px-3 py-2 font-extrabold tabular-nums">{{ \App\Support\Formats::money($r['total'], short: true) }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-white/5 font-extrabold">
                            <td class="px-3 py-2 text-start">{{ __('ops.live.daily_grand') }}</td>
                            @foreach(['vip', 'normal', 'parking'] as $t)
                                <td class="px-2 py-2 tabular-nums">{{ $daily['totals'][$t]['count'] }}</td><td class="px-2 py-2">—</td><td class="px-2 py-2 tabular-nums text-red-300">{{ \App\Support\Formats::amount($daily['totals'][$t]['amount']) }}</td>
                            @endforeach
                            <td class="px-3 py-2 tabular-nums text-gold-300">{{ \App\Support\Formats::money($daily['totals']['total'], short: true) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @else
            <x-ui.empty dark icon="chart" :title="__('ops.live.daily_empty')" class="!py-10" />
        @endif
    </x-ui.dialog>
</div>
