import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

// Live connection indicator (D-056: never a static «متصل»).
const set = (state) => document.documentElement.dataset.realtime = state;
window.Echo.connector.pusher.connection.bind('connected', () => set('online'));
window.Echo.connector.pusher.connection.bind('unavailable', () => set('offline'));
window.Echo.connector.pusher.connection.bind('disconnected', () => set('offline'));
