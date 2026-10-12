@php
    $icon = ['ok' => ['✓', 'text-success-600 bg-success-50 dark:bg-success-500/10'], 'warn' => ['!', 'text-warning-600 bg-warning-50 dark:bg-warning-500/10'], 'error' => ['✕', 'text-danger-600 bg-danger-50 dark:bg-danger-500/10']];
@endphp
<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        <span @class(['rounded-full px-3 py-1 text-sm font-bold', 'bg-success-100 text-success-700' => $r['ready'], 'bg-danger-100 text-danger-700' => ! $r['ready']])>
            {{ $r['ready'] ? __('admin.set.ready') : __('admin.set.not_ready') }}
        </span>
        @if($r['environment'])
            <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-bold text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $r['environment'] === 'sandbox' ? __('admin.set.env_sandbox') : __('admin.set.env_live') }}</span>
        @endif
    </div>
    <ul class="space-y-2">
        @foreach($r['checks'] as $c)
            <li class="flex items-start gap-3 rounded-xl bg-gray-50 p-3 text-sm dark:bg-white/5">
                <span class="grid size-6 shrink-0 place-items-center rounded-full text-xs font-extrabold {{ $icon[$c['status']][1] ?? '' }}">{{ $icon[$c['status']][0] ?? '?' }}</span>
                <div><p class="font-bold">{{ __('admin.set.chk.'.$c['id']) }}</p><p class="text-gray-600 dark:text-gray-300">{{ $c['message'] }}</p></div>
            </li>
        @endforeach
    </ul>
    <div class="grid gap-3 md:grid-cols-2">
        @foreach(['webhookUrl' => __('admin.set.register_webhook'), 'callbackUrl' => __('admin.set.callback_url')] as $k => $label)
            <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                <p class="text-xs font-bold text-gray-500">{{ $label }}</p>
                <div class="mt-1 flex items-center gap-2" x-data="{ copied: false }">
                    <code class="flex-1 truncate text-xs" dir="ltr">{{ $r[$k] }}</code>
                    <button type="button" class="text-xs font-bold text-primary-600" x-on:click="navigator.clipboard.writeText(@js($r[$k])); copied = true; setTimeout(() => copied = false, 1500)">
                        <span x-show="!copied">{{ __('common.copy') }}</span><span x-show="copied">{{ __('common.copied') }}</span>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>
