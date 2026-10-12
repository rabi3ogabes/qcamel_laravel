/*
 * POS celebration (F-SFX-009…016): full-screen canvas, 6 s, palette gold /
 * light gold / rose / emerald / white, opening volley + bursts every 750 ms,
 * disabled under prefers-reduced-motion.
 */
const PALETTE = ['250,204,21', '253,224,71', '190,24,93', '16,185,129', '255,255,255'];
const rand = (a, b) => a + Math.random() * (b - a);

export function fireworks(duration = 6000) {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return () => {};
    const canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    Object.assign(canvas.style, { position: 'fixed', inset: '0', zIndex: '100', pointerEvents: 'none' });
    document.body.appendChild(canvas);
    const g = canvas.getContext('2d');
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    let w = 0, h = 0;
    const resize = () => {
        w = window.innerWidth; h = window.innerHeight;
        canvas.width = w * dpr; canvas.height = h * dpr;
        g.setTransform(dpr, 0, 0, dpr, 0, 0);
    };
    resize();
    window.addEventListener('resize', resize);

    const particles = [];
    const explode = (cx, cy, color, count = 46, power = 4.2) => {
        for (let i = 0; i < count; i++) {
            const angle = (Math.PI * 2 * i) / count + rand(-0.12, 0.12);
            const speed = rand(0.45, 1) * power;
            const white = color === '255,255,255';
            particles.push({
                x: cx, y: cy, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed,
                life: 0, maxLife: rand(50, 85), size: white ? rand(1, 1.8) : rand(1.4, 2.6),
                color, sparkle: Math.random() < 0.35,
            });
        }
    };

    explode(rand(0.42, 0.58) * w, rand(0.16, 0.3) * h, PALETTE[Math.floor(Math.random() * 3)], 60, 5);
    explode(rand(0.12, 0.3) * w, rand(0.12, 0.24) * h, PALETTE[Math.floor(Math.random() * 3)], 40, 4);
    explode(rand(0.7, 0.88) * w, rand(0.12, 0.24) * h, PALETTE[3], 40, 4);
    explode(rand(0.3, 0.45) * w, rand(0.34, 0.44) * h, PALETTE[4], 24, 3);

    const started = performance.now();
    const interval = setInterval(() => {
        if (performance.now() - started > duration) return clearInterval(interval);
        explode(rand(0.15, 0.85) * w, rand(0.12, 0.4) * h, PALETTE[Math.floor(Math.random() * PALETTE.length)], Math.round(rand(24, 44)), rand(3, 4.4));
    }, 750);

    let raf = 0;
    const frame = () => {
        g.globalCompositeOperation = 'destination-out';
        g.fillStyle = 'rgba(0,0,0,0.28)';
        g.fillRect(0, 0, w, h);
        g.globalCompositeOperation = 'lighter';
        for (let i = particles.length - 1; i >= 0; i--) {
            const p = particles[i];
            p.life++;
            if (p.life > p.maxLife) { particles.splice(i, 1); continue; }
            p.vx *= 0.985; p.vy = p.vy * 0.985 + 0.035;
            p.x += p.vx; p.y += p.vy;
            const t = 1 - p.life / p.maxLife;
            let alpha = t;
            if (p.sparkle && Math.random() < 0.4) alpha *= 0.35;
            g.beginPath();
            g.fillStyle = `rgba(${p.color},${alpha})`;
            g.arc(p.x, p.y, p.size * t + 0.4, 0, Math.PI * 2);
            g.fill();
        }
        if (performance.now() - started < duration || particles.length) raf = requestAnimationFrame(frame);
        else cleanup();
    };
    const cleanup = () => {
        cancelAnimationFrame(raf); clearInterval(interval);
        window.removeEventListener('resize', resize);
        canvas.remove();
    };
    raf = requestAnimationFrame(frame);
    return cleanup;
}
