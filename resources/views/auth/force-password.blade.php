<x-layouts.auth :title="__('auth.force_title')">
    <x-ui.card pad="p-7">
        <div class="text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-gold-100 text-gold-700"><x-glyph name="key" size="26" /></span>
            <h1 class="mt-5 text-2xl font-extrabold text-ink">{{ __('auth.force_title') }}</h1>
            <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ __('auth.force_body') }}</p>
        </div>
        <form method="POST" action="{{ route('security.password.update') }}" class="mt-7 space-y-5">
            @csrf
            <x-ui.field :label="__('auth.new_password')" for="password" required error="password">
                <x-ui.input id="password" name="password" type="password" dir="ltr" icon="key" autocomplete="new-password" required autofocus />
            </x-ui.field>
            <x-ui.field :label="__('auth.password_confirmation')" for="password_confirmation" required>
                <x-ui.input id="password_confirmation" name="password_confirmation" type="password" dir="ltr" icon="key" autocomplete="new-password" required />
            </x-ui.field>
            <x-ui.button type="submit" size="lg" block trail="arrow">{{ __('auth.save_continue') }}</x-ui.button>
        </form>
    </x-ui.card>
</x-layouts.auth>
