<x-filament-panels::page>
    @if($plainToken)
        <div class="bezel">
            <div class="space-y-3 rounded-[calc(1.75rem-0.375rem)] bg-success-50 p-5 ring-1 ring-success-200 dark:bg-success-500/10 dark:ring-success-500/30">
                <div class="flex items-start gap-3">
                    <x-filament::icon icon="heroicon-o-check-badge" class="size-6 shrink-0 text-success-600" />
                    <div>
                        <p class="font-extrabold text-success-800 dark:text-success-300">{{ __('admin.ai.copy_now') }}</p>
                        <p class="text-sm text-success-700 dark:text-success-400">{{ __('admin.ai.copy_now_hint') }}</p>
                    </div>
                </div>
                <div x-data="{ copied: false }" class="flex items-center gap-2">
                    <code dir="ltr" class="block flex-1 overflow-x-auto rounded-xl bg-white px-4 py-3 font-mono text-sm text-gray-950 ring-1 ring-success-200 dark:bg-gray-900 dark:text-white">{{ $plainToken }}</code>
                    <x-filament::button icon="heroicon-m-clipboard-document" x-on:click="navigator.clipboard.writeText(@js($plainToken)); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-text="copied ? @js(__('admin.ai.copied')) : @js(__('admin.ai.copy'))"></span>
                    </x-filament::button>
                </div>
                <x-filament::link tag="button" wire:click="dismissToken" color="gray" size="sm">{{ __('admin.ai.done') }}</x-filament::link>
            </div>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_24rem]">
        <x-filament::section :heading="__('admin.ai.tokens')" icon="heroicon-o-key">
            @if($tokens->isEmpty())
                <div class="flex flex-col items-center gap-2 py-10 text-center">
                    <span class="grid size-12 place-items-center rounded-2xl bg-gold-400/15 text-gold-600"><x-filament::icon icon="heroicon-o-sparkles" class="size-6" /></span>
                    <p class="font-bold">{{ __('admin.ai.empty') }}</p>
                    <p class="max-w-sm text-sm text-gray-500">{{ __('admin.ai.empty_hint') }}</p>
                </div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach($tokens as $t)
                        <li wire:key="tok-{{ $t['id'] }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div>
                                <p class="font-bold">{{ $t['name'] }}
                                    @if($t['expired'])<x-filament::badge color="gray" size="sm" class="ms-1 inline-flex">{{ __('admin.ai.expired') }}</x-filament::badge>@endif
                                </p>
                                <p class="text-xs text-gray-500">{{ __('admin.ai.created_at', ['d' => $t['created']]) }} · {{ $t['used'] ? __('admin.ai.used', ['d' => $t['used']]) : __('admin.ai.never_used') }} · {{ $t['expires'] ? __('admin.ai.expires', ['d' => $t['expires']]) : '' }}</p>
                            </div>
                            <x-filament::button color="danger" outlined size="sm" icon="heroicon-m-no-symbol"
                                wire:click="revoke({{ $t['id'] }})" wire:confirm="{{ __('admin.ai.revoke_confirm') }}">{{ __('admin.ai.revoke') }}</x-filament::button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('admin.ai.connect')" icon="heroicon-o-link">
            <div class="space-y-4 text-sm">
                <div>
                    <p class="mb-1 text-xs font-bold text-gray-500">{{ __('admin.ai.endpoint') }}</p>
                    <code dir="ltr" class="block rounded-lg bg-gray-50 px-3 py-2 font-mono text-xs dark:bg-white/5">POST {{ $endpoint }}</code>
                </div>
                <div>
                    <p class="mb-1 text-xs font-bold text-gray-500">{{ __('admin.ai.header') }}</p>
                    <code dir="ltr" class="block rounded-lg bg-gray-50 px-3 py-2 font-mono text-xs dark:bg-white/5">Authorization: Bearer &lt;token&gt;</code>
                </div>
                <div>
                    <p class="mb-1 text-xs font-bold text-gray-500">{{ __('admin.ai.tools') }}</p>
                    <ul class="space-y-1 text-xs">
                        @foreach(['list_events', 'event_sales_summary', 'find_booking', 'search_customers', 'list_payment_reviews'] as $tool)
                            <li class="flex gap-2"><code dir="ltr" class="font-mono font-bold text-primary-600">{{ $tool }}</code><span class="text-gray-500">{{ __('admin.ai.tool.'.$tool) }}</span></li>
                        @endforeach
                    </ul>
                </div>
                <p class="rounded-xl bg-gold-50 p-3 text-xs leading-relaxed text-gold-800 ring-1 ring-gold-200 dark:bg-gold-400/10 dark:text-gold-300">{{ __('admin.ai.safety') }}</p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
