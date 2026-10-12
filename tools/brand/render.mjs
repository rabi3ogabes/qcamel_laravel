// Renders the brand icon set + Open Graph card into public/ with the system Edge via Playwright.
// usage (from the project root): node tools/brand/render.mjs "$(pwd)"
import { chromium } from 'playwright';
import { pathToFileURL } from 'node:url';
import path from 'node:path';
import { readFileSync } from 'node:fs';

const root = process.argv[2];
const b64 = (f) => 'data:font/woff2;base64,' + readFileSync(path.join(root, 'node_modules/@fontsource/tajawal/files', f)).toString('base64');
const font = (w) => b64(`tajawal-arabic-${w}-normal.woff2`);
const fontLatin = (w) => b64(`tajawal-latin-${w}-normal.woff2`);

const AR = 'U+0600-06FF,U+0750-077F,U+08A0-08FF,U+FB50-FDFF,U+FE70-FEFF,U+200C-200E';
const LA = 'U+0000-00FF,U+2000-206F,U+2190-21FF,U+2700-27BF';
const faces = [500, 700, 800, 900].map((w) => `
@font-face{font-family:T;font-weight:${w};src:url(${font(w)}) format('woff2');unicode-range:${AR}}
@font-face{font-family:T;font-weight:${w};src:url(${fontLatin(w)}) format('woff2');unicode-range:${LA}}`).join('') + `
*{margin:0;padding:0;box-sizing:border-box}`;

// Sadu band: interlocking diamonds and chevrons in gold/cream on maroon.
const sadu = (h, scale = 1) => `
<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="${h}" preserveAspectRatio="none">
 <defs><pattern id="p" width="${48 * scale}" height="${h}" patternUnits="userSpaceOnUse">
  <rect width="${48 * scale}" height="${h}" fill="#7f1130"/>
  <path d="M0 ${h / 2} L${12 * scale} ${h * 0.12} L${24 * scale} ${h / 2} L${12 * scale} ${h * 0.88}Z" fill="#F9B11F"/>
  <path d="M${24 * scale} ${h / 2} L${36 * scale} ${h * 0.12} L${48 * scale} ${h / 2} L${36 * scale} ${h * 0.88}Z" fill="#F5EFE7"/>
  <path d="M${8 * scale} ${h / 2} L${12 * scale} ${h * 0.34} L${16 * scale} ${h / 2} L${12 * scale} ${h * 0.66}Z" fill="#7f1130"/>
  <path d="M${32 * scale} ${h / 2} L${36 * scale} ${h * 0.34} L${40 * scale} ${h / 2} L${36 * scale} ${h * 0.66}Z" fill="#A85740"/>
 </pattern></defs><rect width="100%" height="${h}" fill="url(#p)"/></svg>`;

const iconCss = (size) => `
.i{width:${size}px;height:${size}px;border-radius:${size * 0.22}px;overflow:hidden;position:relative;
 background:radial-gradient(120% 90% at 30% 15%,#c22a52 0%,#9C1638 45%,#5f0d24 100%);}
.q{position:absolute;inset:0;display:grid;place-items:center;font-family:T;font-weight:900;color:#F9B11F;
 font-size:${size * 0.66}px;line-height:1;padding-bottom:${size * 0.16}px;
 text-shadow:0 ${size * 0.012}px 0 #9c5009, 0 ${size * 0.03}px ${size * 0.06}px rgba(0,0,0,.35)}
.b{position:absolute;left:0;right:0;bottom:${size * 0.09}px;height:${size * 0.1}px;opacity:.95}
.r{position:absolute;inset:0;border-radius:inherit;box-shadow:inset 0 0 0 ${Math.max(1, size * 0.012)}px rgba(249,177,31,.35)}`;
const iconHtml = (size) => `<div class="i"><div class="q">ق</div><div class="b">${sadu(size * 0.1, size / 512)}</div><div class="r"></div></div>`;
const icon = (size) => `<!doctype html><html><head><style>${faces}
html,body{width:${size}px;height:${size}px;background:transparent}${iconCss(size)}</style></head><body>${iconHtml(size)}</body></html>`;

