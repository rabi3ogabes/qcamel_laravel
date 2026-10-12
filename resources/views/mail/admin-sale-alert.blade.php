<x-mail-layout :logo="$logo" :preview="$preview" :title="__('mail.sale_label')" :footer="__('mail.auto_footer')" font="Tahoma, Arial, sans-serif">
    <h1 style="margin:0 0 6px 0;font-size:22px;color:#14110E;">{{ __('mail.sale_title') }}</h1>
    <p style="margin:0 0 20px 0;color:#6B6257;font-size:14px;">{{ __('mail.sale_subtitle', ['source' => $sourceLabel, 'qty' => $order->quantity, 'total' => \App\Support\Formats::amount($order->total_amount)]) }}</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #EDE6DA;border-radius:14px;overflow:hidden;">
        @include('mail.partials.row', ['label' => __('mail.booking_ref'), 'value' => $order->booking_reference, 'ltr' => true])
        @include('mail.partials.row', ['label' => __('mail.customer_name'), 'value' => $order->customer?->name ?? '-'])
        @include('mail.partials.row', ['label' => __('mail.customer_phone'), 'value' => $order->customer?->fullPhone() ?? '-', 'ltr' => true])
        @include('mail.partials.row', ['label' => __('mail.customer_email'), 'value' => $order->customer?->email ?? '-', 'ltr' => true])
        @include('mail.partials.row', ['label' => __('mail.event'), 'value' => $order->event?->title ?? '-'])
        @include('mail.partials.row', ['label' => __('mail.event_date'), 'value' => $eventDate])
        @include('mail.partials.row', ['label' => __('mail.ticket_count'), 'value' => $order->quantity])
        @include('mail.partials.row', ['label' => __('mail.amount'), 'value' => \App\Support\Formats::amount($order->total_amount).' '.__('common.qar_short')])
        @include('mail.partials.row', ['label' => __('mail.payment_method'), 'value' => $order->payment_method->value === 'cash_pos' ? __('mail.method_cash_admin') : __('mail.method_sadad')])
        @include('mail.partials.row', ['label' => __('mail.txn_number'), 'value' => $order->payment_id ?? '-', 'ltr' => true])
        @if($order->posUser)
            @include('mail.partials.row', ['label' => __('mail.cashier'), 'value' => $order->posUser->label()])
        @endif
        @include('mail.partials.row', ['label' => __('mail.sold_at'), 'value' => $soldAt])
        @if($holders)
            @include('mail.partials.row', ['label' => __('mail.holders'), 'value' => $holders])
        @endif
    </table>
    <div style="text-align:center;margin:24px 0 0 0;">
        <a href="{{ $invoiceUrl }}" style="display:inline-block;background:#B08B4F;color:#FFFFFF;text-decoration:none;font-weight:700;font-size:14px;padding:12px 28px;border-radius:999px;">{{ __('mail.view_invoice') }}</a>
    </div>
</x-mail-layout>
