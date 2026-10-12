@php
    $kinds = collect(\App\Enums\TicketKind::cases())->keyBy->value;
    $phonePh = fn (string $cc) => __('public.checkout.ph.'.$cc) !== 'public.checkout.ph.'.$cc ? __('public.checkout.ph.'.$cc) : __('public.checkout.ph.default');
    $maxLen = \App\Support\PhoneNumber::MAX_LENGTH;
    $isEn = app()->getLocale() === 'en';
    $countryName = fn ($c) => $isEn ? $c['en'] : $c['ar'];
    $low = (int) config('qcamelmc.orders.low_stock_threshold', 10);
@endphp
<div class="mx-auto max-w-6xl px-4 pb-16 pt-8 sm:px-6 sm:pt-12"
     x-data
     x-on:scroll-to-error.window="$nextTick(() => document.querySelector('[data-invalid]')?.scrollIntoView({ behavior: 'smooth', block: 'center' }))"
     x-on:scroll-to-terms.window="document.getElementById('terms')?.scrollIntoView({ behavior: 'smooth', block: 'center' })">

    {{-- Title + step indicator --}}
    <div class="animate-rise text-center">
        <h1 class="text-3xl font-extrabold tracking-tight text-bark md:text-4xl">{{ __('public.checkout.title') }}</h1>
        <p class="mt-2 text-ink-soft">{{ $eventTitle }} · {{ $eventDate }}</p>
        <ol class="mx-auto mt-7 flex max-w-md items-center justify-between gap-2" aria-label="steps">
            @foreach(__('public.checkout.steps') as $n => $label)
                <li class="flex flex-1 items-center gap-2 {{ $n < 2 ? 'after:h-px after:flex-1 after:bg-gradient-to-l after:from-sand after:to-transparent rtl:after:bg-gradient-to-r' : '' }}">
                    <span @class(['grid size-8 shrink-0 place-items-center rounded-full text-sm font-extrabold transition', 'bg-success text-white' => $n === 0, 'bg-maroon-600 text-white shadow-elegant ring-4 ring-maroon-100' => $n === 1, 'bg-white text-ink-soft ring-1 ring-bark/15' => $n === 2])>
                        @if($n === 0)<x-glyph name="check" size="16" stroke="2.4" />@else{{ $n + 1 }}@endif
                    </span>
                    <span class="text-xs font-bold {{ $n === 1 ? 'text-ink' : 'text-ink-soft' }}">{{ $label }}</span>
                </li>
            @endforeach
        </ol>
    </div>

    <form wire:submit="submit" novalidate class="mt-10 grid gap-8 lg:grid-cols-3">
        {{-- Side column: order summary (F-PUB-036) --}}
        <aside class="lg:order-last">
            <div class="space-y-4 lg:sticky lg:top-28">
                <x-ui.card pad="p-6">
                    <h2 class="flex items-center gap-2 text-lg font-extrabold text-ink"><x-glyph name="receipt" size="20" class="text-terracotta" />{{ __('public.checkout.summary') }}</h2>
                    <ul class="mt-5 divide-y divide-dashed divide-bark/15">
                        @foreach($tickets as $t)
                            @php $q = (int) ($qty[$t['id']] ?? 0); @endphp
                            @continue($q === 0)
                            @php [$canInc] = $this->canIncrease($t['id']); $label = $kinds[$t['type']]->longLabel(); @endphp
                            <li wire:key="line-{{ $t['id'] }}" class="py-4 first:pt-0 last:pb-0">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-bold text-ink">{{ $label }}</p>
                                        <p class="text-sm text-ink-soft">{{ __('public.checkout.qty', ['n' => $q]) }}</p>
                                    </div>
                                    <p class="whitespace-nowrap font-extrabold tabular-nums text-ink">{{ \App\Support\Formats::money(\App\Support\Money::of($t['price'])->multiply($q)->toDecimal()) }}</p>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-3">
                                    @if($q > $t['remaining'])
                                        <p class="flex items-center gap-1.5 text-xs font-bold text-danger"><x-glyph name="alert" size="14" />{{ __('public.checkout.only_left', ['n' => $t['remaining']]) }}</p>
                                    @else
                                        <p class="text-xs font-medium {{ $t['remaining'] <= $low ? 'text-gold-700' : 'text-ink-soft' }}">{{ __('public.checkout.left', ['n' => $t['remaining']]) }}</p>
                                    @endif
                                    <x-ui.stepper size="sm" :value="$q" dec="dec('{{ $t['id'] }}')" inc="inc('{{ $t['id'] }}')" :can-inc="$canInc" :label="$label" />
                                </div>

                                <x-ui.dialog :name="'remove-'.$t['id']" max-width="sm:max-w-sm">
                                    <div class="space-y-5 text-center">
                                        <span class="mx-auto grid size-14 place-items-center rounded-full bg-danger/10 text-danger"><x-glyph name="trash" size="24" /></span>
                                        <p class="text-lg font-bold text-ink">{{ __('public.checkout.confirm_remove', ['type' => $label]) }}</p>
                                        <div class="flex gap-3">
                                            <x-ui.button variant="secondary" block x-on:click="$dispatch('close-dialog')">{{ __('common.cancel') }}</x-ui.button>
                                            <x-ui.button variant="danger" block wire:click="removeLine('{{ $t['id'] }}')">{{ __('public.checkout.remove') }}</x-ui.button>
                                        </div>
                                    </div>
                                </x-ui.dialog>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-5 flex items-center justify-between rounded-2xl bg-maroon-600 px-5 py-4 text-white shadow-elegant">
                        <span class="font-bold">{{ __('public.checkout.total') }}</span>
                        <span class="text-xl font-extrabold tabular-nums">{{ \App\Support\Formats::money($total->toDecimal()) }}</span>
                    </div>
                    <ul class="mt-4 space-y-1.5 text-xs leading-relaxed text-ink-soft">
                        <li>* {{ __('public.checkout.note_whatsapp') }}</li>
                        <li>* {{ __('public.checkout.note_gate') }}</li>
                    </ul>
                </x-ui.card>
                <x-ui.button variant="secondary" block :href="route('tickets.select', $eventId)" icon="arrow" class="hidden lg:inline-flex ltr:[&_svg]:-scale-x-100">{{ __('public.checkout.back_select') }}</x-ui.button>
            </div>
        </aside>

        <div class="space-y-6 lg:col-span-2">
            {{-- Customer = holder 1 (F-PUB-040…047) --}}
            <x-ui.card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-maroon-600 text-sm font-extrabold text-white shadow-elegant">1</span>
                        <div>
                            <h2 class="text-lg font-extrabold text-ink">{{ __('public.checkout.customer_title') }}</h2>
                            <p class="mt-0.5 text-sm text-ink-soft">{{ __('public.checkout.customer_sub') }}</p>
                        </div>
                    </div>
                    @if($customerType)<x-ui.badge :tone="$customerType">{{ __('enums.ticket_kind_chip.'.$customerType) }}</x-ui.badge>@endif
                </div>

                <div class="mt-7 grid gap-5 sm:grid-cols-2">
                    <x-ui.field :label="__('public.checkout.name')" for="c-name" required error="customer.name" class="sm:col-span-2" :data-invalid="$errors->has('customer.name') ?: null">
                        <x-ui.input id="c-name" wire:model.blur="customer.name" icon="user" autocomplete="name" maxlength="100" :placeholder="__('public.checkout.name_ph')" :invalid="$errors->has('customer.name')" />
                    </x-ui.field>

                    <x-ui.field :label="__('public.checkout.nationality')" for="c-nat" required error="customer.nationality" :data-invalid="$errors->has('customer.nationality') ?: null">
                        <x-ui.select id="c-nat" wire:model.live="customer.nationality" :invalid="$errors->has('customer.nationality')">
                            @foreach($countries as $c)<option value="{{ $c['ar'] }}">{{ $c['flag'] }} {{ $countryName($c) }}</option>@endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field :label="__('public.checkout.id_number')" for="c-id" required error="customer.id_number" :data-invalid="$errors->has('customer.id_number') ?: null">
                        <x-ui.input id="c-id" wire:model.blur="customer.id_number" icon="id-card" maxlength="30" inputmode="text" autocomplete="off" :placeholder="__('public.checkout.id_ph')" :invalid="$errors->has('customer.id_number')" />
                    </x-ui.field>

                    <x-ui.field :label="__('public.checkout.phone')" for="c-phone" required error="customer.phone" :help="__('public.checkout.phone_help')" class="sm:col-span-2" :data-invalid="$errors->has('customer.phone') ?: null">
                        <div class="flex gap-2" dir="ltr">
                            <div class="w-[110px] shrink-0 sm:w-[124px]">
                                <x-ui.select wire:model.live="customer.country_code" :aria-label="__('public.checkout.code')">
                                    @foreach($codes as $c)<option value="{{ $c['code'] }}">{{ $c['flag'] }} {{ $c['code'] }}</option>@endforeach
                                </x-ui.select>
                            </div>
                            <div class="flex-1">
                                <x-ui.input id="c-phone" type="tel" inputmode="numeric" wire:model.blur="customer.phone" autocomplete="tel-national" dir="ltr"
                                    :maxlength="$maxLen[$customer['country_code']] ?? 15" :placeholder="$phonePh($customer['country_code'])" :invalid="$errors->has('customer.phone')" />
                            </div>
                        </div>
                    </x-ui.field>

                    <x-ui.field :label="__('public.checkout.email')" for="c-email" :optional="__('public.checkout.email_optional')" error="customer.email" class="sm:col-span-2">
                        <x-ui.input id="c-email" type="email" wire:model.blur="customer.email" icon="mail" autocomplete="email" dir="ltr" maxlength="254" placeholder="name@example.com" :invalid="$errors->has('customer.email')" />
                    </x-ui.field>
                </div>
            </x-ui.card>

            {{-- Additional holders (F-PUB-048/049) --}}
            @if(count($holders) > 0)
                <x-ui.card>
                    <div class="flex items-start gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-maroon-600 text-sm font-extrabold text-white shadow-elegant">2</span>
                        <div>
                            <h2 class="text-lg font-extrabold text-ink">{{ __('public.checkout.holders_title') }}</h2>
                            <p class="mt-0.5 text-sm text-ink-soft">{{ __('public.checkout.holders_sub') }}</p>
                        </div>
                    </div>

                    <div class="mt-7 space-y-5">
                        @foreach($holders as $i => $h)
                            @php $p = "holders.$i"; @endphp
                            <fieldset wire:key="h-{{ $h['uid'] }}" class="rounded-[1.5rem] bg-ivory p-5 ring-1 ring-bark/10 sm:p-6">
                                <legend class="sr-only">{{ __('public.checkout.holder', ['n' => $i + 2, 'type' => $kinds[$h['type']]->longLabel()]) }}</legend>
                                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-8 place-items-center rounded-full bg-white text-sm font-extrabold text-maroon-600 ring-1 ring-maroon-200">{{ $i + 2 }}</span>
                                        <p class="font-bold text-ink">{{ __('public.checkout.holder', ['n' => $i + 2, 'type' => $kinds[$h['type']]->longLabel()]) }}</p>
                                    </div>
                                    <x-ui.badge tone="neutral" icon="qr" size="sm">{{ __('public.checkout.own_qr') }}</x-ui.badge>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-ui.field :label="__('public.checkout.name')" :for="'h-name-'.$i" required :error="$p.'.name'" :data-invalid="$errors->has($p.'.name') ?: null">
                                        <x-ui.input :id="'h-name-'.$i" wire:model.blur="{{ $p }}.name" maxlength="100" :placeholder="__('public.checkout.name_ph')" :invalid="$errors->has($p.'.name')" />
                                    </x-ui.field>
                                    <x-ui.field :label="__('public.checkout.nationality')" required :error="$p.'.nationality'">
                                        <x-ui.select wire:model.live="{{ $p }}.nationality">
                                            @foreach($countries as $c)<option value="{{ $c['ar'] }}">{{ $c['flag'] }} {{ $countryName($c) }}</option>@endforeach
                                        </x-ui.select>
                                    </x-ui.field>
                                    <x-ui.field :label="__('public.checkout.id_number')" :for="'h-id-'.$i" required :error="$p.'.id_number'" :data-invalid="$errors->has($p.'.id_number') ?: null">
                                        <x-ui.input :id="'h-id-'.$i" wire:model.blur="{{ $p }}.id_number" maxlength="30" :placeholder="__('public.checkout.id_ph')" :invalid="$errors->has($p.'.id_number')" />
                                    </x-ui.field>
                                    <x-ui.field :label="__('public.checkout.phone_short')" :for="'h-phone-'.$i" required :error="$p.'.phone'" :help="__('public.checkout.phone_help')" :data-invalid="$errors->has($p.'.phone') ?: null">
                                        <div class="flex gap-2" dir="ltr">
                                            <div class="w-[104px] shrink-0">
                                                <x-ui.select wire:model.live="{{ $p }}.country_code" :aria-label="__('public.checkout.code')">
                                                    @foreach($codes as $c)<option value="{{ $c['code'] }}">{{ $c['flag'] }} {{ $c['code'] }}</option>@endforeach
                                                </x-ui.select>
                                            </div>
                                            <div class="flex-1">
                                                <x-ui.input :id="'h-phone-'.$i" type="tel" inputmode="numeric" dir="ltr" wire:model.blur="{{ $p }}.phone"
                                                    :maxlength="$maxLen[$h['country_code']] ?? 15" :placeholder="$phonePh($h['country_code'])" :invalid="$errors->has($p.'.phone')" />
                                            </div>
                                        </div>
                                        @if($customer['phone'] !== '' && $h['phone'] === '')
                                            <button type="button" wire:click="copyMainTo({{ $i }})" class="mt-1 text-xs font-bold text-maroon-600 underline-offset-4 hover:underline">{{ __('public.checkout.copy_from_main') }}</button>
                                        @endif
                                    </x-ui.field>
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif

            {{-- Terms (F-PUB-050) --}}
            <div id="terms" class="overflow-hidden rounded-[1.75rem] bg-danger/[0.04] p-6 ring-2 ring-danger/40 sm:p-7">
                <h2 class="flex items-center gap-2 text-lg font-extrabold text-danger"><x-glyph name="alert" size="22" />{{ __('public.checkout.terms_title') }}</h2>
                <ul class="mt-4 space-y-2.5 font-bold leading-relaxed text-ink">
                    @foreach($termsLines as $line)<li>{{ $line }}</li>@endforeach
                </ul>
                <button type="button" wire:click="toggleTerms" aria-pressed="{{ $terms ? 'true' : 'false' }}"
                    @class(['mt-6 flex min-h-14 w-full items-center justify-center gap-2 rounded-full px-6 text-base font-extrabold text-white transition duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] active:scale-[0.99]',
                        'bg-success shadow-[0_14px_30px_-14px_rgb(24_139_72/0.8)]' => $terms,
                        'animate-recent-pulse bg-danger shadow-[0_14px_30px_-14px_rgb(196_48_43/0.8)]' => ! $terms])>
                    @if($terms)<x-glyph name="check-circle" size="22" />{{ __('public.checkout.terms_accepted') }}@else{{ __('public.checkout.terms_accept') }}@endif
                </button>
            </div>

            {{-- Payment method (F-PUB-051) --}}
            <x-ui.card>
                <h2 class="text-lg font-extrabold text-ink">{{ __('public.checkout.method_title') }}</h2>
                <div class="mt-5 grid gap-3" role="radiogroup">
                    @foreach($this->methods() as $m)
                        <label wire:key="m-{{ $m }}" @class(['flex cursor-pointer items-center gap-4 rounded-2xl p-4 ring-1 transition duration-300', 'bg-maroon-50/60 ring-2 ring-maroon-500/60' => $method === $m, 'bg-ivory ring-bark/10 hover:ring-bark/25' => $method !== $m])>
                            <input type="radio" wire:model.live="method" value="{{ $m }}" class="sr-only">
                            <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-white text-maroon-600 shadow-soft ring-1 ring-bark/10"><x-glyph :name="$m === 'sadad' ? 'card' : 'cash'" size="22" /></span>
                            <span class="flex-1">
                                <span class="block font-extrabold text-ink">{{ __('public.checkout.method_'.($m === 'sadad' ? 'sadad' : 'cash')) }}</span>
                                <span class="block text-sm text-ink-soft">{{ __('public.checkout.method_'.($m === 'sadad' ? 'sadad' : 'cash').'_sub') }}</span>
                            </span>
                            <span @class(['grid size-6 place-items-center rounded-full ring-2 transition', 'bg-maroon-600 ring-maroon-600 text-white' => $method === $m, 'ring-bark/25' => $method !== $m])>
                                @if($method === $m)<x-glyph name="check" size="14" stroke="3" />@endif
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-7">
                    <x-ui.button type="submit" variant="primary" size="xl" block :trail="$terms ? 'lock' : null" loading="submit" wire:loading.attr="disabled" wire:target="submit" :class="$terms ? '' : 'opacity-70'">
                        <span wire:loading.remove wire:target="submit">{{ $terms ? __('public.checkout.pay_amount', ['amount' => \App\Support\Formats::money($total->toDecimal())]) : __('public.checkout.submit') }}</span>
                        <span wire:loading wire:target="submit">{{ __('public.checkout.submitting') }}</span>
                    </x-ui.button>
                    <p class="mt-3 flex items-center justify-center gap-1.5 text-xs text-ink-soft"><x-glyph name="shield" size="14" class="text-success" />{{ __('public.checkout.secure_note') }}</p>
                </div>
            </x-ui.card>

            <x-ui.button variant="secondary" block :href="route('tickets.select', $eventId)" icon="arrow" class="lg:hidden ltr:[&_svg]:-scale-x-100">{{ __('public.checkout.back_select') }}</x-ui.button>
        </div>
    </form>
</div>
