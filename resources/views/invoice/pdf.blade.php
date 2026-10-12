@php
    /** @var \App\Models\Order $order */
    $paid = $order->payment_status->value === 'confirmed';
    $c = $order->customer;
    $brandName = $festival ?: __('common.app_name');
    $seasonText = $season ?: (string) now('Asia/Qatar')->year;
    $isAr = app()->getLocale() !== 'en';
    $phone = $c ? ($pii ? \App\Support\PhoneNumber::international($c->phone, $c->country_code) : \App\Support\PhoneNumber::mask($c->phone, $c->country_code)) : '-';
@endphp
<html dir="{{ $isAr ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 10mm; }
    body { font-family: dejavusans, sans-serif; font-size: 11pt; color: #4a3728; direction: {{ $isAr ? 'rtl' : 'ltr' }}; }
    .wrap { border: 1px solid #e7e2dc; border-radius: 12px; padding: 16px 20px; }
    .bar { height: 5px; background: #9C1638; margin: -16px -20px 14px -20px; }
    .head { text-align: center; border-bottom: 2px solid #c27444; padding-bottom: 12px; }
    .head h1 { color: #a4552b; font-size: 20pt; margin: 6px 0 2px; }
    .muted { color: #6b4b2a; font-size: 10pt; }
    h2 { color: #a4552b; font-size: 12.5pt; border-bottom: 1px solid #d8b59f; padding-bottom: 4px; margin: 18px 0 8px; }
    table.kv td { padding: 4px 0; font-size: 10.5pt; }
    table.kv td.k { color: #8b6f47; width: 32%; }
    table.grid { width: 100%; border-collapse: collapse; background: #fdfaf8; }
    table.grid th { background: #f4ede6; color: #8b4a2a; padding: 7px; font-size: 10pt; border: 1px solid #eadfd4; }
    table.grid td { padding: 7px; text-align: center; border: 1px solid #eadfd4; font-size: 10.5pt; }
    .pill-paid { background: #e8f7e6; color: #2c7a3f; padding: 3px 10px; border-radius: 10px; font-weight: bold; }
    .pill-wait { background: #fff4e5; color: #b47400; padding: 3px 10px; border-radius: 10px; font-weight: bold; }
    .qr td { text-align: center; padding: 6px; vertical-align: top; }
    .qr .cap { font-size: 9pt; color: #6b4b2a; }
    .used { width: 150px; height: 150px; border: 1px dashed #c4302b; color: #c4302b; font-size: 10pt; text-align: center; }
    .thanks { text-align: center; color: #a4552b; font-weight: bold; margin-top: 18px; }
    .foot { text-align: center; color: #776150; font-size: 8.5pt; margin-top: 8px; }
</style>
</head>
<body>
<div class="wrap">
    <div class="bar"></div>
    <div class="head">
        @if($logo)<img src="{{ $logo }}" style="width: 150px;" alt="">@endif
        <h1>{{ __('public.invoice.pdf_title') }}</h1>
        <div class="muted">{{ __('public.invoice.pdf_number', ['ref' => $order->booking_reference]) }}</div>
    </div>

    <h2>{{ __('public.invoice.pdf_customer') }}</h2>
    <table class="kv" width="100%">
        <tr><td class="k">{{ __('public.invoice.name') }}</td><td>{{ $c?->name ?? '-' }}</td></tr>
        <tr><td class="k">{{ __('public.invoice.phone') }}</td><td dir="ltr">{{ $phone }}</td></tr>
        <tr><td class="k">{{ __('public.invoice.nationality') }}</td><td>{{ \App\Support\Countries::label($c?->nationality) }}</td></tr>
    </table>

    <h2>{{ __('public.invoice.pdf_payment') }}</h2>
    <table class="kv" width="100%">
        <tr><td class="k">{{ __('public.invoice.method') }}</td><td>{{ $order->isPos() || $order->isCashAtVenue() ? __('public.invoice.method_pos') : __('public.invoice.method_sadad') }}</td></tr>
        <tr><td class="k">{{ __('public.invoice.sadad_no') }}</td><td dir="ltr">{{ $order->payment_id ?: '-' }}</td></tr>
        <tr><td class="k">{{ __('public.invoice.paid_at') }}</td><td>{{ $order->confirmed_at ? \App\Support\Formats::longDateTime($order->confirmed_at) : '-' }}</td></tr>
    </table>

    <h2>{{ __('public.invoice.pdf_tickets') }}</h2>
    <table class="grid">
        <tr>
            <th>{{ __('public.invoice.type') }}</th><th>{{ __('public.result.quantity') }}</th><th>{{ __('public.invoice.pdf_day') }}</th>
            <th>{{ __('public.invoice.total') }}</th><th>{{ __('public.invoice.pdf_status') }}</th>
        </tr>
        @forelse($order->items as $item)
            <tr>
                <td>{{ __('enums.ticket_kind.'.$item->type->value) }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ $order->event?->title }}</td>
                <td>{{ \App\Support\Formats::money(\App\Support\Money::of((string) $item->unit_price)->multiply((int) $item->quantity)->toDecimal(), short: true) }}</td>
                <td><span class="{{ $paid ? 'pill-paid' : 'pill-wait' }}">{{ $paid ? __('public.invoice.pdf_paid') : __('public.invoice.pending') }}</span></td>
            </tr>
        @empty
            <tr>
                <td>{{ __('enums.ticket_kind.'.$order->ticket_type->value) }}</td>
                <td>{{ $order->quantity }}</td>
                <td>{{ $order->event?->title }}</td>
                <td>{{ \App\Support\Formats::money($order->total_amount, short: true) }}</td>
                <td><span class="{{ $paid ? 'pill-paid' : 'pill-wait' }}">{{ $paid ? __('public.invoice.pdf_paid') : __('public.invoice.pending') }}</span></td>
            </tr>
        @endforelse
        <tr>
            <td colspan="3" style="text-align: {{ $isAr ? 'left' : 'right' }}; font-weight: bold;">{{ __('public.invoice.total') }}</td>
            <td colspan="2" style="font-weight: bold; color: #9C1638;">{{ \App\Support\Formats::money($order->total_amount, short: true) }}</td>
        </tr>
    </table>

    @php $holders = $order->holders->filter(fn ($h) => filled($h->qr_code))->values(); @endphp
    @if($holders->isNotEmpty())
        <h2>{{ __('public.invoice.qr_title') }}</h2>
        <table class="qr" width="100%">
            @foreach($holders->chunk(3) as $row)
                <tr>
                    @foreach($row as $h)
                        <td width="33%">
                            @if(isset($qrs[$h->id]))
                                <img src="{{ $qrs[$h->id] }}" width="150" height="150" alt="">
                            @else
                                <table class="used" align="center"><tr><td>{{ $h->is_present ? __('public.invoice.used') : __('public.invoice.pending') }}</td></tr></table>
                            @endif
                            <div class="cap">{{ __('enums.ticket_kind.'.$h->ticket_type->value) }} · {{ $h->name }}</div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif

    <div class="thanks">{{ __('public.invoice.pdf_thanks', ['brand' => $brandName, 'season' => $seasonText]) }}</div>
    <div class="foot">{{ __('public.invoice.pdf_rights', ['brand' => $brandName, 'season' => $seasonText]) }}</div>
</div>
</body>
</html>
