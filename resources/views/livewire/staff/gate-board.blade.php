@php
    $types = ['normal' => ['icon' => 'ticket', 'tone' => 'from-maroon-500 to-maroon-700'], 'vip' => ['icon' => 'crown', 'tone' => 'from-gold-300 to-gold-500'], 'parking' => ['icon' => 'car', 'tone' => 'from-sky-500 to-sky-700']];
    $noEvent = ($d['code'] ?? null) === 'no_current_event';
@endphp
<x-slot:header>
    <x-staff.header :title="__('ops.gate.eyebrow')" :subtitle="$d['event']['title'] ?? __('ops.gate.title')" icon="gate" dark fullscreen status />
</x-slot:header>

<div wire:poll.15s class="mx-auto max-w-7xl px-3 pb-16 pt-6 sm:px-5">
    @if($failed || $noEvent)
        <div class="mx-auto mt-10 max-w-lg rounded-[1.75rem] bg-ops-surface p-8 text-center ring-1 ring-white/10">
            <x-ui.empty dark icon="gate" :title="$noEvent ? __('ops.gate.no_event') : __('ops.gate.load_failed')" class="!py-6" />
        </div>
    @else
        <section class="text-center">
            <p class="text-xs font-bold tracking-[0.3em] text-gold-300">{{ __('ops.gate.eyebrow') }}</p>
            <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">{{ $d['event']['title'] }}</h1>
            <p class="mt-1 text-sm text-ops-muted">{{ \App\Support\Formats::longDate($d['event']['event_date']) }}</p>
        </section>

        {{-- Counters (F-STF-014) --}}
        <section class="mt-8 grid gap-4 sm:grid-cols-3">
            @foreach([['issued', $d['issued'], 'text-ops-fg', 'ticket'], ['scanned', $d['scanned'], 'text-emerald-300', 'user-check'], ['remaining', $d['remaining'], 'text-ops-muted', 'clock']] as [$k, $v, $c, $ic])
                <div class="relative overflow-hidden rounded-[1.75rem] bg-ops-surface p-6 ring-1 ring-white/10">
                    <x-glyph :name="$ic" size="64" stroke="1" class="absolute -end-2 -top-2 text-white/[0.04]" />
                    <p class="text-sm font-bold text-ops-muted">{{ __('ops.gate.'.$k) }}</p>
                    <p class="mt-2 text-6xl font-extrabold tabular-nums {{ $c }}">{{ number_format($v) }}</p>
                </div>
            @endforeach
        </section>

        {{-- Attendance progress (F-STF-015) --}}
        <section class="mt-5 rounded-[1.75rem] bg-ops-surface p-6 ring-1 ring-white/10">
            <div class="flex items-end justify-between">
                <p class="font-bold">{{ __('ops.gate.rate') }}</p>
                <p class="text-4xl font-extrabold tabular-nums text-gold-300">{{ $d['percent'] }}%</p>
            </div>
            <div class="mt-4 h-3 overflow-hidden rounded-full bg-white/5">
                <div class="h-full rounded-full bg-gradient-to-l from-gold-400 via-emerald-400 to-emerald-500 transition-[width] duration-700 ease-[cubic-bezier(0.32,0.72,0,1)]" style="width: {{ $d['percent'] }}%"></div>
            </div>
            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                @foreach($types as $type => $cfg)
                    @php $t = $d['totals'][$type] ?? ['issued' => 0, 'scanned' => 0]; $p = $t['issued'] > 0 ? round($t['scanned'] / $t['issued'] * 100) : 0; @endphp
                    <div class="flex items-center gap-4 rounded-2xl bg-ops-elevated/60 p-4 ring-1 ring-white/5">
                        <span class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br {{ $cfg['tone'] }} {{ $type === 'vip' ? 'text-ink' : 'text-white' }}"><x-glyph :name="$cfg['icon']" size="22" /></span>
                        <div class="flex-1">
                            <p class="text-sm font-bold text-ops-muted">{{ __('enums.ticket_kind.'.$type) }}</p>
                            <p class="text-2xl font-extrabold tabular-nums">{{ $t['scanned'] }} <span class="text-base text-ops-muted">/ {{ $t['issued'] }}</span></p>
                        </div>
                        <span class="text-sm font-bold text-ops-muted">{{ $p }}%</span>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
            {{-- Last 25 scans (F-STF-016) --}}
            <section class="rounded-[1.75rem] bg-ops-surface p-6 ring-1 ring-white/10">
                <h2 class="font-extrabold">{{ __('ops.gate.recent') }}</h2>
                <ul class="mt-4 divide-y divide-white/5">
                    @forelse($d['recent'] as $r)
                        <li wire:key="gr-{{ $r['id'] }}" class="flex items-center gap-3 py-3">
                            <span class="grid size-10 place-items-center rounded-xl bg-gradient-to-br {{ $types[$r['ticket_type']]['tone'] ?? $types['normal']['tone'] }} {{ $r['ticket_type'] === 'vip' ? 'text-ink' : 'text-white' }}"><x-glyph :name="$types[$r['ticket_type']]['icon'] ?? 'ticket'" size="18" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-bold">{{ $r['name'] }}</p>
                                <p class="truncate text-xs text-ops-muted">{{ __('enums.ticket_kind.'.$r['ticket_type']) }} — {{ $r['confirmed_by_name'] ?? '—' }}</p>
                            </div>
                            <span class="text-sm tabular-nums text-ops-muted">{{ $r['time'] ?: '-' }}</span>
                        </li>
                    @empty
                        <li><x-ui.empty dark icon="scan" :title="__('ops.gate.recent_empty')" class="!py-10" /></li>
                    @endforelse
                </ul>
            </section>

            {{-- NEW: per-gate-staff counts --}}
            <section class="rounded-[1.75rem] bg-ops-surface p-6 ring-1 ring-white/10">
                <h2 class="font-extrabold">{{ __('ops.gate.by_staff') }}</h2>
                @php $max = max(1, ...array_values($d['by_staff'] ?: [1])); @endphp
                <ul class="mt-4 space-y-3">
                    @forelse($d['by_staff'] as $name => $n)
                        <li>
                            <div class="flex justify-between text-sm"><span class="font-bold">{{ $name }}</span><span class="tabular-nums text-ops-muted">{{ $n }}</span></div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/5"><div class="h-full rounded-full bg-gold-400" style="width: {{ round($n / $max * 100) }}%"></div></div>
                        </li>
                    @empty
                        <li class="text-sm text-ops-muted">—</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <p class="mt-6 text-center text-xs text-ops-muted">{{ __('ops.shell.updated_at', ['time' => \App\Support\Formats::timeOf($d['generated_at'])]) }}</p>
    @endif
</div>
