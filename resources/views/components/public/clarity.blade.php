@props(['id', 'consent' => true])
@php $nonce = \Illuminate\Support\Facades\Vite::cspNonce(); @endphp
{{-- F-PUB-087/088 rebuild: Clarity on crawlable pages only, behind a consent gate (NEW). --}}
<div x-data="{ ask: false,
        load() {
            if (document.getElementById('ms-clarity-script')) return;
            (function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
             t=l.createElement(r);t.async=1;t.id='ms-clarity-script';t.src='https://www.clarity.ms/tag/'+i;
             y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script',@js($id));
            try { window.clarity('set', 'page_path', location.pathname); window.clarity('set', 'site_area', 'public'); } catch (e) {}
        },
        decide(v) { try { localStorage.setItem('qc:consent', v) } catch (e) {} this.ask = false; if (v === 'yes') this.load(); } }"
     x-init="let v = null; try { v = localStorage.getItem('qc:consent') } catch (e) {}
             if (! @js($consent) || v === 'yes') load(); else if (v === null) setTimeout(() => ask = true, 1200)">
    <div x-show="ask" x-cloak x-transition:enter="transition duration-700 ease-[cubic-bezier(0.32,0.72,0,1)]" x-transition:enter-start="translate-y-6 opacity-0"
         class="fixed inset-x-3 bottom-3 z-50 mx-auto max-w-xl rounded-[1.5rem] bg-ink/95 p-4 text-cream shadow-float ring-1 ring-white/10 backdrop-blur-xl sm:bottom-6 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <p class="flex-1 text-sm leading-relaxed text-cream/85">{{ app()->getLocale() === 'en' ? 'We use anonymous analytics to improve booking. Allow?' : 'نستخدم تحليلات مجهولة الهوية لتحسين تجربة الحجز. هل توافق؟' }}</p>
            <div class="flex gap-2">
                <button type="button" @click="decide('no')" class="h-10 rounded-full px-4 text-sm font-bold text-cream/80 ring-1 ring-white/20 hover:bg-white/10">{{ app()->getLocale() === 'en' ? 'No thanks' : 'لا، شكراً' }}</button>
                <button type="button" @click="decide('yes')" class="h-10 rounded-full bg-gold-400 px-5 text-sm font-bold text-ink hover:bg-gold-300">{{ app()->getLocale() === 'en' ? 'Allow' : 'موافق' }}</button>
            </div>
        </div>
    </div>
</div>
