@props(['name', 'title' => null, 'maxWidth' => 'sm:max-w-lg', 'dark' => false])
{{-- Centered dialog on desktop, bottom sheet on phones. Open with $dispatch('open-dialog', '{name}'). --}}
<div x-data="{ open: false }"
     x-on:open-dialog.window="if ([].concat($event.detail).includes('{{ $name }}')) open = true"
     x-on:close-dialog.window="if (! $event.detail || [].concat($event.detail).includes('{{ $name }}')) open = false"
     x-on:keydown.escape.window="open = false">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[55] flex items-end justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true" @if($title) aria-label="{{ $title }}" @endif>
            <div x-show="open" x-transition.opacity.duration.400ms class="absolute inset-0 bg-ink/50 backdrop-blur-sm" @click="open = false"></div>
            <div x-show="open"
                 x-transition:enter="transition duration-500 ease-[cubic-bezier(0.32,0.72,0,1)]" x-transition:enter-start="translate-y-full sm:translate-y-6 sm:opacity-0 sm:scale-[.97]" x-transition:enter-end="translate-y-0 sm:opacity-100 sm:scale-100"
                 x-transition:leave="transition duration-300 ease-in" x-transition:leave-start="translate-y-0 sm:opacity-100" x-transition:leave-end="translate-y-full sm:translate-y-4 sm:opacity-0"
                 class="relative max-h-[92dvh] w-full {{ $maxWidth }} overflow-y-auto overscroll-contain rounded-t-[2rem] p-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] shadow-float sm:rounded-[2rem] sm:p-8 {{ $dark ? 'bg-ops-surface text-ops-fg ring-1 ring-white/10' : 'bg-white text-ink' }}">
                <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-current opacity-10 sm:hidden"></div>
                <button type="button" @click="open = false" class="absolute end-4 top-4 grid size-9 place-items-center rounded-full opacity-60 transition hover:bg-black/5 hover:opacity-100" aria-label="{{ __('common.close') }}"><x-glyph name="x" size="18" /></button>
                @if($title)<h2 class="mb-5 pe-10 text-xl font-extrabold">{{ $title }}</h2>@endif
                {{ $slot }}
            </div>
        </div>
    </template>
</div>
