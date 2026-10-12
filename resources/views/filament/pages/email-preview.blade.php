<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-[20rem_1fr]">
        {{-- Controls --}}
        <aside class="space-y-4">
            <x-filament::section :heading="__('admin.mail.template')" icon="heroicon-o-envelope" compact>
                <div class="space-y-1.5">
                    @foreach(\App\Filament\Pages\EmailPreview::TEMPLATES as $t)
                        <button type="button" wire:click="$set('template', '{{ $t }}')"
                            @class(['flex w-full items-start gap-3 rounded-xl px-3 py-2.5 text-start transition', 'bg-primary-600 text-white shadow-md' => $template === $t, 'hover:bg-gray-50 dark:hover:bg-white/5' => $template !== $t])>
                            <x-filament::icon :icon="['invoice' => 'heroicon-o-document-text', 'payment_failed' => 'heroicon-o-exclamation-circle', 'sale_alert' => 'heroicon-o-banknotes', 'login_alert' => 'heroicon-o-lock-closed'][$t]" class="mt-0.5 size-5 shrink-0" />
                            <span>
                                <span class="block text-sm font-bold">{{ __('admin.mail.t.'.$t) }}</span>
                                <span @class(['block text-xs', 'text-white/75' => $template === $t, 'text-gray-500' => $template !== $t])>{{ __('admin.mail.t_hint.'.$t) }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </x-filament::section>

            @if($template !== 'login_alert')
                <x-filament::section :heading="__('admin.mail.sample_order')" icon="heroicon-o-ticket" compact>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="orderId">
                            <option value="">{{ __('admin.mail.latest_order') }}</option>
                            @foreach($orders as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    @if($when)<p class="mt-2 text-xs text-gray-500">{{ $when }}</p>@endif
                </x-filament::section>
            @endif

            <x-filament::section compact>
                <div class="space-y-3">
                    <div>
                        <p class="mb-1.5 text-xs font-bold text-gray-500">{{ __('admin.mail.language') }}</p>
                        <x-filament::tabs class="!p-0 !shadow-none !ring-0">
                            <x-filament::tabs.item :active="$lang === 'ar'" wire:click="$set('lang', 'ar')">العربية</x-filament::tabs.item>
                            <x-filament::tabs.item :active="$lang === 'en'" wire:click="$set('lang', 'en')">English</x-filament::tabs.item>
                        </x-filament::tabs>
                    </div>
                    <div>
                        <p class="mb-1.5 text-xs font-bold text-gray-500">{{ __('admin.mail.width') }}</p>
                        <x-filament::tabs class="!p-0 !shadow-none !ring-0">
                            <x-filament::tabs.item :active="$width === 'desktop'" wire:click="$set('width', 'desktop')" icon="heroicon-m-computer-desktop">{{ __('admin.vis.kpi.desktop') }}</x-filament::tabs.item>
                            <x-filament::tabs.item :active="$width === 'mobile'" wire:click="$set('width', 'mobile')" icon="heroicon-m-device-phone-mobile">{{ __('admin.vis.kpi.mobile') }}</x-filament::tabs.item>
                        </x-filament::tabs>
                    </div>
                </div>
            </x-filament::section>
        </aside>

        {{-- Preview --}}
        <div class="bezel">
            <div class="overflow-hidden rounded-[calc(1.75rem-0.375rem)] bg-white dark:bg-gray-900">
                <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-3.5 dark:border-white/10">
                    <span class="flex gap-1.5"><span class="size-3 rounded-full bg-danger-400"></span><span class="size-3 rounded-full bg-warning-400"></span><span class="size-3 rounded-full bg-success-400"></span></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">{{ __('admin.mail.subject') }}</p>
                        <p class="truncate text-sm font-bold text-gray-950 dark:text-white" @if($lang === 'en') dir="ltr" @endif>{{ $subject ?? '—' }}</p>
                    </div>
                </div>
                <div class="bg-[#f4efe9] p-4 sm:p-8 dark:bg-gray-950" wire:loading.class="opacity-50">
                    @if($error)
                        <p class="rounded-xl bg-danger-50 p-4 text-sm font-bold text-danger-700">{{ __('admin.mail.render_failed') }} — {{ $error }}</p>
                    @elseif(! $html)
                        <p class="py-24 text-center text-sm text-gray-500">{{ __('admin.mail.no_order') }}</p>
                    @else
                        <iframe wire:key="frame-{{ $template }}-{{ $lang }}-{{ $orderId }}" title="{{ __('admin.mail.title') }}" sandbox
                            srcdoc="{{ $html }}" @class(['mx-auto block h-[70vh] w-full rounded-xl bg-white shadow-xl transition-all duration-500 ease-[cubic-bezier(0.32,0.72,0,1)]', 'max-w-[390px]' => $width === 'mobile', 'max-w-[760px]' => $width !== 'mobile'])></iframe>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
