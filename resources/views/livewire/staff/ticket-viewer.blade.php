<x-slot:header>
    <div class="print:hidden">
        <x-staff.header :title="__('ops.viewer.title')" :subtitle="$reference" icon="ticket" status width="max-w-6xl" />
    </div>
</x-slot:header>

<x-slot:head>
    <style>
        @media print {
            @page { size: A5 portrait; margin: 10mm; }
            body { background: #fff !important; }
            body::after { display: none !important; }
            .print-ticket { break-after: page; box-shadow: none !important; }
            .print-ticket:last-child { break-after: auto; }
        }
    </style>
</x-slot:head>

<div wire:poll.15s.visible class="mx-auto max-w-6xl px-3 pb-16 pt-6 sm:px-5"
     x-data x-init="@if($autoPrint && $order) setTimeout(() => window.print(), 700) @endif">
    @if(! $order || $holders->isEmpty())
        <x-ui.card class="mx-auto max-w-lg">
            <x-ui.empty icon="search-x" :title="__('ops.viewer.not_found')" :hint="$reference">
                <x-ui.button variant="secondary" :href="route('staff.hub')" icon="home">{{ __('ops.shell.hub') }}</x-ui.button>
            </x-ui.empty>
        </x-ui.card>
    @else
        {{-- Booking summary (F-QR-017) --}}
        <div class="print:hidden">
            <x-ui.card pad="p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <dl class="grid flex-1 grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                        <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.result.reference') }}</dt><dd class="font-mono font-extrabold text-maroon-600" dir="ltr">{{ $order->booking_reference }}</dd></div>
                        <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.invoice.name') }}</dt><dd class="font-bold">{{ $order->customer?->name }}</dd></div>
                        <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.invoice.event') }}</dt><dd class="font-bold">{{ $order->event?->title }}</dd></div>
                        <div><dt class="text-xs font-bold text-ink-soft">{{ __('public.invoice.count') }}</dt><dd class="font-bold">{{ __('public.select.summary_count', ['n' => $order->holders->count()]) }}</dd></div>
                    </dl>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button icon="receipt" x-on:click="window.print()">{{ __('ops.viewer.print') }} ({{ $holders->count() }})</x-ui.button>
                        <x-ui.button variant="secondary" icon="eye" :href="route('invoice.show', $order->booking_reference)" target="_blank">{{ __('ops.viewer.open_invoice') }}</x-ui.button>
                        @if($isAdmin)
                            <x-ui.button variant="secondary" icon="refresh" wire:click="regenerate" loading="regenerate">{{ __('ops.viewer.regenerate') }}</x-ui.button>
                        @endif
                    </div>
                </div>
                @if($legacy)
                    <p class="mt-4 rounded-2xl bg-gold-50 p-4 text-sm leading-relaxed text-gold-900 ring-1 ring-gold-200">⚠️ {{ app()->getLocale() === 'en' ? 'Old printed tickets that only carry the booking reference must be re-printed: every ticket needs its own QR code.' : 'إذا كانت التذاكر المطبوعة القديمة تحتوي فقط على الرقم المرجعي للحجز، يجب إعادة تحميل وطباعة التذاكر الجديدة التي تحتوي على رموز QR فردية لكل تذكرة.' }}</p>
                @endif
            </x-ui.card>
        </div>

        {{-- Tickets (F-QR-018…021) — screen grid, one page each when printed --}}
        <div class="mt-5 grid gap-5 md:grid-cols-2 lg:grid-cols-3 print:mt-0 print:block">
            @foreach($holders as $i => $h)
                <article wire:key="tv-{{ $h->id }}" class="print-ticket overflow-hidden rounded-[1.75rem] bg-white shadow-elegant ring-1 ring-gold-300/50 print:mx-auto print:max-w-[130mm] print:rounded-2xl">
                    <div class="sadu relative bg-gradient-to-l from-maroon-700 to-maroon-600 px-5 py-4 text-white">
                        <p class="text-xs font-bold tracking-[0.2em] text-gold-300">🎫 {{ app()->getLocale() === 'en' ? 'ADMISSION TICKET' : 'تذكرة دخول' }}</p>
                        <p class="mt-1 truncate text-lg font-extrabold">{{ $order->event?->title }}</p>
                        <p class="text-xs text-white/75">{{ \App\Support\Formats::longDate($order->event?->event_date) }} · {{ $order->event?->location }}</p>
                    </div>
                    <div class="mx-5 ticket-perf-line"></div>
                    <div class="p-5 text-center">
                        <div class="mx-auto flex items-center justify-between gap-2">
                            <x-ui.badge :tone="$h->ticket_type->value">{{ $h->ticket_type->longLabel() }}</x-ui.badge>
                            @if($h->is_present)
                                <x-ui.badge tone="success" icon="check">{{ __('common.present') }}</x-ui.badge>
                            @else
                                <span class="text-xs font-bold text-ink-soft">{{ __('ops.viewer.ticket', ['n' => $i + 1, 'total' => $holders->count()]) }}</span>
                            @endif
                        </div>
                        <button type="button" x-data @click="$dispatch('open-dialog', 'tv-zoom-{{ $h->id }}')" class="mx-auto mt-4 block size-48 rounded-2xl bg-white p-2 ring-2 ring-maroon-600/20 transition hover:scale-[1.02] print:size-[70mm] print:ring-0 [&>svg]:size-full" aria-label="{{ __('public.invoice.zoom') }}">{!! $qrs[$h->id] ?? '' !!}</button>
                        <p class="mt-3 font-mono text-sm font-bold" dir="ltr">{{ $h->code() }}</p>
                        <dl class="mt-4 space-y-1.5 text-start text-sm">
                            <div class="flex justify-between gap-3 border-s-4 border-gold-400 bg-ivory px-3 py-1.5"><dt class="text-ink-soft">{{ __('ops.scanner.holder') }}</dt><dd class="font-bold">{{ $h->name }}</dd></div>
                            <div class="flex justify-between gap-3 border-s-4 border-gold-400 bg-ivory px-3 py-1.5"><dt class="text-ink-soft">{{ __('ops.scanner.phone') }}</dt><dd class="font-mono" dir="ltr">{{ $pii ? $h->displayPhone() : \App\Support\PhoneNumber::mask($h->phone, $h->country_code) }}</dd></div>
                            <div class="flex justify-between gap-3 border-s-4 border-gold-400 bg-ivory px-3 py-1.5"><dt class="text-ink-soft">{{ __('ops.scanner.nationality') }}</dt><dd>{{ \App\Support\Countries::label($h->nationality) }}</dd></div>
                            @if($pii && $h->getAttribute('id_number'))
                                <div class="flex justify-between gap-3 border-s-4 border-gold-400 bg-ivory px-3 py-1.5"><dt class="text-ink-soft">{{ __('ops.scanner.id_number') }}</dt><dd class="font-mono" dir="ltr">{{ $h->getAttribute('id_number') }}</dd></div>
                            @endif
                        </dl>
                        <p @class(['mt-4 rounded-2xl p-3 text-sm font-bold', 'bg-success/10 text-success' => $h->is_present, 'bg-gold-50 text-gold-800' => ! $h->is_present])>
                            {{ $h->is_present ? '✅ '.\App\Support\Formats::longDateTime($h->checked_in_at) : '⏳ '.__('public.invoice.show_at_gate') }}
                        </p>
                        <div class="mt-4 flex flex-wrap justify-center gap-2 print:hidden">
                            <x-ui.button size="sm" variant="secondary" icon="receipt" :href="route('staff.tickets', ['reference' => $reference, 'only' => $h->id, 'print' => 1])" target="_blank">{{ __('ops.viewer.print') }}</x-ui.button>
                            <x-ui.button size="sm" variant="secondary" icon="whatsapp" wire:click="sendOne('{{ $h->id }}')" loading="sendOne('{{ $h->id }}')">{{ __('ops.pos.send_whatsapp') }}</x-ui.button>
                            @if($isAdmin && $h->is_present)
                                <x-ui.button size="sm" variant="danger" icon="refresh" wire:click="undo('{{ $h->id }}')" wire:confirm="{{ __('ops.scanner.undo_title', ['name' => $h->name]) }}">{{ __('ops.scanner.undo') }}</x-ui.button>
                            @endif
                        </div>
                    </div>
                    <x-ui.dialog :name="'tv-zoom-'.$h->id" max-width="sm:max-w-md">
                        <div class="text-center">
                            <p class="font-mono font-bold" dir="ltr">{{ $h->code() }}</p>
                            <div class="mx-auto mt-4 aspect-square w-full max-w-[22rem] rounded-2xl bg-white p-3 ring-4 ring-maroon-600/20 [&>svg]:size-full">{!! $qrs[$h->id] ?? '' !!}</div>
                            <p class="mt-4 text-xl font-extrabold">{{ $h->name }}</p>
                            <p class="text-sm text-ink-soft">{{ $h->ticket_type->longLabel() }}</p>
                        </div>
                    </x-ui.dialog>
                </article>
            @endforeach
        </div>
    @endif
</div>
