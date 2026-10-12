/*
 * SFX library (F-SFX-001…008, F-UI-014): Web Audio tones only, ONE shared
 * AudioContext unlocked on the first user gesture (D-052), global mute
 * persisted per device.
 */
let ctx = null;
const MUTE_KEY = 'qc:sfx:muted';

function safeGet(k) { try { return localStorage.getItem(k); } catch { return null; } }
function safeSet(k, v) { try { localStorage.setItem(k, v); } catch { /* storage blocked */ } }

export function audio() {
    if (!ctx) {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return null;
        ctx = new AC();
    }
    if (ctx.state === 'suspended') ctx.resume().catch(() => {});
    return ctx;
}

export const muted = () => safeGet(MUTE_KEY) === '1';
export const setMuted = (m) => safeSet(MUTE_KEY, m ? '1' : '0');

['pointerdown', 'keydown', 'touchstart'].forEach((ev) =>
    window.addEventListener(ev, () => audio(), { once: true, passive: true }),
);

/** One tone: osc → gain, 20 ms exponential attack, decay to 0.0001 (F-SFX-001). */
function tone(freq, start, duration, { type = 'sine', volume = 0.35 } = {}) {
    const c = audio();
    if (!c || muted()) return;
    const t0 = c.currentTime + start;
    const osc = c.createOscillator();
    const gain = c.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, t0);
    gain.gain.setValueAtTime(0.0001, t0);
    gain.gain.exponentialRampToValueAtTime(volume, t0 + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + duration);
    osc.connect(gain).connect(c.destination);
    osc.start(t0);
    osc.stop(t0 + duration + 0.02);
}

/** Frequency sweep helper (glide through notes at given offsets). */
function sweep(notes, total, { type = 'sine', from = 0.3, peak = null, peakAt = 0, to = 0.01 } = {}) {
    const c = audio();
    if (!c || muted()) return;
    const t0 = c.currentTime;
    const osc = c.createOscillator();
    const gain = c.createGain();
    osc.type = type;
    notes.forEach(([f, at]) => osc.frequency.setValueAtTime(f, t0 + at));
    gain.gain.setValueAtTime(from, t0);
    if (peak) gain.gain.linearRampToValueAtTime(peak, t0 + peakAt);
    gain.gain.exponentialRampToValueAtTime(to, t0 + total);
    osc.connect(gain).connect(c.destination);
    osc.start(t0);
    osc.stop(t0 + total + 0.02);
}

export const sfx = {
    // F-SFX-002 alert bell
    alert() { tone(880, 0, 0.22); tone(1320, 0.18, 0.3); },
    // F-SFX-003 error buzzer
    error() { tone(320, 0, 0.25, { type: 'square', volume: 0.4 }); tone(200, 0.22, 0.35, { type: 'square', volume: 0.4 }); },
    // NEW success beep at the gate
    success() { tone(988, 0, 0.12, { volume: 0.3 }); tone(1319, 0.1, 0.18, { volume: 0.3 }); },
    // F-SFX-004 new online booking chime
    chime() { sweep([[880, 0], [1108.73, 0.1], [1318.51, 0.2]], 0.5, { from: 0.3 }); },
    // F-SFX-005 POS cha-ching
    cash() {
        sweep([[1200, 0], [800, 0.05]], 0.1, { type: 'square', from: 0.25 });
        sweep([[1567.98, 0], [2093, 0.15]], 0.4, { from: 0.25, peak: 0.4, peakAt: 0.1 });
        sweep([[2637, 0], [3136, 0.2]], 0.5, { type: 'triangle', from: 0.2 });
    },
    // F-SFX-006 check-in by type
    checkin(type) {
        if (type === 'vip') {
            sweep([[523.25, 0], [659.25, 0.15], [783.99, 0.3]], 0.6, { from: 0.4 });
            sweep([[1046.5, 0], [1318.51, 0.15], [1567.98, 0.3]], 0.6, { type: 'triangle', from: 0.2 });
        } else if (type === 'normal') {
            sweep([[698.46, 0], [880, 0.1]], 0.4, { from: 0.35 });
        } else if (type === 'parking') {
            sweep([[349.23, 0], [440, 0.08]], 0.3, { from: 0.3 });
        } else {
            sweep([[440, 0], [523.25, 0.1]], 0.3, { from: 0.3 });
        }
    },
    // F-SFX-007 capacity increase by type
    capacity(type) {
        if (type === 'vip') {
            sweep([[659.25, 0], [783.99, 0.12], [987.77, 0.24], [1318.51, 0.36]], 1.0, { type: 'triangle', from: 0.4, peak: 0.6, peakAt: 0.2 });
            sweep([[493.88, 0], [659.25, 0.12], [783.99, 0.24], [987.77, 0.36]], 1.0, { from: 0.2 });
            sweep([[1318.51, 0], [1567.98, 0.12], [1975.53, 0.24], [2637.02, 0.36]], 1.0, { from: 0.1 });
        } else if (type === 'normal') {
            sweep([[523.25, 0], [659.25, 0.15], [783.99, 0.3], [1046.5, 0.45]], 0.8, { type: 'triangle', from: 0.5, peak: 0.6, peakAt: 0.3 });
            sweep([[392, 0], [523.25, 0.15], [659.25, 0.3], [783.99, 0.45]], 0.8, { from: 0.2 });
        } else if (type === 'parking') {
            sweep([[261.63, 0], [329.63, 0.1], [392, 0.2]], 0.5, { type: 'square', from: 0.3 });
        } else {
            sweep([[440, 0], [880, 0.2]], 0.4, { from: 0.3 });
        }
    },
};

export function haptic(pattern = 30) {
    try { navigator.vibrate?.(pattern); } catch { /* unsupported */ }
}
