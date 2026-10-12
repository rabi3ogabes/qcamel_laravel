@props([
    'title',
    'subtitle' => null,
    'icon' => 'sparkle',
    'dark' => false,
    'back' => true,
    'sound' => false,
    'fullscreen' => false,
    'status' => false,      // live connectivity dot (navigator.onLine + /up ping)
    'width' => 'max-w-7xl',
])
@php $actor = \App\Domain\Shared\Actor::current(); @endphp
{{-- Shared staff header: floating glass bar, identity chip, utilities on the end side --}}
<header class="sticky top-0 z-40 px-3 pt-3 sm:px-5">
    <div @class([
        'mx-auto flex min-h-16 items-center gap-3 rounded-[1.4rem] px-3 py-2 ring-1 backdrop-blur-xl sm:px-4', $width,
        'bg-white/80 shadow-soft ring-bark/10' => ! $dark,
        'bg-ops-surface/80 shadow-[0_20px_50px_-30px_rgb(0_0_0/0.8)] ring-white/10' => $dark,
    ])>
        <a href="{{ route('staff.hub') }}" class="flex shrink-0 items-center" aria-label="{{ __('ops.shell.hub') }}">
            @if($brand['logo_url'] ?? null)
                <span @class(['grid size-11 place-items-center overflow-hidden rounded-2xl p-1', 'bg-white ring-1 ring-bark/10' => ! $dark, 'bg-white/95 ring-2 ring-gold-400/60' => $dark])>
                    <img src="{{ $brand['logo_url'] }}" alt="{{ __('common.logo') }}" class="max-h-full w-auto">
                </span>
            @else
                <span class="grid size-11 place-items-center rounded-2xl bg-maroon-600 text-gold-300 shadow-elegant"><x-glyph :name="$icon" size="22" /></span>
            @endif
        </a>
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-base font-extrabold sm:text-lg">{{ $title }}</h1>
            <div class="flex items-center gap-2 text-xs {{ $dark ? 'text-ops-muted' : 'text-ink-soft' }}">
                @if($status)
                    <span x-data="netStatus()" x-init="start()" class="inline-flex items-center gap-1.5">
                        <span class="relative flex size-2">
                            <span x-show="online" class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400/70"></span>
                            <span class="relative inline-flex size-2 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-red-500'"></span>
                        </span>
                        <span x-text="online ? @js(__('ops.shell.online')) : @js(__('ops.shell.offline'))"></span>
                    </span>
                    @if($subtitle)<span class="opacity-40">•</span>@endif
                @endif
                @if($subtitle)<span class="truncate">{{ $subtitle }}</span>@endif
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-1.5">
            {{ $slot }}
            @if($sound)
                <button type="button" x-data="{ m: window.qc?.muted() }" @click="m = !m; window.qc.setMuted(m); if (!m) window.qc.sfx.success()"
                    :title="m ? @js(__('ops.shell.sound_on')) : @js(__('ops.shell.sound_off'))"
                    @class(['grid size-10 place-items-center rounded-full transition', 'hover:bg-bark/10' => ! $dark, 'hover:bg-white/10' => $dark])>
                    <span x-show="!m" class="text-emerald-500"><x-glyph name="volume" size="19" /></span>
                    <span x-show="m" x-cloak class="opacity-60"><x-glyph name="volume-x" size="19" /></span>
                </button>
            @endif
            @if($fullscreen)
                <button type="button" x-data="{ fs: false }" x-init="document.addEventListener('fullscreenchange', () => fs = !!document.fullscreenElement)"
                    @click="(fs ? document.exitFullscreen() : document.documentElement.requestFullscreen()).catch(() => $store.toasts.push({ type: 'error', message: @js(__('ops.shell.fullscreen_error')) }))"
                    :title="fs ? @js(__('ops.shell.exit_fullscreen')) : @js(__('ops.shell.fullscreen'))"
                    @class(['hidden size-10 place-items-center rounded-full transition md:grid', 'hover:bg-bark/10' => ! $dark, 'hover:bg-white/10' => $dark])>
                    <x-glyph name="expand" size="18" />
                </button>
            @endif
            @if($back)
                <a href="{{ route('staff.hub') }}" @class(['hidden h-10 items-center gap-1.5 rounded-full px-3 text-sm font-bold ring-1 transition sm:inline-flex', 'ring-bark/15 hover:bg-bark/5' => ! $dark, 'ring-white/15 hover:bg-white/10' => $dark])>
                    <x-glyph name="home" size="16" />{{ __('ops.shell.hub') }}
                </a>
            @endif
            <form method="POST" action="{{ route('staff.logout') }}">
                @csrf
                <button type="submit" title="{{ __('ops.shell.logout') }}" @class(['grid size-10 place-items-center rounded-full transition', 'hover:bg-danger/10 hover:text-danger' => ! $dark, 'hover:bg-red-500/15 hover:text-red-300' => $dark])>
                    <x-glyph name="logout" size="18" flip />
                </button>
            </form>
        </div>
    </div>
</header>
