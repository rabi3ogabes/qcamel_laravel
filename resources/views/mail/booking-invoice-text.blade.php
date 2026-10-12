{{ __('mail.invoice_label') }} — {{ $order->booking_reference }}

{{ $order->event?->title }} — {{ $eventDate }}
{{ __('mail.amount_paid') }}: {{ \App\Support\Formats::amount($order->total_amount) }} {{ __('common.qar_short') }}
{{ __('mail.name') }}: {{ $order->customer?->name }}
{{ __('mail.ticket_count') }}: {{ $order->quantity }}

{{ __('mail.view_invoice_online') }}: {{ $invoiceUrl }}

{{ __('mail.invoice_note') }}
{{ __('mail.invoice_footer') }}
