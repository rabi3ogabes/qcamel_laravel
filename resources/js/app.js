import './bootstrap';
import { sfx, haptic, muted, setMuted } from './sfx';
import { fireworks } from './fireworks';

/* F-PUB-082: save the invoice card as PNG (pixel ratio 2, white background). */
async function saveImage(node, filename) {
    if (!node) return;
    try {
        const { toPng } = await import('html-to-image');
        const url = await toPng(node, { pixelRatio: 2, backgroundColor: '#ffffff', cacheBust: true });
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
    } catch (e) {
        console.error('save image failed', e);
    }
}

window.qc = { sfx, haptic, fireworks, muted, setMuted, saveImage };

/*
 * Toast store (F-PUB-004): top of the "end" side, up to 6 visible, 12 px gap,
 * fed by Livewire `$this->dispatch('toast', ...)` and server flashes.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.store('toasts', {
        items: [],
        push({ type = 'info', title = '', message = '', duration = 4500 } = {}) {
            const id = Date.now() + Math.random();
            this.items.push({ id, type, title, message, duration });
            if (this.items.length > 6) this.items.shift();
            setTimeout(() => this.remove(id), duration);
        },
        remove(id) { this.items = this.items.filter((t) => t.id !== id); },
    });

    /* F-GATE-002 rebuild: real connectivity = navigator.onLine + a 30 s server ping. */
    window.Alpine.data('netStatus', () => ({
        online: navigator.onLine,
        start() {
            const ping = async () => {
                if (!navigator.onLine) { this.online = false; return; }
                try {
                    const r = await fetch('/up', { cache: 'no-store', credentials: 'same-origin' });
                    this.online = r.ok;
                } catch { this.online = false; }
            };
            window.addEventListener('online', ping);
            window.addEventListener('offline', () => (this.online = false));
            setInterval(ping, 30000);
        },
    }));

    window.Alpine.data('copyText', (text) => ({
        done: false,
        async copy() {
            try { await navigator.clipboard.writeText(text); } catch { /* clipboard blocked */ }
            this.done = true;
            window.Alpine.store('toasts').push({ type: 'success', message: document.documentElement.dataset.copied || 'Copied', duration: 2000 });
            setTimeout(() => (this.done = false), 2000);
        },
    }));
});

window.addEventListener('toast', (e) => {
    const d = Array.isArray(e.detail) ? e.detail[0] : e.detail;
    window.Alpine?.store('toasts')?.push(d || {});
});
window.addEventListener('sfx', (e) => {
    const d = Array.isArray(e.detail) ? e.detail[0] : e.detail;
    const name = d?.name;
    if (name && sfx[name]) sfx[name](d.type);
});
window.addEventListener('fireworks', () => fireworks(6000));

/* Scroll interpolation: heavy fade-up on entry (IntersectionObserver only). */
const io = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => entries.forEach((en) => {
        if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
    }), { rootMargin: '0px 0px -8% 0px' })
    : null;
const observeReveals = () => document.querySelectorAll('.reveal:not(.is-in)').forEach((el) => (io ? io.observe(el) : el.classList.add('is-in')));
document.addEventListener('DOMContentLoaded', observeReveals);
document.addEventListener('livewire:navigated', observeReveals);
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morph.updated', () => requestAnimationFrame(observeReveals));
});

/* F-PUB-091 rebuild: 60 s presence heartbeat on public pages only. */
if (document.body?.dataset.track === '1') {
    const beat = () => {
        const fd = new FormData();
        fd.append('p', location.pathname);
        fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '');
        navigator.sendBeacon?.('/v', fd);
    };
    setInterval(beat, 60000);
}

/* F-PUB-006: one-time cleanup of the old SPA's caches / service workers. */
try {
    if (localStorage.getItem('app-cleanup-popups-v1') !== 'done') {
        [localStorage, sessionStorage].forEach((s) => Object.keys(s).filter((k) => /popup|banner/i.test(k)).forEach((k) => s.removeItem(k)));
        caches?.keys?.().then((ks) => ks.forEach((k) => caches.delete(k)));
        navigator.serviceWorker?.getRegistrations?.().then((rs) => rs.forEach((r) => {
            if (!r.active?.scriptURL?.includes('/sw.js')) r.unregister();
        }));
        localStorage.setItem('app-cleanup-popups-v1', 'done');
    }
} catch { /* storage may be blocked */ }

/* Realtime (Reverb + Echo) only when configured; every screen also polls (rule 8). */
if (import.meta.env.VITE_REVERB_APP_KEY) {
    import('./echo').catch(() => {});
}
