<x-mail-layout :preview="$preview" :title="__('mail.login_label')" :footer="__('mail.auto_footer')" font="Tahoma, Arial, sans-serif">
    <h1 style="margin:0 0 6px 0;font-size:22px;color:#8E2C2C;">{{ __('mail.login_title') }}</h1>
    <p style="margin:0 0 20px 0;color:#6B6257;font-size:14px;">{{ __('mail.login_subtitle', ['minutes' => $blockedMinutes, 'attempts' => $attempts]) }}</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #EDE6DA;border-radius:14px;overflow:hidden;">
        @include('mail.partials.row', ['label' => __('mail.identifier'), 'value' => $identifier, 'ltr' => true])
        @include('mail.partials.row', ['label' => __('mail.login_kind'), 'value' => $kindLabel])
        @include('mail.partials.row', ['label' => __('mail.attempts'), 'value' => $attempts])
        @include('mail.partials.row', ['label' => __('mail.blocked_at'), 'value' => $occurredAt])
        @include('mail.partials.row', ['label' => __('mail.ip'), 'value' => $ip ?? '-', 'ltr' => true])
        @include('mail.partials.row', ['label' => __('mail.device'), 'value' => \Illuminate\Support\Str::limit($userAgent ?? '-', 160), 'ltr' => true])
    </table>
</x-mail-layout>
