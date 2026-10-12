<x-mail-layout :logo="$logo" :preview="$preview" :title="__('mail.invoice_label')" :footer="__('mail.invoice_footer')">
    <p style="margin:0 0 6px 0;color:#C9A227;font-size:12px;letter-spacing:3px;font-weight:700;text-transform:uppercase;text-align:center;">{{ __('mail.invoice_label') }}</p>
    <h1 style="margin:0 0 4px 0;font-size:24px;line-height:1.35;color:#14110E;text-align:center;">{{ $order->event?->title ?? '-' }}</h1>
    <p style="margin:0 0 22px 0;color:#6B6257;font-size:14px;text-align:center;">{{ $eventDate }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #EDE6DA;border-top:3px solid #C9A227;border-radius:14px;background:#FCFAF6;margin:0 0 20px 0;">
        <tr>
            <td style="padding:20px;text-align:center;">
                <div style="color:#6B6257;font-size:13px;margin-bottom:6px;">{{ __('mail.amount_paid') }}</div>
                <div style="font-size:30px;font-weight:800;color:#14110E;" dir="ltr">{{ \App\Support\Formats::amount($order->total_amount) }} {{ __('common.qar_short') }}</div>
                <div style="display:inline-block;margin-top:10px;padding:5px 14px;border-radius:999px;background:#E8F5ED;color:#1F7A44;font-size:12px;font-weight:700;">{{ __('mail.payment_confirmed_pill') }}</div>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #EDE6DA;border-radius:14px;overflow:hidden;">
        @include('mail.partials.row', ['label' => __('mail.booking_ref'), 'value' => $order->booking_reference, 'ltr' => true])
        @include('mail.partials.row', ['label' => __('mail.name'), 'value' => $order->customer?->name ?? '-'])
        @include('mail.partials.row', ['label' => __('mail.phone'), 'value' => $order->customer?->fullPhone() ?? '-', 'ltr' => true])
        @include('mail.partials.row', ['label' => __('mail.ticket_type'), 'value' => $order->ticket_type->label()])
        @include('mail.partials.row', ['label' => __('mail.ticket_count'), 'value' => $order->quantity])
        @include('mail.partials.row', ['label' => __('mail.payment_method'), 'value' => $order->payment_method->value === 'cash_pos' ? __('mail.method_cash') : __('mail.method_sadad')])
        @if($order->payment_id)
            @include('mail.partials.row', ['label' => $order->payment_method->value === 'cash_pos' ? __('mail.txn_number') : __('mail.sadad_txn'), 'value' => $order->payment_id, 'ltr' => true])
        @endif
        @if($paidAt)
            @include('mail.partials.row', ['label' => __('mail.paid_at'), 'value' => $paidAt])
        @endif
    </table>

    <div style="text-align:center;margin:26px 0 8px 0;">
        <a href="{{ $invoiceUrl }}" style="display:inline-block;background:#C9A227;color:#14110E;text-decoration:none;font-weight:800;font-size:15px;padding:13px 30px;border-radius:999px;">{{ __('mail.view_invoice_online') }}</a>
        <p style="margin:10px 0 0 0;color:#6B6257;font-size:12px;">{{ __('mail.view_invoice_hint') }}</p>
    </div>
    <p style="margin:20px 0 0 0;color:#6B6257;font-size:13px;line-height:1.8;text-align:center;">{{ __('mail.invoice_note') }}</p>
</x-mail-layout>
