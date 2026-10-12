<div wire:poll.30s.visible>
    @if($data)
        @php
            $style = [
                'critical' => ['ring-danger-500/40 bg-danger-50 text-danger-800 dark:bg-danger-500/10 dark:text-danger-300', 'heroicon-s-x-circle', 'bg-danger-600 text-white'],
                'warning' => ['ring-orange-500/40 bg-orange-50 text-orange-800 dark:bg-orange-500/10 dark:text-orange-300', 'heroicon-s-exclamation-triangle', 'bg-orange-500 text-white'],
                'nearing' => ['ring-warning-500/40 bg-warning-50 text-warning-800 dark:bg-warning-500/10 dark:text-warning-300', 'heroicon-s-arrow-trending-up', 'bg-warning-400 text-gray-950'],
                'normal' => ['ring-success-500/30 bg-success-50 text-success-800 dark:bg-success-500/10 dark:text-success-300', 'heroicon-s-check-circle', 'bg-success-600 text-white'],
            ];
        @endphp
        <div class="mb-6 flex flex-wrap gap-3" role="status" aria-live="polite">
            @foreach($data['levels'] as $level => $items)
                <div wire:key="cap-{{ $level }}" class="flex min-w-[16rem] flex-1 items-start gap-3 rounded-2xl px-4 py-3 ring-1 {{ $style[$level][0] }}">
                    <x-filament::icon :icon="$style[$level][1]" @class(['mt-0.5 size-5 shrink-0', 'animate-pulse' => $level === 'critical']) />
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold">{{ __('admin.cap.'.$level) }} <span class="font-normal opacity-70">· {{ $data['event'] }}</span></p>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            @foreach($items as $i)
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums {{ $style[$level][2] }}">
                                    {{ $i['label'] }}: {{ $i['confirmed'] }}/{{ $i['cap'] }} ({{ $i['pct'] }}%)@if($level !== 'critical') — {{ __('admin.cap.remaining', ['n' => $i['left']]) }}@endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
