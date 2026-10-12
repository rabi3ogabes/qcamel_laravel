<x-mail-layout :logo="$logo" :preview="$preview" :title="__('mail.failed_label')" :footer="__('mail.failed_footer')" font="Arial, sans-serif">
    <p style="margin:0 0 6px 0;color:#6B6257;font-size:12px;letter-spacing:2px;text-align:center;">{{ __('mail.payment_state') }}</p>
    <h1 style="margin:0 0 4px 0;font-size:22px;color:#14110E;text-align:center;">{{ $order->event?->title ?? '-' }}</h1>
    <p style="margin:0 0 20px 0;color:#6B6257;font-size:14px;text-align:center;">{{ $eventDate }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #B3261E;border-radius:14px;background:#FDF3F2;margin:0 0 20px 0;">
        <tr>
            <td style="padding:18px 20px;">
                <div style="color:#B3261E;font-weight:800;font-size:16px;margin-bottom:6px;">{{ __('mail.failed_title') }}</div>
                <div style="font-size:22px;font-weight:800;color:#14110E;" dir="ltr">{{ \App\Support\Formats::amount($order->total_amount) }} {{ __('common.qar_short') }}</div>
                @if($sourceLabel)
                    <div style="margin-top:8px;color:#B3261E;font-size:13px;">{{ __('mail.failure_source', ['source' => $sourceLabel]) }}</div>
                @endif
                @if($reason)
                    <div style="margin-top:6px;color:#6B6257;font-size:13px;">{{ $reason }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #EDE6DA;border-radius:14px;overflow:hidden;">
        @include('mail.partials.row', ['label' => __('mail.booking_ref'), 'value' => $order->booking_reference, 'ltr' => true])
        @include('mail.partials.row', ['label' => __('mail.name'), 'value' => $order->customer?->name ?? '-'])
        @include('mail.partials.row', ['label' => __('mail.phone'), 'value' => $order->customer?->fullPhone() ?? '-', 'ltr' => true])
        @include('mail.partials.row', ['label' => __('mail.ticket_count'), 'value' => $order->quantity])
        @if($order->payment_id)
            @include('mail.partials.row', ['label' => __('mail.sadad_txn'), 'value' => $order->payment_id, 'ltr' => true])
        @endif
    </table>

    <div style="text-align:center;margin:26px 0 8px 0;">
        <a href="{{ $retryUrl }}" style="display:inline-block;background:#14110E;color:#FFFFFF;text-decoration:none;font-weight:800;font-size:15px;padding:13px 30px;border-radius:999px;">{{ __('mail.retry_booking') }}</a>
        <p style="margin:10px 0 0 0;color:#6B6257;font-size:12px;">{{ __('mail.retry_hint') }}</p>
    </div>
    <p style="margin:18px 0 0 0;color:#6B6257;font-size:13px;text-align:center;">{{ __('mail.track_booking') }} <a href="{{ $invoiceUrl }}" style="color:#9C1638;" dir="ltr">{{ $invoiceUrl }}</a></p>
</x-mail-layout>
