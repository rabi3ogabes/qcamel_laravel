@unless($embedded)
<x-slot:header>
    <x-staff.header :title="__('staff.link_attendance')" :subtitle="__('attendance.subtitle')" icon="user-check" status width="max-w-6xl" />
</x-slot:header>
@endunless

<div wire:poll.30s.visible class="{{ $embedded ? '' : 'mx-auto max-w-6xl px-3 pb-16 pt-6 sm:px-5' }}">
    {{-- Header card (F-STF-027) + date navigation (F-STF-021) --}}
    <x-ui.card pad="p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <x-ui.eyebrow>{{ __('attendance.eyebrow') }}</x-ui.eyebrow>
                <h2 class="mt-3 text-2xl font-extrabold text-bark">{{ __('attendance.title') }}</h2>
                <p class="text-sm text-ink-soft">{{ __('attendance.subtitle') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="shift(-1)" class="grid size-11 place-items-center rounded-full bg-white ring-1 ring-bark/15 hover:bg-ivory" title="{{ __('attendance.prev_day') }}"><x-glyph name="chevron" size="18" class="ltr:-scale-x-100" /></button>
                <div class="text-center">
                    <p class="text-sm font-extrabold">{{ $label }}</p>
                    <input type="date" wire:model.live="date" max="{{ \App\Support\QatarClock::today() }}" dir="ltr" class="mt-1 h-9 rounded-xl border-0 bg-ivory px-3 text-sm ring-1 ring-bark/15">
                </div>
                <button type="button" wire:click="shift(1)" @disabled($isToday) class="grid size-11 place-items-center rounded-full bg-white ring-1 ring-bark/15 hover:bg-ivory disabled:opacity-40" title="{{ __('attendance.next_day') }}"><x-glyph name="chevron" size="18" class="rtl:-scale-x-100" /></button>
            </div>
        </div>

        {{-- Stats (F-STF-022) --}}
        <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach([['team_total', $stats['total'], 'text-ink'], ['present', $stats['present'], 'text-success'], ['absent', $stats['absent'], 'text-danger'], ['unmarked', $stats['unmarked'], 'text-ink-soft']] as [$k, $v, $c])
                <div class="rounded-2xl bg-ivory p-4 ring-1 ring-bark/10"><p class="text-xs font-bold text-ink-soft">{{ __('attendance.'.$k) }}</p><p class="mt-1 text-3xl font-extrabold tabular-nums {{ $c }}">{{ $v }}</p></div>
            @endforeach
        </div>

        @if(! $canEdit)
            <p class="mt-5 flex items-start gap-2 rounded-2xl bg-gold-50 p-4 text-sm font-bold text-gold-800 ring-1 ring-gold-200"><x-glyph name="info" size="18" />{{ __('attendance.read_only') }}</p>
        @else
            <div class="mt-5 flex flex-wrap gap-2">
                <x-ui.button variant="success" icon="check" wire:click="markAll" loading="markAll" :disabled="$users->isEmpty()">{{ __('attendance.mark_all') }}</x-ui.button>
                <x-ui.button variant="secondary" icon="refresh" x-data x-on:click="$dispatch('open-dialog', 'att-clear')">{{ __('attendance.clear_day') }}</x-ui.button>
            </div>
        @endif
    </x-ui.card>

    {{-- Register (F-STF-020) --}}
    @if($users->isEmpty())
        <x-ui.card class="mt-5"><x-ui.empty icon="users" :title="__('attendance.empty')" /></x-ui.card>
    @else
        <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach($users as $u)
                @php $rec = $byKey[$u->id.'|'.$date] ?? null; $st = $rec?->status?->value; @endphp
                <div wire:key="au-{{ $u->id }}" @class(['flex items-center gap-3 rounded-[1.5rem] p-4 ring-1 transition', 'bg-success/[0.07] ring-success/25' => $st === 'present', 'bg-danger/[0.06] ring-danger/20' => $st === 'absent', 'bg-white ring-bark/10 shadow-soft' => ! $st])>
                    <span class="grid size-12 place-items-center rounded-2xl bg-ivory text-2xl ring-1 ring-bark/10">{{ $u->icon ?: '👤' }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-extrabold">{{ $u->name }}</p>
                        <p @class(['text-sm font-bold', 'text-success' => $st === 'present', 'text-danger' => $st === 'absent', 'text-ink-soft' => ! $st])>{{ $st ? __('attendance.'.$st) : __('attendance.not_recorded') }}</p>
                        @if($rec?->marker)<p class="truncate text-xs text-ink-soft">{{ __('attendance.marked_by', ['name' => $rec->marker->name, 'time' => \App\Support\Formats::timeOf($rec->marked_at)]) }}</p>@endif
                    </div>
                    <div class="flex gap-1.5">
                        <button type="button" wire:click="setStatus('{{ $u->id }}', {{ $st === 'present' ? 'null' : "'present'" }})" @disabled(! $canEdit) aria-label="{{ __('attendance.mark_present', ['name' => $u->name]) }}"
                            @class(['grid size-10 place-items-center rounded-full ring-1 transition active:scale-90 disabled:opacity-40', 'bg-success text-white ring-success' => $st === 'present', 'bg-white text-success ring-success/30 hover:bg-success/10' => $st !== 'present'])><x-glyph name="check" size="18" stroke="2.2" /></button>
                        <button type="button" wire:click="setStatus('{{ $u->id }}', {{ $st === 'absent' ? 'null' : "'absent'" }})" @disabled(! $canEdit) aria-label="{{ __('attendance.mark_absent', ['name' => $u->name]) }}"
                            @class(['grid size-10 place-items-center rounded-full ring-1 transition active:scale-90 disabled:opacity-40', 'bg-danger text-white ring-danger' => $st === 'absent', 'bg-white text-danger ring-danger/30 hover:bg-danger/10' => $st !== 'absent'])><x-glyph name="x" size="18" stroke="2.2" /></button>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- 7-day summary (F-STF-026) --}}
        <x-ui.card class="mt-5" pad="p-5 sm:p-6">
            <h3 class="font-extrabold">{{ __('attendance.week') }}</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[520px] text-sm">
                    <thead>
                        <tr class="text-ink-soft">
                            <th class="py-2 text-start font-bold">{{ __('attendance.name') }}</th>
                            @foreach($days as $d)<th class="py-2 text-center font-bold">{{ \App\Support\Formats::weekday($d.' 12:00:00') }}<br><span class="text-xs font-medium">{{ (int) substr($d, 8) }}</span></th>@endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-bark/10">
                        @foreach($users as $u)
                            <tr>
                                <td class="py-2.5 font-bold">{{ $u->icon ?: '👤' }} {{ $u->name }}</td>
                                @foreach($days as $d)
                                    @php $s = ($byKey[$u->id.'|'.$d] ?? null)?->status?->value; @endphp
                                    <td class="py-2.5 text-center"><span @class(['inline-grid size-6 place-items-center rounded-full text-xs font-bold', 'bg-success/15 text-success' => $s === 'present', 'bg-danger/15 text-danger' => $s === 'absent', 'bg-bark/10 text-ink-soft' => ! $s])>{{ $s === 'present' ? '✓' : ($s === 'absent' ? '✗' : '–') }}</span></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif

    <x-ui.dialog name="att-clear" max-width="sm:max-w-sm">
        <div class="text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-danger/10 text-danger"><x-glyph name="refresh" size="24" /></span>
            <p class="mt-4 font-bold">{{ __('attendance.clear_confirm') }}</p>
            <div class="mt-6 flex gap-3">
                <x-ui.button variant="secondary" block x-on:click="$dispatch('close-dialog')">{{ __('common.cancel') }}</x-ui.button>
                <x-ui.button variant="danger" block wire:click="clearDay" loading="clearDay">{{ __('attendance.clear_day') }}</x-ui.button>
            </div>
        </div>
    </x-ui.dialog>
</div>
