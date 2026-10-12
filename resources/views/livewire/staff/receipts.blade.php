<x-slot:header>
    <x-staff.header :title="__('ops.receipts.title')" :subtitle="__('staff.link_receipts_hint')" icon="receipt" status width="max-w-6xl" />
</x-slot:header>

<div wire:poll.15s.visible class="mx-auto max-w-6xl px-3 pb-16 pt-6 sm:px-5">
    {{-- Capture (F-RCP-003/004) --}}
    <x-ui.card pad="p-5 sm:p-6">
        <h2 class="flex items-center gap-2 text-lg font-extrabold"><x-glyph name="camera" size="20" class="text-terracotta" />{{ __('ops.receipts.capture') }}</h2>
        <div class="mt-5 grid gap-3 sm:grid-cols-2" x-data="{ busy: false }" x-on:livewire-upload-start="busy = true" x-on:livewire-upload-error="busy = false">
            <label class="group relative flex h-28 cursor-pointer flex-col items-center justify-center gap-2 overflow-hidden rounded-[1.5rem] bg-maroon-600 text-white shadow-elegant transition hover:bg-maroon-700 active:scale-[.99]">
                <x-glyph name="camera" size="38" stroke="1.3" />
                <span class="font-bold">{{ __('ops.receipts.camera') }}</span>
                <input type="file" accept="image/*" capture="environment" wire:model="photo" class="sr-only">
            </label>
            <label class="group relative flex h-28 cursor-pointer flex-col items-center justify-center gap-2 rounded-[1.5rem] bg-ivory text-ink ring-1 ring-bark/15 transition hover:bg-white active:scale-[.99]">
                <x-glyph name="image" size="38" stroke="1.3" class="text-terracotta" />
                <span class="font-bold">{{ __('ops.receipts.gallery') }}</span>
                <input type="file" accept="image/*" wire:model="photo" class="sr-only">
            </label>
        </div>
        <div wire:loading.flex wire:target="photo" class="mt-4 items-center justify-center gap-3 rounded-2xl bg-gold-50 p-4 font-bold text-gold-800 ring-1 ring-gold-200">
            <x-ui.spinner size="20" />{{ __('ops.receipts.reading') }}
        </div>
        @error('photo')<p class="mt-3 text-sm font-medium text-danger">{{ $message }}</p>@enderror
        @unless($canManage)<p class="mt-4 text-xs text-ink-soft">{{ __('ops.receipts.can_capture_only') }}</p>@endunless
    </x-ui.card>

    {{-- Filters + list (F-RCP-013…017) --}}
    <x-ui.card class="mt-5" pad="p-5 sm:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-lg font-extrabold">{{ __('ops.receipts.list', ['n' => $rows->count()]) }}</h2>
            <div class="flex flex-wrap items-end gap-2">
                <label class="text-xs font-bold text-ink-soft">{{ __('ops.receipts.from') }}<input type="date" wire:model.live="from" dir="ltr" class="mt-1 block h-10 rounded-xl border-0 bg-ivory px-3 text-sm ring-1 ring-bark/15"></label>
                <label class="text-xs font-bold text-ink-soft">{{ __('ops.receipts.to') }}<input type="date" wire:model.live="to" dir="ltr" class="mt-1 block h-10 rounded-xl border-0 bg-ivory px-3 text-sm ring-1 ring-bark/15"></label>
                <div class="w-48">
                    <x-ui.select wire:model.live="eventId" class="!h-10 !text-sm">
                        <option value="">{{ __('common.all') }}</option>
                        @foreach($events as $e)<option value="{{ $e->id }}">{{ \App\Support\Formats::dayMonth($e->event_date) }} — {{ $e->title }}</option>@endforeach
                    </x-ui.select>
                </div>
                @if($canManage)
                    <x-ui.button variant="secondary" icon="download" wire:click="export" loading="export" :disabled="$rows->isEmpty()">{{ __('ops.receipts.export') }}</x-ui.button>
                @endif
            </div>
        </div>

        @if($rows->isEmpty())
            <x-ui.empty icon="receipt" :title="__('ops.receipts.empty')" :hint="__('ops.receipts.empty_hint')" />
        @else
            <div class="mt-5 overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="text-xs text-ink-soft">
                        <tr class="border-b border-bark/10">
                            <th class="px-2 py-2 text-start">#</th>
                            <th class="px-2 py-2 text-start">{{ __('ops.receipts.amount') }}</th>
                            <th class="px-2 py-2 text-start">{{ __('ops.receipts.seq') }}</th>
                            <th class="px-2 py-2">{{ __('ops.receipts.normal') }}</th>
                            <th class="px-2 py-2">{{ __('ops.receipts.vip') }}</th>
                            <th class="px-2 py-2">{{ __('ops.receipts.parking') }}</th>
                            <th class="px-2 py-2 text-start">{{ __('ops.receipts.card') }}</th>
                            <th class="px-2 py-2 text-start">{{ __('ops.receipts.time') }}</th>
                            <th class="px-2 py-2 text-start">{{ __('ops.receipts.auth') }}</th>
                            <th class="px-2 py-2 text-start">{{ __('ops.receipts.date') }}</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-bark/10">
                        @foreach($rows as $i => $r)
                            @php
                                $exp = \App\Support\Money::of($prices['normal'])->multiply($r->normal_tickets)->add(\App\Support\Money::of($prices['vip'])->multiply($r->vip_tickets))->add(\App\Support\Money::of($prices['parking'])->multiply($r->parking_tickets));
                                $mismatch = ($r->normal_tickets + $r->vip_tickets + $r->parking_tickets) > 0 && $r->amount_qar !== null && ! \App\Support\Money::amountsMatch($exp->toDecimal(), $r->amount_qar);
                            @endphp
                            <tr wire:key="rc-{{ $r->id }}" @class(['bg-danger/[0.04]' => $mismatch])>
                                <td class="px-2 py-2 text-ink-soft">{{ $rows->count() - $i }}</td>
                                <td class="px-2 py-2 font-extrabold tabular-nums">{{ $r->amount_qar !== null ? \App\Support\Formats::amount($r->amount_qar) : '—' }} @if($mismatch)<span class="text-danger" title="{{ __('ops.receipts.mismatch') }}">⚠</span>@endif</td>
                                <td class="px-2 py-2 font-mono" dir="ltr">{{ $r->seq_number ?? '—' }}</td>
                                @foreach(['normal_tickets', 'vip_tickets', 'parking_tickets'] as $f)
                                    <td class="px-2 py-2 text-center">
                                        @if($canManage)
                                            <input type="number" min="0" max="999" value="{{ $r->{$f} }}" wire:change="updateCount('{{ $r->id }}', '{{ $f }}', $event.target.value)"
                                                class="h-9 w-16 rounded-lg border-0 bg-ivory text-center text-sm font-bold ring-1 ring-bark/15 focus:ring-2 focus:ring-maroon-500/60">
                                        @else
                                            <span class="font-bold">{{ $r->{$f} }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-2 py-2 font-mono text-xs" dir="ltr">{{ $r->card_number_masked ?? '—' }}</td>
                                <td class="px-2 py-2 font-mono text-xs" dir="ltr">{{ $r->time ?? '—' }}</td>
                                <td class="px-2 py-2 font-mono text-xs" dir="ltr">{{ $r->auth_number ?? '—' }}</td>
                                <td class="px-2 py-2 text-xs text-ink-soft">{{ \App\Support\Formats::dateTime($r->created_at) }}</td>
                                <td class="px-2 py-2">
                                    <div class="flex items-center justify-end gap-1">
                                        @if($r->image_path)
                                            <a href="{{ $r->temporaryImageUrl() }}" target="_blank" rel="noopener" class="grid size-8 place-items-center rounded-full text-ink-soft hover:bg-bark/10" title="{{ __('ops.receipts.view') }}"><x-glyph name="eye" size="16" /></a>
                                        @endif
                                        @if($canManage)
                                            <button type="button" x-data @click="$dispatch('open-dialog', 'rc-del-{{ $r->id }}')" class="grid size-8 place-items-center rounded-full text-danger hover:bg-danger/10" title="{{ __('ops.receipts.delete') }}"><x-glyph name="trash" size="16" /></button>
                                            <x-ui.dialog :name="'rc-del-'.$r->id" max-width="sm:max-w-sm">
                                                <div class="text-center">
                                                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-danger/10 text-danger"><x-glyph name="trash" size="24" /></span>
                                                    <p class="mt-4 font-bold">{{ __('ops.receipts.delete_confirm') }}</p>
                                                    <p class="mt-1 font-mono text-sm text-ink-soft" dir="ltr">{{ $r->seq_number }} · {{ $r->amount_qar }}</p>
                                                    <div class="mt-6 flex gap-3">
                                                        <x-ui.button variant="secondary" block x-on:click="$dispatch('close-dialog')">{{ __('common.cancel') }}</x-ui.button>
                                                        <x-ui.button variant="danger" block wire:click="delete('{{ $r->id }}')">{{ __('ops.receipts.delete') }}</x-ui.button>
                                                    </div>
                                                </div>
                                            </x-ui.dialog>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    {{-- Summary (F-RCP-018 rebuild: prices from settings + OCR total + difference) --}}
    @if($rows->isNotEmpty())
        <x-ui.card class="mx-auto mt-5 max-w-xl" pad="p-5 sm:p-6">
            <h3 class="text-center font-extrabold">{{ __('ops.receipts.summary') }} · POS</h3>
            <table class="mt-4 w-full text-sm">
                <thead class="text-xs text-ink-soft"><tr><th class="py-2 text-start">{{ __('ops.receipts.type') }}</th><th class="py-2">{{ __('ops.receipts.tickets') }}</th><th class="py-2 text-end">{{ __('ops.receipts.amount') }}</th></tr></thead>
                <tbody class="divide-y divide-bark/10">
                    @foreach(['normal' => 'text-emerald-700', 'vip' => 'text-gold-700', 'parking' => 'text-sky-700'] as $t => $c)
                        <tr><td class="py-2 font-bold {{ $c }}">{{ __('ops.receipts.'.$t) }} <span class="text-xs font-medium text-ink-soft">× {{ (int) $prices[$t] }}</span></td><td class="py-2 text-center tabular-nums">{{ $sum[$t] }}</td><td class="py-2 text-end tabular-nums">{{ $amounts[$t]->format() }}</td></tr>
                    @endforeach
                    <tr class="bg-maroon-50/60 font-extrabold"><td class="py-2 ps-2">{{ __('ops.receipts.total') }}</td><td class="py-2 text-center tabular-nums">{{ array_sum($sum) }}</td><td class="py-2 pe-2 text-end tabular-nums">{{ $expected->format() }}</td></tr>
                    <tr><td class="py-2 text-ink-soft" colspan="2">{{ __('ops.receipts.ocr_total') }}</td><td class="py-2 text-end tabular-nums">{{ $ocr->format() }}</td></tr>
                    <tr><td class="py-2 text-ink-soft" colspan="2">{{ __('ops.receipts.difference') }}</td><td @class(['py-2 text-end font-extrabold tabular-nums', 'text-success' => $ocr->equals($expected), 'text-danger' => ! $ocr->equals($expected)])>{{ $ocr->subtract($expected)->format() }}</td></tr>
                </tbody>
            </table>
        </x-ui.card>
    @endif
</div>
