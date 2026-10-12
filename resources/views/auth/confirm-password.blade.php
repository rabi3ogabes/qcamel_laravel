<x-layouts.auth :title="__('auth.confirm_password')">
    <x-ui.card pad="p-7">
        <div class="text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-maroon-50 text-maroon-600"><x-glyph name="lock" size="26" /></span>
            <h1 class="mt-5 text-2xl font-extrabold text-ink">{{ __('auth.confirm_password') }}</h1>
            <p class="mt-2 text-sm text-ink-soft">{{ __('auth.confirm_password_body') }}</p>
        </div>
        <form method="POST" action="{{ url('/user/confirm-password') }}" class="mt-7 space-y-5">
            @csrf
            <x-ui.field :label="__('auth.password_label')" for="password" required error="password">
                <x-ui.input id="password" name="password" type="password" dir="ltr" icon="key" autocomplete="current-password" required autofocus />
            </x-ui.field>
            <x-ui.button type="submit" size="lg" block>{{ __('auth.confirm') }}</x-ui.button>
        </form>
    </x-ui.card>
</x-layouts.auth>
