<x-layouts.auth :title="__('auth.reset_title')">
    <x-ui.card pad="p-7">
        <h1 class="text-center text-2xl font-extrabold text-ink">{{ __('auth.reset_title') }}</h1>
        <form method="POST" action="{{ route('password.update') }}" class="mt-7 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <x-ui.field :label="__('auth.email')" for="email" required error="email">
                <x-ui.input id="email" name="email" type="email" dir="ltr" icon="mail" :value="old('email', $request->email)" required />
            </x-ui.field>
            <x-ui.field :label="__('auth.new_password')" for="password" required error="password">
                <x-ui.input id="password" name="password" type="password" dir="ltr" icon="key" autocomplete="new-password" required />
            </x-ui.field>
            <x-ui.field :label="__('auth.password_confirmation')" for="password_confirmation" required>
                <x-ui.input id="password_confirmation" name="password_confirmation" type="password" dir="ltr" icon="key" autocomplete="new-password" required />
            </x-ui.field>
            <x-ui.button type="submit" size="lg" block>{{ __('auth.reset_do') }}</x-ui.button>
        </form>
    </x-ui.card>
</x-layouts.auth>
