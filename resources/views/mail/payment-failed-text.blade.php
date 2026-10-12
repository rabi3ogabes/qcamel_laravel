{{ __('mail.failed_title') }} — {{ $order->booking_reference }}

{{ $order->event?->title }} — {{ $eventDate }}
{{ __('mail.amount') }}: {{ \App\Support\Formats::amount($order->total_amount) }} {{ __('common.qar_short') }}
@if($sourceLabel){{ __('mail.failure_source', ['source' => $sourceLabel]) }}
@endif
@if($reason){{ $reason }}
@endif

{{ __('mail.retry_booking') }}: {{ $retryUrl }}
{{ __('mail.track_booking') }} {{ $invoiceUrl }}