const og = `<!doctype html><html lang="ar" dir="rtl"><head><style>${faces}
html,body{width:1200px;height:630px}${iconCss(150)}
.mark .i{box-shadow:0 30px 60px -20px rgba(0,0,0,.55)}
body{position:relative;overflow:hidden;font-family:T;color:#F5EFE7;
 background:radial-gradient(90% 120% at 85% 10%,#b8234a 0%,#9C1638 38%,#4a0a1c 100%)}
.dune{position:absolute;inset:auto 0 0 0;height:300px}
.top,.bot{position:absolute;left:0;right:0;height:26px}
.top{top:0}.bot{bottom:0}
.wrap{position:absolute;inset:0;padding:96px 92px 0;display:flex;flex-direction:column;gap:22px}
.eyebrow{display:inline-flex;align-self:flex-start;gap:10px;align-items:center;border:1px solid rgba(249,177,31,.45);
 border-radius:999px;padding:9px 20px;font-weight:800;font-size:22px;color:#F9B11F;background:rgba(0,0,0,.18)}
h1{font-weight:900;font-size:104px;line-height:1.05;letter-spacing:-1px}
h1 span{color:#F9B11F}
p{font-weight:500;font-size:34px;color:rgba(245,239,231,.86)}
.cta{margin-top:18px;display:inline-flex;align-self:flex-start;align-items:center;gap:14px;background:#F9B11F;color:#3f0818;
 font-weight:800;font-size:30px;border-radius:999px;padding:16px 34px;box-shadow:0 18px 40px -16px rgba(0,0,0,.6)}
.cta i{display:grid;place-items:center;width:42px;height:42px;border-radius:50%;background:rgba(63,8,24,.12);font-style:normal}
.mark{position:absolute;left:92px;top:96px;width:150px;height:150px}
.url{position:absolute;left:92px;bottom:62px;font-weight:700;font-size:24px;letter-spacing:2px;color:rgba(249,177,31,.85);direction:ltr}
</style></head><body>
<div class="top">${sadu(26, 0.6)}</div>
<svg class="dune" viewBox="0 0 1200 300" preserveAspectRatio="none">
 <path d="M0 210 C 220 120, 420 250, 640 170 S 1020 110, 1200 190 L1200 300 L0 300Z" fill="rgba(212,183,138,.16)"/>
 <path d="M0 250 C 260 190, 520 290, 760 230 S 1060 200, 1200 240 L1200 300 L0 300Z" fill="rgba(212,183,138,.22)"/>
 <g fill="rgba(42,26,18,.5)" transform="translate(300 150) scale(1.25)">
  <path d="M10 70 C 14 52, 26 46, 36 44 C 44 26, 58 22, 66 34 C 72 24, 84 22, 92 30 C 98 20, 110 14, 118 22 L 122 18 L 128 22 L 124 30 C 118 34, 112 44, 108 52 L 104 70 L 98 70 L 98 56 C 92 58, 80 60, 70 58 L 64 70 L 58 70 L 60 56 C 50 56, 40 56, 32 54 L 28 70 L 22 70 L 24 56 C 18 58, 14 64, 16 70Z"/>
 </g>
</svg>
<div class="mark">${iconHtml(150)}</div>
<div class="wrap">
 <span class="eyebrow">✦ التذاكر الرسمية</span>
 <h1>مهرجان <span>قطر</span><br>للإبل</h1>
 <p>احجز تذكرتك في ثوانٍ — دخول عادي، VIP، ومواقف</p>
 <span class="cta">احجز الآن <i>←</i></span>
</div>
<div class="url">QCAMELMC.ORG</div>
<div class="bot">${sadu(26, 0.6)}</div>
</body></html>`;

const browser = await chromium.launch({ channel: 'msedge' });
const page = await browser.newPage({ deviceScaleFactor: 1 });
const out = path.join(root, 'public');
for (const [size, name] of [[512, 'favicon-512.png'], [192, 'favicon-192.png'], [180, 'apple-touch-icon.png'], [64, 'favicon.png'], [32, 'favicon-32.png']]) {
  await page.setViewportSize({ width: size, height: size });
  await page.setContent(icon(size), { waitUntil: 'networkidle' });
  await page.evaluate(async () => { await Promise.all([500, 700, 800, 900].flatMap((w) => [document.fonts.load(`${w} 40px T`, 'قطر'), document.fonts.load(`${w} 40px T`, 'VIP')])); await document.fonts.ready; });
  await page.screenshot({ path: path.join(out, name), omitBackground: true, clip: { x: 0, y: 0, width: size, height: size } });
}
await page.setViewportSize({ width: 1200, height: 630 });
await page.setContent(og, { waitUntil: 'networkidle' });
await page.evaluate(async () => { await Promise.all([500, 700, 800, 900].flatMap((w) => [document.fonts.load(`${w} 40px T`, 'قطر'), document.fonts.load(`${w} 40px T`, 'VIP')])); await document.fonts.ready; });
await page.screenshot({ path: path.join(out, 'og-image.jpg'), type: 'jpeg', quality: 90 });
await browser.close();
console.log('rendered');
