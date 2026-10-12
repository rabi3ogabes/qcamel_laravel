<div>
    <x-ui.card pad="p-7">
        <div class="text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-maroon-50 text-maroon-600 ring-8 ring-maroon-50/50"><x-glyph name="shield" size="26" /></span>
            <h1 class="mt-5 text-2xl font-extrabold text-ink">{{ __('auth.two_factor_setup_title') }}</h1>
            <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ __('auth.two_factor_setup_body') }}</p>
        </div>

        @if($step === 'start')
            <x-ui.button size="lg" block class="mt-7" wire:click="enable" loading="enable" trail="arrow">{{ __('auth.two_factor_enable') }}</x-ui.button>
        @elseif($step === 'scan')
            <div class="mt-7 space-y-5">
                <p class="text-center text-sm font-bold text-ink">{{ __('auth.two_factor_scan') }}</p>
                <div class="mx-auto w-fit rounded-3xl bg-white p-4 ring-1 ring-bark/10 [&>svg]:size-48">{!! $qr !!}</div>
                <div class="rounded-2xl bg-ivory p-3 text-center ring-1 ring-bark/10">
                    <p class="text-xs text-ink-soft">{{ __('auth.two_factor_secret') }}</p>
                    <div class="mt-1 flex items-center justify-center gap-1">
                        <code class="select-all font-mono text-sm font-bold tracking-wider text-ink" dir="ltr">{{ $secret }}</code>
                        <x-ui.copy :text="$secret" />
                    </div>
                </div>
                <form wire:submit="confirm" class="space-y-4">
                    <x-ui.field :label="__('auth.two_factor_code')" for="tf-code" error="code">
                        <x-ui.input id="tf-code" wire:model="code" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="6" placeholder="••••••" class="text-center !text-2xl font-bold tracking-[0.5em]" :invalid="$errors->has('code')" />
                    </x-ui.field>
                    <x-ui.button type="submit" size="lg" block loading="confirm">{{ __('auth.two_factor_confirm') }}</x-ui.button>
                </form>
            </div>
        @else
            <div class="mt-7 space-y-5">
                <p class="flex items-center justify-center gap-2 font-bold text-success"><x-glyph name="check-circle" size="20" />{{ __('auth.two_factor_enabled') }}</p>
                @if($codes)
                    <div class="rounded-2xl bg-ivory p-4 ring-1 ring-bark/10">
                        <p class="text-sm font-bold text-ink">{{ __('auth.two_factor_codes') }}</p>
                        <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm" dir="ltr">
                            @foreach($codes as $c)<li class="rounded-lg bg-white px-2 py-1.5 text-center ring-1 ring-bark/10">{{ $c }}</li>@endforeach
                        </ul>
                        <div class="mt-3 flex justify-center"><x-ui.copy :text="implode(PHP_EOL, $codes)" :label="__('auth.copy_codes')" /></div>
                    </div>
                @endif
                <x-ui.button size="lg" block :href="$home" trail="arrow">{{ __('auth.two_factor_continue') }}</x-ui.button>
            </div>
        @endif
    </x-ui.card>
</div>
