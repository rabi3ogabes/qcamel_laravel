<x-layouts.auth :title="__('auth.two_factor_title')">
    <x-ui.card pad="p-7" x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
        <div class="text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-maroon-50 text-maroon-600 ring-8 ring-maroon-50/50"><x-glyph name="shield" size="26" /></span>
            <h1 class="mt-5 text-2xl font-extrabold text-ink">{{ __('auth.two_factor_title') }}</h1>
            <p class="mt-2 text-sm text-ink-soft" x-show="!recovery">{{ __('auth.two_factor_body') }}</p>
        </div>
        <form method="POST" action="{{ url('/two-factor-challenge') }}" class="mt-7 space-y-5">
            @csrf
            <div x-show="!recovery">
                <x-ui.field :label="__('auth.two_factor_code')" for="code" error="code">
                    <x-ui.input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="6" pattern="[0-9]*" placeholder="••••••" class="text-center !text-2xl font-bold tracking-[0.5em]" x-bind:disabled="recovery" autofocus />
                </x-ui.field>
            </div>
            <div x-show="recovery" x-cloak>
                <x-ui.field :label="__('auth.two_factor_recovery_code')" for="recovery_code" error="recovery_code">
                    <x-ui.input id="recovery_code" name="recovery_code" dir="ltr" autocomplete="off" x-bind:disabled="!recovery" />
                </x-ui.field>
            </div>
            <x-ui.button type="submit" size="lg" block trail="arrow">{{ __('auth.two_factor_verify') }}</x-ui.button>
            <button type="button" @click="recovery = !recovery" class="block w-full text-center text-sm font-bold text-maroon-600 underline-offset-4 hover:underline">
                <span x-show="!recovery">{{ __('auth.two_factor_recovery') }}</span><span x-show="recovery" x-cloak>{{ __('auth.two_factor_use_code') }}</span>
            </button>
        </form>
    </x-ui.card>
</x-layouts.auth>
