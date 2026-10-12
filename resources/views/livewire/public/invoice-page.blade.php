<div @if($order) wire:poll.5s.visible @endif class="mx-auto max-w-3xl px-4 pb-6 pt-8 sm:px-6 sm:pt-12">
    @if(! $order)
        <x-ui.card class="animate-rise">
            <x-ui.empty icon="search-x" :title="__('public.invoice.not_found')" :hint="__('public.invoice.not_found_hint')">
                <x-ui.button variant="secondary" :href="route('home')" icon="home">{{ __('public.invoice.home') }}</x-ui.button>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="animate-rise">
            <p class="mb-4 flex items-center justify-center gap-2 text-xs font-bold text-success">
                <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-success/60"></span><span class="relative inline-flex size-2 rounded-full bg-success"></span></span>
                {{ __('public.invoice.live') }}
            </p>

            <x-public.invoice-card :order="$order" id="invoice-card" />

            <div class="mt-6 grid gap-3 sm:grid-cols-2" x-data="{ saving: false }">
                <x-ui.button variant="primary" size="lg" icon="download" :href="route('invoice.pdf', $order->booking_reference)">{{ __('public.invoice.pdf') }}</x-ui.button>
                <x-ui.button variant="secondary" size="lg" icon="image" x-bind:disabled="saving"
                    x-on:click="saving = true; window.qc.saveImage(document.getElementById('invoice-card'), 'invoice-{{ $order->booking_reference }}.png').finally(() => saving = false)">
                    <span x-show="!saving">{{ __('public.invoice.png') }}</span>
                    <span x-show="saving" x-cloak><x-ui.spinner size="18" /></span>
                </x-ui.button>
                <x-ui.button variant="ghost" size="lg" icon="home" :href="route('home')" class="sm:col-span-2">{{ __('public.invoice.home') }}</x-ui.button>
            </div>
        </div>
    @endif
</div>
