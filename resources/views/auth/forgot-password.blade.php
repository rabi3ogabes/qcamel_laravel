<x-layouts.auth :title="__('auth.reset_title')">
    <x-ui.card pad="p-7">
        <div class="text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-maroon-50 text-maroon-600"><x-glyph name="mail" size="26" /></span>
            <h1 class="mt-5 text-2xl font-extrabold text-ink">{{ __('auth.reset_title') }}</h1>
        </div>
        @if(session('status'))
            <p class="mt-6 flex items-start gap-2 rounded-2xl bg-success/[0.07] p-4 text-sm font-medium text-success ring-1 ring-success/20" role="status"><x-glyph name="check-circle" size="18" />{{ __('auth.reset_sent') }}</p>
        @endif
        <form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-5">
            @csrf
            <x-ui.field :label="__('auth.email')" for="email" required error="email">
                <x-ui.input id="email" name="email" type="email" dir="ltr" icon="mail" :value="old('email')" required autofocus :placeholder="__('auth.email_placeholder')" />
            </x-ui.field>
            <x-ui.button type="submit" size="lg" block trail="arrow">{{ __('auth.reset_send') }}</x-ui.button>
        </form>
        <a href="{{ route('login', ['tab' => 'account']) }}" class="mt-5 block text-center text-sm font-bold text-ink-soft hover:text-ink">{{ __('auth.back_login') }}</a>
    </x-ui.card>
</x-layouts.auth>
