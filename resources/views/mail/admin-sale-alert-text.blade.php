{{ __('mail.sale_title') }}
{{ __('mail.sale_subtitle', ['source' => $sourceLabel, 'qty' => $order->quantity, 'total' => \App\Support\Formats::amount($order->total_amount)]) }}

{{ __('mail.booking_ref') }}: {{ $order->booking_reference }}
{{ __('mail.customer_name') }}: {{ $order->customer?->name }}
{{ __('mail.event') }}: {{ $order->event?->title }}
{{ __('mail.sold_at') }}: {{ $soldAt }}
{{ __('mail.view_invoice') }}: {{ $invoiceUrl }}
