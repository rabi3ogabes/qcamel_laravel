@php
    $kindIcon = ['vip' => 'crown', 'normal' => 'ticket', 'parking' => 'car'];
    $kindTone = ['vip' => 'from-gold-300 to-gold-500 text-ink', 'normal' => 'from-maroon-500 to-maroon-700 text-white', 'parking' => 'from-sky-500 to-sky-700 text-white'];
    $openCount = collect($tickets)->filter(fn ($r) => ! $r['is_present'] && $r['admissible'])->count();
    $first = $tickets[0] ?? null;
    $camMsgs = [
        'noApi' => __('ops.scanner.no_camera_api'), 'noCamera' => __('ops.scanner.no_camera'), 'denied' => __('ops.scanner.camera_denied'),
        'busy' => __('ops.scanner.camera_busy'), 'failed' => __('ops.scanner.camera_failed'), 'started' => __('ops.scanner.camera_started'),
    ];
@endphp
<x-slot:head>
    @vite(['resources/js/scanner.js'])
</x-slot:head>

<x-slot:header>
    <x-staff.header :title="__('ops.scanner.title')" :subtitle="__('ops.scanner.subtitle')" icon="scan" dark sound status width="max-w-6xl">
        <div class="hidden w-52 sm:block">
            <x-ui.select wire:model.live="staffId" dark class="!h-10 !text-sm" :aria-label="__('ops.scanner.staff')">
                <option value="">👤 {{ __('ops.scanner.staff') }}</option>
                @foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->icon ?: '⭐' }} {{ $s->name }}</option>@endforeach
            </x-ui.select>
        </div>
    </x-staff.header>
</x-slot:header>

