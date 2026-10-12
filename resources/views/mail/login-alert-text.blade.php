{{ __('mail.login_title') }}
{{ __('mail.login_subtitle', ['minutes' => $blockedMinutes, 'attempts' => $attempts]) }}

{{ __('mail.identifier') }}: {{ $identifier }}
{{ __('mail.login_kind') }}: {{ $kindLabel }}
{{ __('mail.blocked_at') }}: {{ $occurredAt }}
{{ __('mail.ip') }}: {{ $ip ?? '-' }}
