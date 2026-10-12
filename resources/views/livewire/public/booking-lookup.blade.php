<div class="mx-auto max-w-md px-4 pb-6 pt-12 sm:pt-16">
    <header class="animate-rise text-center">
        <span class="mx-auto grid size-16 place-items-center rounded-full bg-white text-terracotta shadow-soft ring-1 ring-sand/60"><x-glyph name="search" size="28" stroke="1.3" /></span>
        <h1 class="mt-6 text-3xl font-extrabold tracking-tight text-bark">{{ __('public.lookup.title') }}</h1>
        <p class="mt-2 leading-relaxed text-ink-soft">{{ __('public.lookup.subtitle') }}</p>
    </header>

    <x-ui.card class="reveal mt-8" pad="p-6 sm:p-7">
        <form wire:submit="find" class="space-y-5">
            <x-ui.field :label="__('public.lookup.reference')" for="lk-ref" required>
                <x-ui.input id="lk-ref" wire:model="reference" icon="ticket" dir="ltr" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="60" :placeholder="__('public.lookup.reference_ph')" required />
            </x-ui.field>
            <x-ui.field :label="__('public.lookup.last4')" for="lk-4" required>
                <x-ui.input id="lk-4" wire:model="last4" icon="phone" dir="ltr" inputmode="numeric" maxlength="4" pattern="[0-9٠-٩]{4}" placeholder="••••" required />
            </x-ui.field>
            @if($error)
                <p class="flex items-start gap-2 rounded-2xl bg-danger/[0.06] p-3 text-sm font-medium text-danger ring-1 ring-danger/20" role="alert"><x-glyph name="alert" size="18" class="mt-px" />{{ $error }}</p>
            @endif
            <x-ui.button type="submit" variant="primary" size="lg" block trail="arrow" loading="find">{{ __('public.lookup.submit') }}</x-ui.button>
        </form>
    </x-ui.card>
</div>