<div class="mx-auto max-w-6xl px-3 pb-24 pt-5 sm:px-5"
     x-data="qrScanner({ messages: @js($camMsgs) })"
     x-on:scanner-already.window="if ($event.detail.camera) setTimeout(() => $wire.closeAlert(), 4000)"
     x-on:scanner-done.window="if ($event.detail.camera) setTimeout(() => { if ($wire.phase === 'success') $wire.closeAlert() }, 2600)">

    <div class="mb-4 sm:hidden">
        <x-ui.select wire:model.live="staffId" dark :aria-label="__('ops.scanner.staff')">
            <option value="">👤 {{ __('ops.scanner.staff') }}</option>
            @foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->icon ?: '⭐' }} {{ $s->name }}</option>@endforeach
        </x-ui.select>
    </div>

    {{-- Sticky confirm bar (F-GATE-025) --}}
    @if($phase === 'review' && count($selected) > 0)
        <div class="sticky top-24 z-30 mb-4 flex items-center gap-3 rounded-[1.25rem] bg-emerald-600/95 p-3 ps-5 text-white shadow-float ring-1 ring-emerald-300/30 backdrop-blur-xl">
            <p class="min-w-0 flex-1 truncate text-sm font-bold">{{ $first['ticket_holder_name'] ?? $first['customer_name'] ?? '' }} — <span class="font-mono" dir="ltr">{{ $first['booking_reference'] ?? '' }}</span></p>
            <button type="button" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm" class="inline-flex h-12 items-center gap-2 rounded-full bg-white px-6 font-extrabold text-emerald-700 transition active:scale-[.97]">
                <x-ui.spinner wire:loading wire:target="confirm" size="18" />
                <span wire:loading.remove wire:target="confirm" class="contents"><x-glyph name="check" size="18" stroke="2.4" /></span>
                {{ count($selected) > 1 ? __('ops.scanner.confirm_n', ['n' => count($selected)]) : __('ops.scanner.confirm') }}
            </button>
        </div>
    @endif

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-5">
            {{-- Camera card (F-GATE-005…008) --}}
            <section class="overflow-hidden rounded-[1.75rem] bg-ops-surface ring-1 ring-white/10">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 pb-4 pt-5">
                    <div>
                        <h2 class="text-lg font-extrabold">{{ __('ops.scanner.card_title') }}</h2>
                        <p class="text-sm text-ops-muted">{{ __('ops.scanner.card_sub') }}</p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold ring-1"
                          :class="state === 'running' ? 'bg-emerald-500/15 text-emerald-300 ring-emerald-400/25' : 'bg-white/5 text-ops-muted ring-white/10'">
                        <span class="size-2 rounded-full" :class="state === 'running' ? 'animate-pulse bg-emerald-400' : 'bg-ops-muted'"></span>
                        <span x-text="state === 'running' ? @js(__('ops.scanner.camera_on')) : @js(__('ops.scanner.camera_off'))"></span>
                    </span>
                </div>

                <div class="relative mx-5 overflow-hidden rounded-[1.4rem] bg-black ring-1 ring-white/10" wire:ignore>
                    <div id="qr-reader" x-ref="reader" class="min-h-[300px] w-full sm:min-h-[400px] [&_video]:!h-full [&_video]:!w-full [&_video]:object-cover"></div>
                    {{-- Idle overlay / frame --}}
                    <div class="pointer-events-none absolute inset-0 grid place-items-center">
                        <div class="relative size-56 sm:size-64">
                            <span class="absolute start-0 top-0 size-10 rounded-ss-2xl border-s-2 border-t-2 border-gold-400"></span>
                            <span class="absolute end-0 top-0 size-10 rounded-se-2xl border-e-2 border-t-2 border-gold-400"></span>
                            <span class="absolute bottom-0 start-0 size-10 rounded-es-2xl border-b-2 border-s-2 border-gold-400"></span>
                            <span class="absolute bottom-0 end-0 size-10 rounded-ee-2xl border-b-2 border-e-2 border-gold-400"></span>
                            <span x-show="state === 'running'" class="absolute inset-x-4 top-4 h-0.5 animate-scanner-line rounded-full bg-gradient-to-r from-transparent via-gold-400 to-transparent shadow-[0_0_14px_rgb(249_177_31/0.8)]"></span>
                            <div x-show="state !== 'running'" class="absolute inset-0 grid place-items-center text-center text-ops-muted">
                                <div><x-glyph name="scan" size="44" stroke="1.1" class="mx-auto text-gold-400/80" /><p class="mt-3 text-sm" x-text="state === 'starting' ? @js(__('ops.scanner.starting_camera')) : @js(__('ops.scanner.camera_ready'))"></p></div>
                            </div>
                        </div>
                    </div>
                </div>

                <p x-show="error" x-cloak class="mx-5 mt-4 flex items-center gap-2 rounded-2xl bg-red-500/10 p-3 text-sm text-red-300 ring-1 ring-red-400/20"><x-glyph name="alert" size="16" /><span x-text="error"></span></p>

                <div class="flex flex-wrap items-center gap-2 p-5">
                    <button type="button" x-show="state !== 'running'" @click="start()" :disabled="state === 'starting'"
                        class="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-full bg-maroon-600 px-6 font-bold text-white shadow-elegant transition hover:bg-maroon-500 active:scale-[.98] disabled:opacity-60">
                        <x-glyph name="camera" size="19" /><span x-text="state === 'starting' ? @js(__('ops.scanner.starting_camera')) : @js(__('ops.scanner.start_camera'))"></span>
                    </button>
                    <button type="button" x-show="state === 'running'" x-cloak @click="stop()" class="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-full bg-white/10 px-6 font-bold ring-1 ring-white/15 transition hover:bg-white/15">
                        <x-glyph name="x" size="18" />{{ __('ops.scanner.stop_camera') }}
                    </button>
                    <button type="button" x-show="cameras.length > 1" x-cloak @click="switchCamera()" class="grid size-12 place-items-center rounded-full bg-white/10 ring-1 ring-white/15 hover:bg-white/15" title="{{ __('ops.scanner.switch_camera') }}"><x-glyph name="refresh" size="18" /></button>
                    <button type="button" x-show="torchSupported && state === 'running'" x-cloak @click="toggleTorch()" :class="torch ? 'bg-gold-400 text-ink' : 'bg-white/10'" class="grid size-12 place-items-center rounded-full ring-1 ring-white/15" title="{{ __('ops.scanner.torch') }}"><x-glyph name="sparkle" size="18" /></button>
                    <label class="ms-auto flex cursor-pointer items-center gap-2 text-sm text-ops-muted" title="{{ __('ops.scanner.continuous_hint') }}">
                        <input type="checkbox" wire:model.live="continuous" class="size-4 rounded border-white/20 bg-ops-bg text-gold-400 focus:ring-gold-400"> {{ __('ops.scanner.continuous') }}
                    </label>
                </div>

                {{-- Manual search / keyboard-wedge reader (F-GATE-014) --}}
                <div class="border-t border-white/5 p-5 pt-4">
                    <button type="button" wire:click="$toggle('showManual')" class="text-sm font-bold text-gold-300 hover:text-gold-200">{{ $showManual ? __('ops.scanner.hide_manual') : __('ops.scanner.manual') }}</button>
                    @if($showManual)
                        <form wire:submit="searchManual" class="mt-3 flex gap-2">
                            <input wire:model="manual" x-on:input="$el.value = $el.value.toUpperCase()" autofocus autocomplete="off" spellcheck="false" dir="ltr"
                                placeholder="{{ __('ops.scanner.manual_ph') }}"
                                class="h-12 flex-1 rounded-2xl border-0 bg-ops-bg px-4 text-center font-mono text-[16px] text-ops-fg ring-1 ring-white/10 placeholder:font-sans placeholder:text-ops-muted/60 focus:ring-2 focus:ring-gold-400/60">
                            <button type="submit" class="inline-flex h-12 items-center gap-2 rounded-2xl bg-gold-400 px-5 font-bold text-ink transition hover:bg-gold-300 active:scale-[.98]">
                                <x-ui.spinner wire:loading wire:target="searchManual" size="18" />
                                <span wire:loading.remove wire:target="searchManual" class="contents"><x-glyph name="search" size="18" /></span>
                                <span class="hidden sm:inline">{{ __('ops.scanner.search') }}</span>
                            </button>
                        </form>
                    @endif
                    <p wire:loading wire:target="process,searchManual" class="mt-3 flex items-center gap-2 text-sm text-ops-muted"><x-ui.spinner size="16" />{{ __('ops.scanner.processing') }}</p>
                </div>
            </section>

            {{-- Continuous-mode flash --}}
            @if($continuous && $flash)
                <div wire:key="flash-{{ $flash['at'] }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 2200)" x-show="show" x-transition.opacity.duration.300ms
                     @class(['flex items-center gap-4 rounded-[1.5rem] p-5 shadow-float ring-1', 'bg-emerald-600 text-white ring-emerald-300/30' => $flash['ok'], 'bg-red-600 text-white ring-red-300/30' => ! $flash['ok']])>
                    <x-glyph :name="$flash['ok'] ? 'check-circle' : 'x-circle'" size="40" stroke="1.6" />
                    <div class="min-w-0">
                        <p class="text-xl font-extrabold">{{ $flash['name'] ?? '' }} @if($flash['type_label'])<span class="text-sm font-bold opacity-80">· {{ $flash['type_label'] }}</span>@endif</p>
                        <p class="text-sm opacity-90">{{ $flash['message'] }}</p>
                        @if($flash['collect'])<p class="mt-1 text-sm font-extrabold text-gold-200">{{ __('ops.scanner.collect_cash') }}</p>@endif
                    </div>
                </div>
            @endif

            {{-- Review panel (F-GATE-019…024) --}}
            @if($phase === 'review' && $first)
                <section id="review" x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }))" class="scroll-mt-28 rounded-[1.75rem] bg-ops-surface p-5 ring-1 ring-emerald-400/30">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-2xl bg-emerald-500/15 text-emerald-300"><x-glyph name="check-circle" size="22" /></span>
                        <div><p class="font-extrabold text-emerald-300">{{ __('ops.scanner.verified') }}</p><p class="text-sm text-ops-muted">{{ $first['event_title'] }} · {{ $first['event_date_label'] }}</p></div>
                    </div>

                    @if(count($tickets) > 1)
                        <div class="mt-5 flex items-center justify-between">
                            <p class="text-sm font-bold">{{ __('ops.scanner.choose', ['n' => $openCount]) }}</p>
                            <button type="button" wire:click="selectAll" class="text-sm font-bold text-gold-300 hover:text-gold-200">{{ __('ops.scanner.select_all') }}</button>
                        </div>
                    @endif

                    <ul class="mt-4 space-y-2.5">
                        @foreach($tickets as $i => $t)
                            @php $sel = in_array($t['holder_id'], $selected, true); @endphp
                            <li wire:key="st-{{ $t['holder_id'] }}">
                                <button type="button" wire:click="toggle('{{ $t['holder_id'] }}')" @disabled($t['is_present'] || ! $t['admissible'])
                                    @class(['flex w-full items-center gap-3 rounded-2xl p-3 text-start ring-1 transition', 'bg-emerald-500/10 ring-2 ring-emerald-400/60' => $sel, 'bg-ops-elevated ring-white/10 hover:ring-white/25' => ! $sel, 'opacity-50' => $t['is_present'] || ! $t['admissible']])>
                                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br {{ $kindTone[$t['ticket_type']] ?? $kindTone['normal'] }}"><x-glyph :name="$kindIcon[$t['ticket_type']] ?? 'ticket'" size="22" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center gap-2 font-extrabold">{{ $t['ticket_holder_name'] }} <span class="text-xs font-bold text-ops-muted">{{ $i + 1 }}/{{ count($tickets) }}</span></span>
                                        <span class="block font-mono text-xs text-ops-muted" dir="ltr">{{ $t['ticket_code'] }}</span>
                                        <span class="block text-xs text-ops-muted">{{ $t['ticket_type_label'] }} · {{ $t['ticket_holder_phone'] }} · {{ \App\Support\Countries::flag($t['ticket_holder_nationality']) }} {{ \App\Support\Countries::label($t['ticket_holder_nationality']) }}</span>
                                        @if($pii && ($t['ticket_holder_id_number'] ?? null))<span class="block text-xs text-ops-muted">{{ __('ops.scanner.id_number') }}: <span class="font-mono" dir="ltr">{{ $t['ticket_holder_id_number'] }}</span></span>@endif
                                        @if(! $t['is_present'] && ! $t['admissible'])<span class="mt-1 block text-xs font-bold text-red-300">{{ $t['refusal_message'] }}</span>@endif
                                        @if($t['collect_cash'] && ! $t['is_present'])<span class="mt-1 block text-xs font-extrabold text-gold-300">{{ __('ops.scanner.collect_cash') }}</span>@endif
                                    </span>
                                    @if($t['is_present'])
                                        <x-ui.badge tone="dark-success" size="sm">✓ {{ __('ops.scanner.present') }}</x-ui.badge>
                                    @else
                                        <span @class(['grid size-7 place-items-center rounded-full ring-2', 'bg-emerald-500 ring-emerald-500 text-white' => $sel, 'ring-white/25' => ! $sel])>@if($sel)<x-glyph name="check" size="15" stroke="3" />@endif</span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <dl class="mt-5 grid grid-cols-2 gap-3 rounded-2xl bg-ops-bg/60 p-4 text-sm ring-1 ring-white/5 sm:grid-cols-3">
                        <div><dt class="text-xs text-ops-muted">{{ __('ops.scanner.ref') }}</dt><dd class="font-mono font-bold" dir="ltr">{{ $first['booking_reference'] }}</dd></div>
                        <div><dt class="text-xs text-ops-muted">{{ __('ops.scanner.customer') }}</dt><dd class="font-bold">{{ $first['customer_name'] }}</dd></div>
                        <div><dt class="text-xs text-ops-muted">{{ __('ops.scanner.payment') }}</dt><dd class="font-bold text-emerald-300">{{ __('ops.scanner.paid') }}</dd></div>
                    </dl>

                    @if($related['same_day'])
                        <div class="mt-5 rounded-2xl bg-emerald-500/[0.07] p-4 ring-1 ring-emerald-400/20">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-bold text-emerald-300">{{ __('ops.scanner.related_same', ['n' => count($related['same_day'])]) }}</p>
                                <button type="button" wire:click="addFamily" class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-bold text-emerald-200 hover:bg-emerald-500/30">{{ __('ops.scanner.family_checkin', ['n' => collect($related['same_day'])->where('admissible', true)->where('is_present', false)->count()]) }}</button>
                            </div>
                            <ul class="mt-3 space-y-1.5 text-sm">
                                @foreach($related['same_day'] as $r)
                                    <li wire:key="rs-{{ $r['holder_id'] }}">
                                        <button type="button" wire:click="toggle('{{ $r['holder_id'] }}')" @disabled($r['is_present'] || ! $r['admissible']) class="flex w-full items-center justify-between gap-2 rounded-xl px-3 py-2 text-start hover:bg-white/5 disabled:opacity-50">
                                            <span>{{ $r['ticket_holder_name'] }} · <span class="text-ops-muted">{{ $r['ticket_type_label'] }} · <span class="font-mono" dir="ltr">{{ $r['booking_reference'] }}</span></span></span>
                                            <span class="text-xs">{{ $r['is_present'] ? '✅' : (in_array($r['holder_id'], $selected, true) ? '☑️' : '⭕') }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if($related['other_days'])
                        <div class="mt-4 rounded-2xl bg-amber-400/[0.06] p-4 ring-1 ring-amber-300/20" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="flex w-full items-center justify-between text-sm font-bold text-amber-200">
                                {{ __('ops.scanner.related_other', ['n' => collect($related['other_days'])->flatten(1)->count()]) }}<x-glyph name="chevron-down" size="16" />
                            </button>
                            <div x-show="open" x-cloak class="mt-3 space-y-3 text-sm">
                                <p class="text-xs text-amber-200/80">{{ __('ops.scanner.related_other_note') }}</p>
                                @foreach($related['other_days'] as $day => $rows)
                                    <div>
                                        <p class="mb-1 text-xs font-bold text-ops-muted">📅 {{ $day === 'unknown' ? '—' : \App\Support\Formats::longDate($day) }} · {{ count($rows) }}</p>
                                        @foreach($rows as $r)<p class="text-ops-fg/80">{{ $r['ticket_holder_name'] }} · {{ $r['ticket_type_label'] }} · {{ $r['event_title'] }}</p>@endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-5 flex flex-wrap gap-2">
                        <button type="button" wire:click="confirm" @disabled(count($selected) === 0) class="inline-flex h-14 flex-1 items-center justify-center gap-2 rounded-full bg-emerald-600 text-lg font-extrabold text-white shadow-[0_16px_40px_-16px_rgb(16_185_129/0.9)] transition hover:bg-emerald-500 active:scale-[.98] disabled:opacity-40">
                            <x-ui.spinner wire:loading wire:target="confirm" size="20" />
                            {{ count($selected) > 1 ? __('ops.scanner.confirm_n', ['n' => count($selected)]) : __('ops.scanner.confirm') }}
                        </button>
                        <button type="button" wire:click="newScan" class="inline-flex h-14 items-center gap-2 rounded-full px-5 font-bold ring-1 ring-white/15 hover:bg-white/5"><x-glyph name="scan" size="18" />{{ __('ops.scanner.new_scan') }}</button>
                    </div>
                </section>
            @endif
        </div>

        {{-- History (F-GATE-035/036) --}}
        <aside class="rounded-[1.75rem] bg-ops-surface p-5 ring-1 ring-white/10 lg:sticky lg:top-24" wire:poll.20s.visible x-data="{ undo: null }">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="font-extrabold">{{ __('ops.scanner.history') }}</h2>
                    <p class="text-xs text-ops-muted">{{ __('ops.scanner.history_count', ['n' => $history->count()]) }}</p>
                </div>
                <div class="flex items-center gap-1 rounded-full bg-ops-bg p-1 text-xs font-bold ring-1 ring-white/10">
                    <button type="button" wire:click="$set('historyAll', false)" @class(['rounded-full px-3 py-1.5', 'bg-white/10' => ! $historyAll, 'text-ops-muted' => $historyAll])>{{ __('ops.scanner.history_today') }}</button>
                    <button type="button" wire:click="$set('historyAll', true)" @class(['rounded-full px-3 py-1.5', 'bg-white/10' => $historyAll, 'text-ops-muted' => ! $historyAll])>{{ __('ops.scanner.history_all') }}</button>
                </div>
            </div>
            <ol class="mt-4 max-h-[34rem] space-y-2 overflow-y-auto pe-1">
                @forelse($history as $n => $h)
                    <li wire:key="hi-{{ $h->id }}" class="flex items-center gap-3 rounded-2xl bg-ops-elevated/60 p-3 ring-1 ring-white/5">
                        <span @class(['grid size-9 shrink-0 place-items-center rounded-xl text-xs font-extrabold', 'bg-gold-400 text-ink' => $h->ticket_type->value === 'vip', 'bg-maroon-600 text-white' => $h->ticket_type->value !== 'vip'])>{{ $n + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-bold">{{ $h->name }}</p>
                            <p class="truncate text-xs text-ops-muted"><span class="font-mono" dir="ltr">{{ $h->order?->booking_reference }}</span> · {{ $h->ticket_type->label() }} · {{ $h->order?->event?->title }}</p>
                            <p class="text-xs text-ops-muted">{{ \App\Support\Formats::dateTime($h->checked_in_at) }} @if($h->checked_in_by_name)<span class="text-gold-300">· {{ __('ops.scanner.by', ['name' => $h->checked_in_by_name]) }}</span>@endif</p>
                        </div>
                        @if($canUndo)
                            <button type="button" @click="undo = { id: '{{ $h->id }}', name: @js($h->name), reason: '' }"
                                class="grid size-9 place-items-center rounded-full text-red-300 hover:bg-red-500/15" title="{{ __('ops.scanner.undo') }}"><x-glyph name="refresh" size="16" /></button>
                        @endif
                    </li>
                @empty
                    <li><x-ui.empty dark icon="clock" :title="__('ops.scanner.history_empty')" class="!py-10" /></li>
                @endforelse
            </ol>

            {{-- Admin undo with mandatory reason (F-GATE-036 rebuild) --}}
            <template x-teleport="body">
                <div x-show="undo" x-cloak class="fixed inset-0 z-[56] grid place-items-center bg-black/70 p-4 backdrop-blur-sm" @keydown.escape.window="undo = null">
                    <div class="dark w-full max-w-md rounded-[2rem] bg-ops-surface p-7 text-ops-fg ring-1 ring-white/10" @click.outside="undo = null">
                        <h3 class="text-xl font-extrabold">{{ __('ops.scanner.undo') }}</h3>
                        <p class="mt-2 text-sm text-ops-muted" x-text="@js(__('ops.scanner.undo_title', ['name' => '__N__'])).replace('__N__', undo?.name || '')"></p>
                        <p class="mt-1 text-sm text-ops-muted">{{ __('ops.scanner.undo_body') }}</p>
                        <textarea :value="undo?.reason ?? ''" rows="3" maxlength="300" placeholder="{{ __('ops.scanner.undo_reason') }}"
                            class="mt-4 w-full rounded-2xl border-0 bg-ops-bg p-3 text-[16px] text-ops-fg ring-1 ring-white/10 focus:ring-2 focus:ring-gold-400/60" x-on:input="undo.reason = $event.target.value"></textarea>
                        <div class="mt-5 flex gap-2">
                            <button type="button" @click="undo = null" class="h-12 flex-1 rounded-full font-bold ring-1 ring-white/15 hover:bg-white/5">{{ __('common.cancel') }}</button>
                            <button type="button" :disabled="!undo || !undo.reason || undo.reason.trim().length < 3" @click="$wire.undo(undo.id, undo.reason); undo = null"
                                class="h-12 flex-1 rounded-full bg-red-600 font-bold text-white hover:bg-red-500 disabled:opacity-40">{{ __('ops.scanner.undo') }}</button>
                        </div>
                    </div>
                </div>
            </template>
        </aside>
    </div>

    {{-- Already scanned (F-GATE-029) --}}
    @if($phase === 'already' && $alert)
        @php $t = $alert['ticket']; @endphp
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4 backdrop-blur-sm" role="alertdialog">
            <div class="w-full max-w-md animate-rise rounded-[2rem] bg-ops-surface p-7 text-center ring-1 ring-amber-300/30">
                <span class="mx-auto grid size-20 animate-pulse place-items-center rounded-full bg-amber-400/15 text-amber-300"><x-glyph name="alert" size="40" /></span>
                <h2 class="mt-5 text-2xl font-extrabold text-amber-200">{{ __('ops.scanner.already_title') }}</h2>
                <p class="mt-3 text-lg font-bold">{{ $t['ticket_holder_name'] }}</p>
                <p class="font-mono text-sm text-ops-muted" dir="ltr">{{ $t['ticket_code'] }}</p>
                @if($t['confirmed_at_label'])<p class="mt-3 text-sm">{{ __('ops.scanner.scan_time', ['time' => $t['confirmed_at_label']]) }}</p>@endif
                @if($t['confirmed_by_name'])<p class="text-sm text-gold-300">{{ __('ops.scanner.by', ['name' => $t['confirmed_by_name']]) }}</p>@endif
                @if($fromCamera)<p class="mt-4 text-xs text-ops-muted">{{ __('ops.scanner.camera_soon') }}</p>@endif
                <button type="button" wire:click="closeAlert" class="mt-6 h-12 w-full rounded-full bg-white/10 font-bold ring-1 ring-white/15 hover:bg-white/15">{{ __('ops.scanner.new_scan') }}</button>
            </div>
        </div>
    @endif

    {{-- Error (F-GATE-030) --}}
    @if($phase === 'error' && $alert)
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4 backdrop-blur-sm" role="alertdialog">
            <div class="w-full max-w-md animate-rise rounded-[2rem] bg-ops-surface p-7 text-center ring-1 ring-red-400/30">
                <span class="mx-auto grid size-20 place-items-center rounded-full bg-red-500/15 text-red-300"><x-glyph name="x-circle" size="40" /></span>
                <h2 class="mt-5 text-2xl font-extrabold text-red-200">{{ __('ops.scanner.error_title') }}</h2>
                <p class="mt-3 whitespace-pre-line leading-relaxed">{{ $alert['message'] }}</p>
                @if($alert['ticket'] ?? null)<p class="mt-2 text-sm text-ops-muted">{{ $alert['ticket']['ticket_holder_name'] }} · <span class="font-mono" dir="ltr">{{ $alert['ticket']['ticket_code'] }}</span></p>@endif
                <button type="button" wire:click="closeAlert" class="mt-6 h-12 w-full rounded-full bg-red-600 font-bold text-white hover:bg-red-500">{{ __('ops.scanner.close') }}</button>
            </div>
        </div>
    @endif

    {{-- Success (F-GATE-028) --}}
    @if($phase === 'success' && $done)
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4 backdrop-blur-sm" role="alertdialog">
            <div class="w-full max-w-md animate-rise rounded-[2rem] bg-ops-surface p-7 text-center ring-1 ring-emerald-400/30">
                <div class="relative mx-auto size-20">
                    <span class="absolute inset-0 animate-ping rounded-full bg-emerald-400/30"></span>
                    <span class="relative grid size-20 place-items-center rounded-full bg-emerald-500 text-white"><x-glyph name="check" size="38" stroke="2.4" /></span>
                </div>
                <h2 class="mt-5 text-2xl font-extrabold text-emerald-200">{{ __('ops.scanner.success_title') }}</h2>
                <p class="text-ops-muted">{{ __('ops.scanner.success_sub') }}</p>
                <p class="mt-4 text-5xl font-extrabold tabular-nums">{{ $done['count'] }}</p>
                <div class="mt-4 space-y-1 text-sm">
                    @foreach($done['types'] as $label => $qty)<p>{{ $qty }}x {{ $label }}</p>@endforeach
                </div>
                <div class="mt-3 space-y-0.5 text-sm text-ops-muted">
                    @foreach($done['holders'] as $k => $h)<p>{{ $k === 0 ? '👤 ' : '' }}{{ $h['name'] }} ({{ $h['type'] }})</p>@endforeach
                </div>
                @if($done['collect'])<p class="mt-4 rounded-2xl bg-gold-400/15 p-3 font-extrabold text-gold-200 ring-1 ring-gold-300/30">{{ __('ops.scanner.collect_cash') }}</p>@endif
                @foreach($done['failures'] as $f)<p class="mt-2 text-sm text-red-300">{{ $f }}</p>@endforeach
                <button type="button" wire:click="closeAlert" class="mt-6 h-12 w-full rounded-full bg-emerald-600 font-bold text-white hover:bg-emerald-500">{{ __('ops.pos.ok') }}</button>
            </div>
        </div>
    @endif
</div>
