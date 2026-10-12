@php
    $kindIcon = ['vip' => 'crown', 'normal' => 'ticket', 'parking' => 'car'];
    $kindTone = [
        'vip' => ['bar' => 'bg-ticket-vip', 'tile' => 'from-gold-300 to-gold-500 text-ink', 'dot' => 'bg-ticket-vip', 'text' => 'text-gold-700'],
        'normal' => ['bar' => 'bg-emerald-500', 'tile' => 'from-emerald-500 to-emerald-700 text-white', 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-700'],
        'parking' => ['bar' => 'bg-ticket-parking', 'tile' => 'from-sky-500 to-sky-700 text-white', 'dot' => 'bg-ticket-parking', 'text' => 'text-sky-700'],
    ];
    $isEn = app()->getLocale() === 'en';
    $natName = fn ($c) => $isEn ? $c['en'] : $c['ar'];
    $allNats = collect($gulf)->concat($more);
    $knownNat = $allNats->contains('ar', $buyer['nationality']);
@endphp
<x-slot:header>
    <x-staff.header :title="__('ops.pos.title')" :subtitle="__('ops.pos.sell')" icon="cash" sound fullscreen status>
        <div class="hidden items-center gap-2 lg:flex">
            <div class="w-44">
                <x-ui.select wire:model.live="posUserId" class="!h-10 !text-sm" :aria-label="__('ops.shell.cashier')">
                    <option value="">👤 {{ __('ops.shell.choose_cashier') }}</option>
                    @foreach($cashiers as $c)<option value="{{ $c->id }}">{{ $c->icon ?: '⭐' }} {{ $c->name }}</option>@endforeach
                </x-ui.select>
            </div>
            <div class="w-56">
                <x-ui.select wire:model.live="eventId" class="!h-10 !text-sm" :aria-label="__('ops.pos.choose_event')">
                    @forelse($events as $e)
                        <option value="{{ $e->id }}">{{ \App\Support\Formats::dayMonth($e->event_date) }} — {{ $e->title }}</option>
                    @empty
                        <option value="">{{ __('ops.pos.no_events') }}</option>
                    @endforelse
                </x-ui.select>
            </div>
        </div>
        <button type="button" x-data @click="$dispatch('open-dialog', 'pos-checkin')"
            class="inline-flex h-10 items-center gap-2 rounded-full bg-gradient-to-l from-emerald-600 to-emerald-700 px-4 text-sm font-bold text-white shadow-soft transition hover:brightness-110 active:scale-[.98]">
            <x-glyph name="user-check" size="17" /><span class="hidden sm:inline">{{ __('ops.pos.checkin') }}</span>
        </button>
    </x-staff.header>
</x-slot:header>

<div wire:poll.10s.keep-alive="refreshStock" class="mx-auto max-w-7xl px-3 pb-20 pt-6 sm:px-5"
     x-data="{ banner: null, timer: null }"
     x-on:capacity-increased.window="banner = $event.detail; window.qc.sfx.capacity($event.detail.type); $store.toasts.push({ type: 'success', title: '🎉 ' + @js(__('ops.pos.new_capacity_title')), message: $event.detail.message, duration: 6000 }); clearTimeout(timer); timer = setTimeout(() => banner = null, 6000)">

    {{-- Offline guard (POS upgrades) --}}
    <div x-data="{ on: navigator.onLine }" x-init="addEventListener('online', () => on = true); addEventListener('offline', () => on = false)" x-show="!on" x-cloak
         class="mb-4 flex items-center gap-2 rounded-2xl bg-danger px-4 py-3 text-sm font-bold text-white shadow-soft"><x-glyph name="wifi-off" size="18" />{{ __('ops.pos.offline_banner') }}</div>

    {{-- Mobile selectors --}}
    <div class="mb-4 grid gap-2 sm:grid-cols-2 lg:hidden">
        <x-ui.select wire:model.live="posUserId" :aria-label="__('ops.shell.cashier')">
            <option value="">👤 {{ __('ops.shell.choose_cashier') }}</option>
            @foreach($cashiers as $c)<option value="{{ $c->id }}">{{ $c->icon ?: '⭐' }} {{ $c->name }}</option>@endforeach
        </x-ui.select>
        <x-ui.select wire:model.live="eventId" :aria-label="__('ops.pos.choose_event')">
            @foreach($events as $e)<option value="{{ $e->id }}">{{ \App\Support\Formats::dayMonth($e->event_date) }} — {{ $e->title }}</option>@endforeach
        </x-ui.select>
    </div>

    {{-- Capacity increase banner (F-POS-039) --}}
    <template x-if="banner">
        <div class="fixed inset-x-4 top-24 z-50 mx-auto max-w-lg overflow-hidden rounded-[1.5rem] p-5 text-white shadow-float"
             :class="{ 'bg-gradient-to-l from-gold-400 to-orange-500 !text-ink': banner.type === 'vip', 'bg-gradient-to-l from-emerald-500 to-green-600': banner.type === 'normal', 'bg-gradient-to-l from-sky-500 to-cyan-600': banner.type === 'parking' }">
            <div class="flex items-start gap-3">
                <span class="animate-bounce"><x-glyph name="sparkle" size="26" /></span>
                <div class="flex-1"><p class="font-extrabold">{{ __('ops.pos.new_capacity_title') }}</p><p class="mt-0.5 text-sm opacity-90" x-text="banner.message"></p></div>
                <button type="button" @click="banner = null" class="opacity-70 hover:opacity-100"><x-glyph name="x" size="18" /></button>
            </div>
            <span class="absolute bottom-0 start-0 h-1 w-full origin-left bg-white/60 rtl:origin-right" style="animation: shrink 6000ms linear forwards"></span>
        </div>
    </template>

    @if(! $eventId)
        <x-ui.card><x-ui.empty icon="calendar" :title="__('ops.pos.no_events')" /></x-ui.card>
    @else
        {{-- Capacity alert bar (F-POS-038) --}}
        <div class="mb-5 flex flex-wrap gap-2">
            @foreach($tickets as $t)
                @php
                    $sold = max(0, $t['capacity'] - $t['remaining']);
                    $pct = $t['capacity'] > 0 ? (int) round($sold / $t['capacity'] * 100) : 100;
                    [$tone, $msg] = $pct >= 100 ? ['bg-danger text-white', __('ops.pos.capacity_full')] : ($pct >= 90 ? ['bg-orange-500 text-white', __('ops.pos.capacity_90')] : ($pct >= 80 ? ['bg-gold-300 text-ink', __('ops.pos.capacity_80')] : ['bg-success/10 text-success ring-1 ring-success/20', __('ops.pos.capacity_ok')]));
                @endphp
                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold {{ $tone }}">
                    <x-glyph :name="$kindIcon[$t['type']]" size="14" />
                    {{ __('ops.pos.capacity_badge', ['label' => __('enums.ticket_kind_chip.'.$t['type']), 'confirmed' => $sold, 'capacity' => $t['capacity'], 'pct' => $pct]) }}@if($pct < 100){{ __('ops.pos.capacity_left', ['n' => $t['remaining']]) }}@endif
                    <span class="opacity-75">· {{ $msg }}</span>
                </span>
            @endforeach
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-6">
                {{-- Ticket type cards (F-POS-005/006) --}}
                <x-ui.card pad="p-5 sm:p-6">
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="flex items-center gap-2 text-lg font-extrabold"><x-glyph name="ticket" size="20" class="text-terracotta" />{{ __('ops.pos.add_tickets') }}</h2>
                        <x-ui.badge :tone="$admission >= $maxAdmission ? 'warning' : 'neutral'">{{ __('ops.pos.admission_counter', ['n' => $admission, 'max' => $maxAdmission]) }}</x-ui.badge>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach($tickets as $t)
                            @php $tone = $kindTone[$t['type']]; @endphp
                            <div wire:key="pt-{{ $t['id'] }}" class="relative overflow-hidden rounded-[1.5rem] bg-ivory p-5 ring-1 ring-bark/10 {{ $t['sold_out'] ? 'opacity-60' : '' }}">
                                <span class="absolute inset-x-0 top-0 h-1 {{ $tone['bar'] }}"></span>
                                <div class="flex items-center gap-3">
                                    <span class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br shadow-soft {{ $tone['tile'] }}"><x-glyph :name="$kindIcon[$t['type']]" size="22" /></span>
                                    <div>
                                        <p class="font-extrabold">{{ __('enums.ticket_kind.'.$t['type']) }}</p>
                                        @if($t['sold_out'])
                                            <p class="text-xs font-bold text-danger">{{ __('ops.pos.sold_out') }}</p>
                                        @else
                                            <p class="text-xs text-ink-soft">{{ __('ops.pos.available', ['n' => $t['remaining']]) }}</p>
                                        @endif
                                    </div>
                                </div>
                                <p class="mt-4 text-3xl font-extrabold tabular-nums" dir="ltr">{{ \App\Support\Formats::amount($t['price']) }} <span class="text-sm font-bold text-ink-soft">{{ __('ops.pos.riyal') }}</span></p>
                                <div class="mt-4 flex items-center gap-2">
                                    <div class="flex items-center rounded-full bg-white p-1 ring-1 ring-bark/10">
                                        <button type="button" wire:click="stepAdd('{{ $t['id'] }}', -1)" class="grid size-9 place-items-center rounded-full hover:bg-cream active:scale-90" aria-label="{{ __('public.select.decrease') }}"><x-glyph name="minus" size="15" stroke="2" /></button>
                                        <span class="w-8 text-center font-extrabold tabular-nums">{{ $addQty[$t['id']] ?? 1 }}</span>
                                        <button type="button" wire:click="stepAdd('{{ $t['id'] }}', 1)" class="grid size-9 place-items-center rounded-full hover:bg-cream active:scale-90" aria-label="{{ __('public.select.increase') }}"><x-glyph name="plus" size="15" stroke="2" /></button>
                                    </div>
                                    <button type="button" wire:click="addToCart('{{ $t['id'] }}')" @disabled($t['sold_out'])
                                        class="h-11 flex-1 rounded-full bg-maroon-600 text-sm font-bold text-white shadow-elegant transition hover:bg-maroon-700 active:scale-[.97] disabled:bg-bark/40 disabled:shadow-none">
                                        {{ $t['sold_out'] ? __('ops.pos.sold_out_btn') : __('ops.pos.add') }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>

                @if($count > 0)
                    {{-- Buyer (F-POS-013…019) --}}
                    <x-ui.card pad="p-5 sm:p-6">
                        <h2 class="text-lg font-extrabold">{{ __('ops.pos.buyer_title') }}</h2>
                        <p class="text-sm text-ink-soft">{{ __('ops.pos.buyer_sub') }}</p>

                        <div class="relative mt-5 rounded-2xl border-2 border-dashed border-sand p-4">
                            <p class="mb-2 flex items-center gap-2 text-sm font-bold"><x-glyph name="search" size="16" />{{ __('ops.pos.recall') }}</p>
                            <div class="flex gap-2">
                                <div class="flex-1"><x-ui.input wire:model.live.debounce.300ms="lookup" :placeholder="__('ops.pos.recall_ph')" autocomplete="off" /></div>
                                <x-ui.spinner wire:loading wire:target="lookup" class="self-center text-maroon-600" />
                            </div>
                            @if(mb_strlen(trim($lookup)) >= 3)
                                <ul class="mt-3 max-h-64 space-y-2 overflow-y-auto">
                                    @forelse($lookupResults as $i => $c)
                                        <li wire:key="lk-{{ $c['id'] }}">
                                            <button type="button" wire:click="pickCustomer({{ $i }})" class="flex w-full items-center gap-3 rounded-xl bg-white p-3 text-start ring-1 ring-bark/10 transition hover:ring-maroon-300">
                                                <span class="min-w-0 flex-1">
                                                    <span class="block font-bold">{{ $c['name'] }}</span>
                                                    <span class="block font-mono text-xs text-ink-soft" dir="ltr">{{ $c['country_code'] }}{{ $c['phone'] }} · {{ $c['id_number'] ?? $c['id_number_masked'] }} · {{ $c['nationality'] }}</span>
                                                    @if($c['history'])
                                                        <span class="mt-1.5 flex flex-wrap gap-1">
                                                            @foreach(array_slice($c['history'], 0, 4) as $hh)
                                                                <span class="rounded-full bg-cream px-2 py-0.5 text-[11px] font-bold text-bark">{{ \App\Support\Formats::dayMonth($hh['event_date']) ?: $hh['title'] }} · {{ $hh['ticket_count'] }}</span>
                                                            @endforeach
                                                        </span>
                                                    @endif
                                                </span>
                                                @isset($c['remaining_allowance'])<x-ui.badge size="sm" :tone="$c['remaining_allowance'] > 0 ? 'success' : 'danger'">{{ __('ops.pos.remaining_allowance', ['n' => $c['remaining_allowance']]) }}</x-ui.badge>@endisset
                                                <span class="text-sm font-bold text-maroon-600">{{ __('ops.pos.recall_select') }}</span>
                                            </button>
                                        </li>
                                    @empty
                                        <li class="text-sm text-ink-soft">{{ __('ops.pos.recall_none') }}</li>
                                    @endforelse
                                </ul>
                            @endif
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <x-ui.field :label="__('ops.pos.name')" required>
                                <x-ui.input wire:model.blur="buyer.name" icon="user" maxlength="100" />
                            </x-ui.field>
                            <x-ui.field :label="__('ops.pos.id_number')" required>
                                <x-ui.input wire:model.blur="buyer.id_number" icon="id-card" maxlength="30" :placeholder="$buyerIdMasked ?? __('ops.pos.id_number')" />
                            </x-ui.field>
                            <x-ui.field :label="__('ops.pos.phone')" required class="sm:col-span-2">
                                <div class="flex gap-2" dir="ltr">
                                    <div class="w-[150px] shrink-0">
                                        <x-ui.select wire:model.live="buyer.country_code">
                                            @foreach($codes as $c)<option value="{{ $c['code'] }}">{{ $c['flag'] }} {{ $c['code'] }}</option>@endforeach
                                        </x-ui.select>
                                    </div>
                                    <div class="flex-1"><x-ui.input type="tel" inputmode="numeric" dir="ltr" wire:model.blur="buyer.phone" placeholder="XXXXXXXX" /></div>
                                </div>
                            </x-ui.field>
                            <x-ui.field :label="__('ops.pos.email')" class="sm:col-span-2">
                                <x-ui.input type="email" dir="ltr" wire:model.blur="buyer.email" icon="mail" />
                            </x-ui.field>
                        </div>

                        <div class="mt-5">
                            <p class="mb-2 text-sm font-bold">{{ __('ops.pos.nationality') }} <span class="text-maroon-500">*</span></p>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                                @foreach(($moreNationalities ? $allNats : collect($gulf)) as $c)
                                    <button type="button" wire:click="setNationality(@js($c['ar']))" wire:key="nat-{{ $c['iso'] }}"
                                        @class(['flex h-12 items-center justify-center gap-1.5 rounded-xl text-sm font-bold ring-1 transition', 'bg-maroon-600 text-white ring-maroon-600 shadow-elegant' => $buyer['nationality'] === $c['ar'], 'bg-white ring-bark/15 hover:ring-bark/30' => $buyer['nationality'] !== $c['ar']])>
                                        <span class="text-lg">{{ $c['flag'] }}</span>{{ $natName($c) }}
                                    </button>
                                @endforeach
                            </div>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                <button type="button" wire:click="$toggle('moreNationalities')" class="text-sm font-bold text-maroon-600 hover:underline">{{ $moreNationalities ? __('ops.pos.less_nationalities') : __('ops.pos.more_nationalities') }}</button>
                                <div class="flex items-center gap-2 text-sm" x-data="{ other: {{ $knownNat ? 'false' : 'true' }} }">
                                    <label class="flex items-center gap-1.5 text-ink-soft"><input type="checkbox" x-model="other" class="rounded text-maroon-600"> {{ __('ops.pos.other_nationality') }}</label>
                                    <input x-show="other" x-cloak wire:model.blur="buyer.nationality" maxlength="60" class="h-9 w-40 rounded-xl border-0 bg-ivory px-3 text-sm ring-1 ring-bark/15 focus:ring-2 focus:ring-maroon-500/60">
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-ink-soft">{{ __('ops.pos.selected_nationality', ['x' => $buyer['nationality']]) }}</p>
                        </div>
                    </x-ui.card>

                    {{-- Additional holders (F-POS-020…023) --}}
                    @if(count($holders) > 0)
                        <x-ui.card pad="p-5 sm:p-6">
                            <h2 class="text-lg font-extrabold">{{ __('ops.pos.holders_title', ['n' => count($holders)]) }}</h2>
                            <p class="text-sm text-ink-soft">{{ __('ops.pos.holders_sub') }}</p>
                            <div class="mt-5 space-y-4">
                                @foreach($holders as $i => $h)
                                    @php $price = collect($tickets)->firstWhere('type', $h['type'])['price'] ?? 0; @endphp
                                    <div wire:key="ph-{{ $h['uid'] }}" class="rounded-[1.25rem] bg-ivory p-4 ring-1 ring-bark/10">
                                        <div class="mb-3 flex items-center justify-between gap-2">
                                            <p class="flex items-center gap-2 font-bold"><span class="size-2.5 rounded-full {{ $kindTone[$h['type']]['dot'] }}"></span>{{ __('ops.pos.holder_n', ['n' => $i + 1, 'type' => __('enums.ticket_kind.'.$h['type'])]) }}</p>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-ink-soft">{{ __('ops.pos.holder_price', ['price' => \App\Support\Formats::money($price, short: true)]) }}</span>
                                                <button type="button" wire:click="removeHolder({{ $i }})" class="grid size-8 place-items-center rounded-full text-danger hover:bg-danger/10" aria-label="{{ __('common.delete') }}"><x-glyph name="trash" size="16" /></button>
                                            </div>
                                        </div>
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <x-ui.input wire:model.blur="holders.{{ $i }}.name" :placeholder="__('ops.pos.holder_name_ph').' *'" maxlength="100" />
                                            <x-ui.input wire:model.blur="holders.{{ $i }}.id_number" :placeholder="__('ops.pos.id_number').' *'" maxlength="30" />
                                            <x-ui.select wire:model.live="holders.{{ $i }}.nationality">
                                                @foreach($allNats as $c)<option value="{{ $c['ar'] }}">{{ $c['flag'] }} {{ $natName($c) }}</option>@endforeach
                                                @unless($allNats->contains('ar', $h['nationality']))<option value="{{ $h['nationality'] }}">{{ $h['nationality'] }}</option>@endunless
                                            </x-ui.select>
                                            <div class="flex gap-2" dir="ltr">
                                                <div class="w-[118px] shrink-0">
                                                    <x-ui.select wire:model.live="holders.{{ $i }}.country_code">
                                                        @foreach($codes as $c)<option value="{{ $c['code'] }}">{{ $c['flag'] }} {{ $c['code'] }}</option>@endforeach
                                                    </x-ui.select>
                                                </div>
                                                <div class="flex-1"><x-ui.input type="tel" dir="ltr" inputmode="numeric" wire:model.blur="holders.{{ $i }}.phone" :placeholder="__('ops.pos.phone')" /></div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </x-ui.card>
                    @endif
                @endif
            </div>

            {{-- Cart sidebar (F-POS-010/011) --}}
            <aside class="lg:sticky lg:top-24">
                <x-ui.card pad="p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="flex items-center gap-2 text-lg font-extrabold"><x-glyph name="receipt" size="20" class="text-terracotta" />{{ __('ops.pos.cart') }}</h2>
                        <x-ui.badge tone="maroon">{{ __('ops.pos.cart_count', ['n' => $count]) }}</x-ui.badge>
                    </div>
                    @if($count === 0)
                        <x-ui.empty icon="ticket" :title="__('ops.pos.cart_empty')" :hint="__('ops.pos.cart_empty_hint')" class="!py-10" />
                    @else
                        <ul class="mt-4 space-y-4">
                            @foreach($lines as [$id, $t])
                                <li wire:key="cl-{{ $id }}" class="rounded-2xl bg-ivory p-3 ring-1 ring-bark/10">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="flex items-center gap-2 font-bold"><span class="size-2.5 rounded-full {{ $kindTone[$t['type']]['dot'] }}"></span>{{ __('enums.ticket_kind.'.$t['type']) }}</span>
                                        <span class="text-ink-soft" dir="ltr">{{ $cart[$id] }} × {{ \App\Support\Formats::amount($t['price']) }}</span>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @for($u = 1; $u <= $cart[$id]; $u++)
                                            <button type="button" wire:click="removeUnit('{{ $id }}')" title="{{ __('ops.pos.remove_unit') }}"
                                                class="group relative grid size-12 place-items-center rounded-xl bg-white ring-1 ring-bark/10 transition hover:ring-danger/40 {{ $kindTone[$t['type']]['text'] }}">
                                                <x-glyph :name="$kindIcon[$t['type']]" size="20" />
                                                <span class="absolute bottom-0.5 end-1 text-[10px] font-bold text-ink-soft">{{ $u }}</span>
                                                <span class="absolute -end-1.5 -top-1.5 hidden size-5 place-items-center rounded-full bg-danger text-white group-hover:grid"><x-glyph name="x" size="11" stroke="2.5" /></span>
                                            </button>
                                        @endfor
                                    </div>
                                    <div class="mt-3 flex justify-between text-sm"><span class="text-ink-soft">{{ __('ops.pos.line_total') }}</span><span class="font-extrabold tabular-nums">{{ \App\Support\Formats::money(\App\Support\Money::of($t['price'])->multiply($cart[$id])->toDecimal(), short: true) }}</span></div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="mt-5 rounded-2xl bg-ink p-4 text-cream">
                        <div class="flex items-center justify-between"><span class="text-sm opacity-80">{{ __('ops.pos.grand_total') }}</span><span class="text-2xl font-extrabold tabular-nums">{{ \App\Support\Formats::money($total->toDecimal(), short: true) }}</span></div>
                        <p class="mt-1 text-xs opacity-70">{{ __('ops.pos.admission_total', ['n' => $admission, 'max' => $maxAdmission]) }}</p>
                    </div>
                    <x-ui.button variant="primary" size="xl" block class="mt-4" trail="arrow" wire:click="submit" loading="submit" wire:loading.attr="disabled" wire:target="submit" :disabled="$count === 0">
                        <span wire:loading.remove wire:target="submit">{{ __('ops.pos.submit') }}</span>
                        <span wire:loading wire:target="submit">{{ __('ops.pos.processing') }}</span>
                    </x-ui.button>
                    @if($lastReference)
                        <a href="{{ route('staff.tickets', $lastReference) }}" target="_blank" class="mt-3 flex items-center justify-center gap-2 text-sm font-bold text-ink-soft hover:text-ink"><x-glyph name="receipt" size="16" />{{ __('ops.pos.reprint_last') }} · <span class="font-mono" dir="ltr">{{ $lastReference }}</span></a>
                    @endif
                </x-ui.card>
            </aside>
        </div>
    @endif

    {{-- Success dialog (F-POS-035 rebuild: reference + first QR + print/WhatsApp) --}}
    <x-ui.dialog name="pos-success" max-width="sm:max-w-md">
        @if($success)
            <div class="text-center">
                <div class="relative mx-auto size-20">
                    <span class="absolute inset-0 animate-ping rounded-full bg-success/25"></span>
                    <span class="relative grid size-20 place-items-center rounded-full bg-success text-white shadow-[0_18px_40px_-14px_rgb(24_139_72/0.8)]"><x-glyph name="check" size="36" stroke="2.4" /></span>
                </div>
                <h2 class="mt-5 text-2xl font-extrabold">{{ __('ops.pos.success_title') }}</h2>
                <p class="text-ink-soft">{{ __('ops.pos.success_sub') }}</p>
                <div class="mt-4 flex items-center justify-center gap-1 rounded-2xl bg-cream/70 p-3 ring-1 ring-sand/60">
                    <span class="select-all font-mono text-lg font-extrabold" dir="ltr">{{ $success['reference'] }}</span>
                    <x-ui.copy :text="$success['reference']" />
                </div>
                @if($success['qr'])
                    <div class="mx-auto mt-4 size-40 rounded-2xl bg-white p-2 ring-1 ring-bark/10 [&>svg]:size-full">{!! $success['qr'] !!}</div>
                @endif
                <div class="mt-4 grid grid-cols-2 gap-3 text-start">
                    <div class="rounded-2xl bg-ivory p-3 ring-1 ring-bark/10"><p class="text-xs text-ink-soft">{{ __('ops.pos.ticket_count') }}</p><p class="text-3xl font-extrabold">{{ $success['count'] }}</p></div>
                    <div class="rounded-2xl bg-ivory p-3 ring-1 ring-bark/10"><p class="text-xs text-ink-soft">{{ __('ops.pos.grand_total') }}</p><p class="text-xl font-extrabold text-maroon-600">{{ \App\Support\Formats::money($success['total'], short: true) }}</p></div>
                </div>
                <div class="mt-4 text-start text-sm">
                    <p class="font-bold">{{ __('ops.pos.ticket_types') }}</p>
                    @foreach($success['types'] as $ty)<p class="text-ink-soft">{{ $ty['qty'] }}x {{ $ty['label'] }}</p>@endforeach
                    <p class="mt-2 font-bold">{{ __('ops.pos.holders') }}</p>
                    @foreach($success['holders'] as $k => $hh)<p class="text-ink-soft">{{ $k === 0 ? '👤 ' : '' }}{{ $hh['name'] }} <span class="text-xs">({{ $hh['type'] }})</span></p>@endforeach
                </div>
                <div class="mt-6 grid gap-2 sm:grid-cols-2">
                    <x-ui.button variant="secondary" icon="receipt" :href="route('staff.tickets', $success['reference'])" target="_blank">{{ __('ops.pos.print') }}</x-ui.button>
                    <x-ui.button variant="secondary" icon="whatsapp" wire:click="sendWhatsApp" loading="sendWhatsApp">{{ __('ops.pos.send_whatsapp') }}</x-ui.button>
                    <x-ui.button variant="primary" class="sm:col-span-2" x-on:click="$dispatch('close-dialog', 'pos-success')">{{ __('ops.pos.ok') }}</x-ui.button>
                </div>
            </div>
        @endif
    </x-ui.dialog>

    {{-- Manual check-in dialog (F-POS-042…044) --}}
    <x-ui.dialog name="pos-checkin" :title="__('ops.pos.checkin_title')" max-width="sm:max-w-2xl">
        <p class="-mt-3 mb-4 text-sm text-ink-soft">{{ __('ops.pos.checkin_sub') }}</p>
        <div class="relative">
            <x-ui.input wire:model.live.debounce.300ms="checkinTerm" icon="search" :placeholder="__('ops.pos.checkin_ph')" class="!h-12" autocomplete="off" />
            <x-ui.spinner wire:loading wire:target="checkinTerm" class="absolute end-3 top-3.5 text-maroon-600" />
        </div>
        @if(mb_strlen(trim($checkinTerm)) < 2)
            <p class="mt-6 text-center text-sm text-ink-soft">{{ __('ops.pos.checkin_type_more') }}</p>
        @else
            <p class="mt-3 text-xs font-bold text-ink-soft">{{ __('ops.pos.checkin_counter', ['n' => count($checkinResults), 'present' => collect($checkinResults)->where('is_present', true)->count()]) }}</p>
            <ul class="mt-3 max-h-[55vh] space-y-2 overflow-y-auto">
                @forelse($checkinResults as $r)
                    <li wire:key="ci-{{ $r['holder_id'] }}" class="flex items-center gap-3 rounded-2xl p-3 ring-1 {{ $r['is_present'] ? 'bg-success/[0.05] ring-success/20' : 'bg-ivory ring-bark/10' }}">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 font-bold">{{ $r['ticket_holder_name'] }}
                                <x-ui.badge size="sm" :tone="$r['ticket_type']" :icon="$kindIcon[$r['ticket_type']]">{{ $r['ticket_type_label'] }}</x-ui.badge>
                                @if($r['is_present'])<x-ui.badge size="sm" tone="success">{{ __('common.present') }}</x-ui.badge>@elseif(! $r['is_today'])<x-ui.badge size="sm" tone="neutral">{{ __('ops.pos.not_today') }}</x-ui.badge>@endif
                            </p>
                            <p class="mt-0.5 font-mono text-xs text-ink-soft" dir="ltr">{{ $r['ticket_holder_phone'] ?: $r['customer_phone'] }} · #{{ $r['booking_reference'] }}</p>
                            <p class="text-xs text-ink-soft">{{ $r['event_title'] }} · {{ __('ops.pos.customer', ['name' => $r['customer_name']]) }}</p>
                            @if($r['confirmed_by_name'])<p class="text-xs text-gold-700">{{ __('ops.pos.registered_by', ['name' => $r['confirmed_by_name']]) }}</p>@endif
                            @if(! $r['is_present'] && ! $r['admissible'] && $r['refusal_message'])<p class="mt-1 text-xs font-bold text-danger">{{ $r['refusal_message'] }}</p>@endif
                        </div>
                        @if($r['is_present'])
                            <span class="grid size-10 place-items-center rounded-full bg-success text-white"><x-glyph name="check" size="18" stroke="2.4" /></span>
                        @else
                            <x-ui.button size="sm" variant="success" wire:click="admit('{{ $r['holder_id'] }}')" loading="admit('{{ $r['holder_id'] }}')" :disabled="! $r['admissible']">{{ __('ops.pos.checkin') }}</x-ui.button>
                        @endif
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-ink-soft">{{ __('ops.pos.checkin_none') }}</li>
                @endforelse
            </ul>
        @endif
    </x-ui.dialog>
</div>
