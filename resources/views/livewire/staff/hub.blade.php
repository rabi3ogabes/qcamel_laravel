<x-slot:header>
    <x-staff.header :title="__('staff.hub_title')" :subtitle="$actor->displayName()" :back="false" status width="max-w-4xl" />
</x-slot:header>

<div>
    <div class="mx-auto max-w-4xl px-4 pb-16 pt-10 sm:px-6 sm:pt-14">
        <section class="animate-rise text-center">
            <x-ui.eyebrow>{{ __('staff.hub_eyebrow') }}</x-ui.eyebrow>
            <h2 class="mt-5 text-4xl font-extrabold tracking-tight text-bark sm:text-5xl">{{ __('staff.hub_welcome') }}</h2>
            <p class="mt-2 text-ink-soft">{{ __('staff.hub_sub') }}</p>
            <x-ui.badge :tone="$badge[1]" class="mt-5" :icon="$actor->isKiosk() ? 'key' : 'shield'">{{ __('staff.'.$badge[0]) }}</x-ui.badge>
        </section>

        @if($mySales)
            <section class="mt-10">
                <div class="bezel">
                    <div class="bezel-core flex flex-wrap items-center justify-between gap-4 px-6 py-5">
                        <div class="flex items-center gap-3">
                            <span class="grid size-11 place-items-center rounded-2xl bg-gold-100 text-gold-700"><x-glyph name="chart" size="20" /></span>
                            <div>
                                <p class="text-sm font-bold text-ink-soft">{{ __('staff.my_sales_today') }}</p>
                                <p class="text-lg font-extrabold text-ink">{{ $mySales['orders'] ? __('staff.my_sales_orders', ['n' => $mySales['orders']]) : __('staff.my_sales_none') }}</p>
                            </div>
                        </div>
                        @if($mySales['orders'])
                            <div class="flex items-center gap-6 text-end">
                                <div><p class="text-xs text-ink-soft">{{ __('ops.pos.ticket_count') }}</p><p class="text-2xl font-extrabold tabular-nums">{{ $mySales['tickets'] }}</p></div>
                                <div><p class="text-xs text-ink-soft">{{ __('ops.pos.grand_total') }}</p><p class="text-2xl font-extrabold tabular-nums text-maroon-600">{{ \App\Support\Formats::money($mySales['amount'], short: true) }}</p></div>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        <nav class="mt-10 grid gap-4 sm:grid-cols-2" aria-label="{{ __('staff.hub_eyebrow') }}">
            @foreach($links as $i => $l)
                <a href="{{ route($l['route']) }}" class="group block">
                    <div class="bezel transition-transform duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:-translate-y-1">
                        <div class="bezel-core relative flex items-center gap-4 overflow-hidden p-5 transition-shadow duration-500 group-hover:shadow-float">
                            <span class="absolute inset-y-4 start-0 w-1 rounded-full bg-gradient-to-b {{ $l['tone'] }}"></span>
                            <span class="sadu relative grid size-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br {{ $l['tone'] }} text-white shadow-soft">
                                <x-glyph :name="$l['icon']" size="26" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-lg font-extrabold text-ink">{{ __('staff.'.$l['label']) }}</span>
                                <span class="block truncate text-sm text-ink-soft">{{ __('staff.'.$l['label'].'_hint') }}</span>
                            </span>
                            <span class="grid size-10 place-items-center rounded-full bg-ivory text-ink ring-1 ring-bark/10 transition duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:bg-maroon-600 group-hover:text-white ltr:group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5">
                                <x-glyph name="arrow" size="17" flip />
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </nav>

        @if($canAdmin)
            <div class="mt-10 text-center">
                <x-ui.button variant="ghost" href="/admin" icon="chart">{{ __('staff.dashboard_link') }}</x-ui.button>
            </div>
        @endif
    </div>
</div>
