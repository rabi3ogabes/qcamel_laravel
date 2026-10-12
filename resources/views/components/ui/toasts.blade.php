@props(['dark' => false])
{{-- F-PUB-004: one toast store for every app — top on the logical "end" side, ≤ 6 visible, 12 px gap. --}}
<div x-data class="pointer-events-none fixed inset-x-4 top-4 z-[60] flex flex-col items-stretch gap-3 sm:inset-x-auto sm:end-5 sm:top-5 sm:w-[380px]" aria-live="polite">
    <template x-for="t in $store.toasts.items" :key="t.id">
        <div x-data="{ show: false }" x-init="$nextTick(() => show = true)" x-show="show"
             x-transition:enter="transition duration-500 ease-[cubic-bezier(0.32,0.72,0,1)]" x-transition:enter-start="opacity-0 -translate-y-3 scale-[.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             class="pointer-events-auto relative overflow-hidden rounded-2xl p-4 pe-11 shadow-float ring-1 backdrop-blur-xl {{ $dark ? 'bg-ops-elevated/95 text-ops-fg ring-white/10' : 'bg-white/95 text-ink ring-bark/10' }}"
             :role="t.type === 'error' ? 'alert' : 'status'">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full"
                      :class="{ 'bg-success/15 text-success': t.type === 'success', 'bg-danger/15 text-danger': t.type === 'error', 'bg-gold-100 text-gold-700': t.type === 'warning', 'bg-maroon-50 text-maroon-600': t.type === 'info' }">
                    <svg x-show="t.type === 'success'" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                    <svg x-show="t.type === 'error'" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    <svg x-show="t.type !== 'success' && t.type !== 'error'" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 11v5"/><circle cx="12" cy="7.5" r=".8" fill="currentColor"/></svg>
                </span>
                <div class="min-w-0 flex-1 space-y-0.5">
                    <p x-show="t.title" x-text="t.title" class="text-sm font-extrabold"></p>
                    <p x-text="t.message" class="whitespace-pre-line text-sm leading-relaxed {{ $dark ? 'text-ops-fg/85' : 'text-ink-soft' }}"></p>
                </div>
            </div>
            <button type="button" @click="$store.toasts.remove(t.id)" class="absolute end-3 top-3 grid size-7 place-items-center rounded-full opacity-50 transition hover:bg-black/5 hover:opacity-100" aria-label="{{ __('common.close') }}">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
            <span class="absolute bottom-0 start-0 h-0.5 w-full origin-left bg-current opacity-15 rtl:origin-right" :style="`animation: shrink ${t.duration}ms linear forwards`"></span>
        </div>
    </template>
</div>
@if(session()->has('toast'))
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        document.addEventListener('alpine:initialized', () => window.Alpine.store('toasts').push(@json(session('toast'))));
    </script>
@endif
