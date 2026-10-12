/*
 * Gate camera (F-GATE-005…010): html5-qrcode with BarcodeDetector when
 * available, back camera preferred and remembered, torch toggle, 2 s
 * same-code debounce, mapped camera errors. Decodes are handed to Livewire.
 */
const CAMERA_KEY = 'qc:scanner:camera';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('qrScanner', (opts = {}) => ({
        state: 'idle', // idle | starting | running
        error: '',
        torch: false,
        torchSupported: false,
        cameras: [],
        cameraId: null,
        lastCode: '',
        lastAt: 0,
        busy: false,
        reader: null,
        msgs: opts.messages || {},

        init() {
            this.$wire.on('scanner-resume', () => setTimeout(() => this.start(true), 250));
            this.$wire.on('scanner-idle', () => { this.busy = false; });
            window.addEventListener('beforeunload', () => this.stop());
        },

        destroy() { this.stop(); },

        async ensureReader() {
            if (this.reader) return this.reader;
            const { Html5Qrcode, Html5QrcodeSupportedFormats } = await import('html5-qrcode');
            this.reader = new Html5Qrcode(this.$refs.reader.id, {
                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
                experimentalFeatures: { useBarCodeDetectorIfSupported: true },
                verbose: false,
            });
            return this.reader;
        },

        async start(silent = false) {
            if (this.state !== 'idle') return;
            this.error = '';
            if (!navigator.mediaDevices?.getUserMedia) {
                this.fail(this.msgs.noApi);
                return;
            }
            this.state = 'starting';
            try {
                const { Html5Qrcode } = await import('html5-qrcode');
                const reader = await this.ensureReader();
                if (!this.cameras.length) this.cameras = await Html5Qrcode.getCameras();
                if (!this.cameras.length) throw Object.assign(new Error(this.msgs.noCamera), { name: 'NotFoundError' });
                let saved = null;
                try { saved = localStorage.getItem(CAMERA_KEY); } catch {}
                const back = this.cameras.find((c) => /back|rear|environment|خلف/i.test(c.label || ''));
                this.cameraId = this.cameras.some((c) => c.id === saved) ? saved : (back || this.cameras[this.cameras.length - 1]).id;
                await reader.start(this.cameraId, { fps: 12, qrbox: (w, h) => { const s = Math.floor(Math.min(w, h) * 0.72); return { width: s, height: s }; }, aspectRatio: 1 },
                    (text) => this.onDecode(text), () => {});
                this.state = 'running';
                try {
                    const caps = reader.getRunningTrackCapabilities?.() || {};
                    this.torchSupported = !!caps.torch;
                } catch { this.torchSupported = false; }
                if (!silent) window.Alpine.store('toasts').push({ type: 'success', message: this.msgs.started, duration: 2000 });
            } catch (e) {
                this.state = 'idle';
                const map = { NotAllowedError: this.msgs.denied, NotFoundError: this.msgs.noCamera, NotReadableError: this.msgs.busy };
                this.fail(map[e?.name] || e?.message || this.msgs.failed);
            }
        },

        async stop() {
            if (this.reader && this.state === 'running') {
                try { await this.reader.stop(); } catch {}
            }
            this.state = 'idle';
            this.torch = false;
        },

        async switchCamera() {
            if (this.cameras.length < 2) return;
            const i = this.cameras.findIndex((c) => c.id === this.cameraId);
            const next = this.cameras[(i + 1) % this.cameras.length].id;
            try { localStorage.setItem(CAMERA_KEY, next); } catch {}
            await this.stop();
            await this.start(true);
        },

        async toggleTorch() {
            if (!this.reader || !this.torchSupported) return;
            this.torch = !this.torch;
            try { await this.reader.applyVideoConstraints({ advanced: [{ torch: this.torch }] }); } catch { this.torch = false; }
        },

        fail(message) {
            this.error = message;
            window.Alpine.store('toasts').push({ type: 'error', message });
            window.qc?.sfx.error();
        },

        async onDecode(text) {
            const now = Date.now();
            if (this.busy || (text === this.lastCode && now - this.lastAt < 2000)) return;
            this.lastCode = text;
            this.lastAt = now;
            this.busy = true;
            window.qc?.haptic(40);
            const continuous = this.$wire.get('continuous');
            if (!continuous) await this.stop();
            try {
                await this.$wire.call('process', text, true);
            } finally {
                this.busy = false;
            }
        },
    }));
});
