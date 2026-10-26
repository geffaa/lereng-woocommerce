/**
 * Builds every product and theme image into data/images/ (and theme/assets/img/):
 *  - a label for each coffee: island colour, contour lines drawn from a seed per origin, and the
 *    facts printed in mono, like a roaster's bag label
 *  - typographic tiles for drip bags, cold brew, bundles, the gift card, the subscription and merch
 *  - CC0 photos (design/photos/src, credits.json) cropped to one shape with one colour grade
 * Writes data/images.json: product slug → [main image, gallery...].
 * Usage: node design/build.mjs
 */
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { fileURLToPath, pathToFileURL } from 'node:url';
import puppeteer from 'puppeteer-core';

const root = new URL('..', import.meta.url);
const out = fileURLToPath(new URL('data/images/', root));
const themeImg = fileURLToPath(new URL('wp-content/themes/lereng/assets/img/', root));
const src = fileURLToPath(new URL('design/photos/src/', root));
const font = (f) => pathToFileURL(fileURLToPath(new URL(`wp-content/themes/lereng/assets/fonts/${f}`, root))).href;
const catalog = JSON.parse(await readFile(new URL('data/catalog.json', root), 'utf8'));

const ISLAND = { Sumatra: '#2F4B3C', Java: '#2E3D6B', Bali: '#B9852A', 'Nusa Tenggara': '#B9563B', Sulawesi: '#6B3554', Papua: '#1E6B67', Blend: '#2B2A27' };
const W = 1200;
const H = 1500;

/* Deterministic randomness per product. */
function rng(seed) {
  let s = [...seed].reduce((a, c) => (a * 31 + c.charCodeAt(0)) >>> 0, 7) || 1;
  return () => ((s = (s * 1103515245 + 12345) >>> 0) / 4294967296);
}

/** Topographic lines: a stack of wavy contours, like the slope a coffee grows on. */
function contours(seed, color, { count = 16, top = 260, bottom = 1250 } = {}) {
  const r = rng(seed);
  const peaks = Array.from({ length: 3 }, () => ({ x: 150 + r() * 900, h: 80 + r() * 160, w: 220 + r() * 280 }));
  const lines = [];
  for (let i = 0; i < count; i++) {
    const base = top + ((bottom - top) * i) / (count - 1);
    let d = '';
    for (let x = -20; x <= W + 20; x += 20) {
      let y = base;
      for (const p of peaks) y -= p.h * Math.exp(-((x - p.x) ** 2) / (2 * p.w ** 2)) * (1 - i / (count * 1.4));
      y += Math.sin(x / 90 + i * 0.7 + peaks[0].x) * 6;
      d += `${x === -20 ? 'M' : 'L'}${x} ${y.toFixed(1)} `;
    }
    lines.push(`<path d="${d}" fill="none" stroke="${color}" stroke-width="${i % 5 === 0 ? 2.4 : 1.2}" opacity="${i % 5 === 0 ? 0.5 : 0.28}"/>`);
  }
  return lines.join('');
}

const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;');

function coffeeLabel(p, index) {
  const bg = p.kind === 'microlot' ? '#17150F' : ISLAND[p.island] || '#2B2A27';
  const ink = '#F6F4EF';
  const accent = p.kind === 'microlot' ? '#C9A24A' : 'rgba(246,244,239,.75)';
  const name = p.name.replace(/ · Lot \d+/, '');
  const lot = p.name.match(/Lot \d+/)?.[0];
  const words = name.split(' ');
  // Two lines for long names, broken near the middle.
  const mid = Math.ceil(words.length / 2);
  const line1 = words.length > 2 ? words.slice(0, mid).join(' ') : name;
  const line2 = words.length > 2 ? words.slice(mid).join(' ') : '';
  const size = Math.max(...[line1, line2].map((l) => l.length)) > 11 ? 112 : 136;
  const facts = [
    ['Process', p.process],
    ['Roast', p.roast[0].toUpperCase() + p.roast.slice(1).replace('-', ' ')],
    p.altitude ? ['Altitude', p.altitude] : ['Blend of', p.components],
    p.varietals ? ['Varietal', p.varietals.split(',')[0]] : ['Species', p.species],
  ];
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" width="${W}" height="${H}">
  <rect width="${W}" height="${H}" fill="${bg}"/>
  ${contours(p.slug, ink)}
  <g font-family="Plex" fill="${accent}" font-size="26" letter-spacing="4">
    <text x="80" y="110">LERENG COFFEE ROASTERS</text>
    <text x="${W - 80}" y="110" text-anchor="end">N° ${String(index).padStart(2, '0')}</text>
    <text x="80" y="150" fill="${ink}" opacity=".6">${esc((p.region || p.island || '').toUpperCase())}</text>
  </g>
  ${lot ? `<rect x="80" y="${H - 790}" width="330" height="56" fill="#C9A24A"/><text x="98" y="${H - 752}" font-family="Plex" font-size="26" letter-spacing="4" fill="#17150F">MICRO-LOT · ${lot.replace('Lot ', '')}</text>` : ''}
  <g font-family="Grotesk" font-weight="800" fill="${ink}" letter-spacing="-4">
    <text x="74" y="${H - 590 + (line2 ? 0 : size)}" font-size="${size}">${esc(line1)}</text>
    ${line2 ? `<text x="74" y="${H - 590 + size}" font-size="${size}">${esc(line2)}</text>` : ''}
  </g>
  <line x1="80" x2="${W - 80}" y1="${H - 250}" y2="${H - 250}" stroke="${ink}" stroke-opacity=".5" stroke-width="2"/>
  <g font-family="Plex" font-size="25" letter-spacing="2">
    ${facts.map(([k, v], i) => `<text x="${80 + (i % 2) * 540}" y="${H - 190 + Math.floor(i / 2) * 70}" fill="${accent}">${esc(k.toUpperCase())}</text><text x="${80 + (i % 2) * 540}" y="${H - 160 + Math.floor(i / 2) * 70}" fill="${ink}" font-size="27">${esc(String(v).slice(0, 28))}</text>`).join('')}
  </g>
  <text x="80" y="${H - 300}" font-family="Grotesk" font-weight="500" font-size="40" fill="${ink}" opacity=".9">${esc(p.notes.join(' · '))}</text>
</svg>`;
}

function tile({ seed, bg, ink = '#F6F4EF', kicker, title, sub, foot }) {
  const lines = title.split('\n');
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" width="${W}" height="${H}">
  <rect width="${W}" height="${H}" fill="${bg}"/>
  ${contours(seed, ink, { count: 12, top: 380, bottom: 1300 })}
  <text x="80" y="110" font-family="Plex" font-size="26" letter-spacing="4" fill="${ink}" opacity=".75">LERENG COFFEE ROASTERS</text>
  <text x="80" y="160" font-family="Plex" font-size="26" letter-spacing="4" fill="${ink}" opacity=".55">${esc(kicker)}</text>
  ${lines.map((l, i) => `<text x="74" y="${H - 420 + i * 130}" font-family="Grotesk" font-weight="800" font-size="128" letter-spacing="-4" fill="${ink}">${esc(l)}</text>`).join('')}
  <text x="80" y="${H - 160}" font-family="Grotesk" font-weight="500" font-size="40" fill="${ink}" opacity=".85">${esc(sub)}</text>
  ${foot ? `<text x="80" y="${H - 100}" font-family="Plex" font-size="25" letter-spacing="3" fill="${ink}" opacity=".6">${esc(foot)}</text>` : ''}
</svg>`;
}

/* Photo crops: name → [source, focal x, focal y, width, height] */
const PHOTOS = {
  'hero': ['hero-cherries', 0.5, 0.5, 1200, 1500],
  'promo-discovery': ['beans-grid', 0.5, 0.5, 1200, 900],
  'promo-brew-kit': ['pourover-station', 0.5, 0.45, 1200, 900],
  'promo-wholesale': ['barista-bar', 0.55, 0.5, 1200, 900],
  'about-roaster': ['roaster', 0.5, 0.5, 1200, 900],
  'photo-cherries': ['cherries-picked', 0.5, 0.5, W, H],
  'photo-cherry-hand': ['cherry-hand', 0.5, 0.5, W, H],
  'photo-beans': ['beans-dark', 0.5, 0.5, W, H],
  'photo-beans-hands': ['beans-hands', 0.5, 0.55, W, H],
  'photo-beans-jar': ['beans-jar', 0.5, 0.5, W, H],
  'photo-latte': ['latte-1', 0.5, 0.5, W, H],
  'photo-latte-2': ['latte-2', 0.5, 0.5, W, H],
  'photo-iced': ['iced-latte', 0.5, 0.5, W, H],
  'photo-pour': ['barista-pour', 0.5, 0.5, W, H],
  'photo-roaster': ['roaster-drum', 0.5, 0.5, W, H],
  'eq-ceramic-dripper': ['paper-filter', 0.5, 0.5, W, H],
  'eq-flat-dripper': ['dripper-grinder', 0.42, 0.55, W, H],
  'eq-carafe': ['carafe', 0.6, 0.5, W, H],
  'eq-immersion': ['brew-kit-scale', 0.82, 0.5, W, H],
  'eq-moka': ['moka-pot', 0.5, 0.5, W, H],
  'eq-hand-grinder': ['dripper-grinder', 0.75, 0.5, W, H],
  'eq-electric-grinder': ['grinders-electric', 0.5, 0.5, W, H],
  'eq-gooseneck': ['kettle-steel', 0.5, 0.5, W, H],
  'eq-electric-kettle': ['copper-kettle', 0.5, 0.5, W, H],
  'eq-scale': ['brew-kit-scale', 0.5, 0.5, W, H],
  'eq-server': ['pourover-station', 0.62, 0.55, W, H],
  'eq-espresso': ['espresso-machine', 0.5, 0.5, W, H],
  'merch-mug': ['enamel-mug', 0.8, 0.55, W, H],
  'merch-tote': ['tote', 0.5, 0.5, W, H],
};
const GRADE = 'saturate(0.94) contrast(1.05) brightness(1.01) sepia(0.05)';

/* Which images each product gets. Coffees: label + a photo chosen by process + a brewing photo. */
const byProcess = (p) => (/natural|anaerobic|carbonic|ferment/i.test(p.process) ? 'photo-cherries' : /honey/i.test(p.process) ? 'photo-cherry-hand' : /wet-hulled|semi/i.test(p.process) ? 'photo-beans-hands' : p.kind === 'blend' ? 'photo-latte' : 'photo-beans');
const brewing = (p) => (/espresso|susu|house/i.test(p.slug) || p.roast === 'medium-dark' ? 'photo-latte-2' : p.roast === 'dark' ? 'photo-roaster' : 'photo-pour');
const EQUIP = {
  'ceramic-dripper-02': ['eq-ceramic-dripper'], 'flat-bottom-dripper': ['eq-flat-dripper'], 'glass-pour-over-carafe': ['eq-carafe'], 'immersion-brewer': ['eq-immersion'],
  'french-press-600': null, 'moka-pot-3': ['eq-moka'], 'hand-grinder-c3': ['eq-hand-grinder'], 'electric-grinder-flat': ['eq-electric-grinder'],
  'gooseneck-kettle': ['eq-gooseneck'], 'electric-kettle-temp': ['eq-electric-kettle'], 'brew-scale': ['eq-scale'], 'paper-filters-02': ['eq-ceramic-dripper'],
  'paper-filters-flat': ['eq-flat-dripper'], 'glass-server-600': ['eq-server'], 'enamel-mug': ['merch-mug'], 'canvas-tote': ['merch-tote'],
};

const jobs = []; // [name, svg] or [name, {photo}]
const images = {};
let n = 0;
for (const p of catalog.products) {
  if (p.type === 'coffee') {
    n++;
    jobs.push([`label-${p.slug}`, coffeeLabel(p, n)]);
    images[p.slug] = [`label-${p.slug}`, byProcess(p), brewing(p)];
  }
}
const T = (slug, spec) => {
  jobs.push([`tile-${slug}`, tile({ seed: slug, ...spec })]);
  return `tile-${slug}`;
};
for (const p of catalog.products) {
  if (p.category === 'drip-bags') images[p.slug] = [T(p.slug, { bg: ISLAND[p.source.startsWith('gayo') ? 'Sumatra' : p.source.startsWith('bali') ? 'Bali' : 'Blend'], kicker: 'DRIP BAGS · 10 × 12 G', title: 'Drip\nbags', sub: p.name.replace('Drip Bags · ', '').replace(' (10)', ''), foot: 'HANG · POUR 180 ML · WAIT · DRINK' }), 'photo-pour'];
  if (p.category === 'cold-brew') images[p.slug] = [T(p.slug, { bg: '#1F3A34', kicker: p.slug.endsWith('1l') ? '1 LITRE' : '500 ML', title: 'Cold\nbrew', sub: 'Concentrate · Java Cikuray · 18 hours', foot: 'KEEP CHILLED · MIX 1:2' }), 'photo-iced'];
  if (EQUIP[p.slug] !== undefined) images[p.slug] = EQUIP[p.slug] || [T(p.slug, { bg: '#ECE5D6', ink: '#141412', kicker: 'EQUIPMENT', title: 'French\npress', sub: '600 ml · double-wall steel' })];
}
images['lereng-tee'] = [T('lereng-tee', { bg: '#ECE5D6', ink: '#141412', kicker: 'MERCH · S–XL', title: 'Lereng\ntee', sub: 'Heavyweight cotton · contour print' })];
images['bucket-hat'] = [T('bucket-hat', { bg: '#2F4B3C', kicker: 'MERCH', title: 'Bucket\nhat', sub: 'Washed cotton · one size' })];
images['lereng-gift-card'] = [T('lereng-gift-card', { bg: '#B23A26', kicker: 'GIFT CARD', title: 'Lereng\ngift card', sub: 'Rp 100.000 to Rp 1.000.000 · never expires', foot: 'SENT BY EMAIL' })];
images['discovery-box'] = [T('discovery-box', { bg: '#141412', kicker: 'BUNDLE · 3 × 100 G', title: 'Discovery\nbox', sub: 'Any three single origins, 15% off', foot: 'SUMATRA · JAVA · BALI · FLORES · SULAWESI · PAPUA' }), 'promo-discovery'];
images['brew-starter-kit'] = ['promo-brew-kit', 'eq-ceramic-dripper', 'eq-server'];
images['office-pack'] = [T('office-pack', { bg: '#2B2A27', kicker: 'BUNDLE · 3 × 1 KG', title: 'Office\npack', sub: 'House Espresso, whole bean, 10% off' }), 'eq-espresso'];
images['roasters-choice-subscription'] = [T('roasters-choice', { bg: '#1F3A34', kicker: 'SUBSCRIPTION', title: "Roaster's\nchoice", sub: 'A different single origin every delivery', foot: 'EVERY 1, 2 OR 4 WEEKS · 10% OFF' }), 'photo-roaster'];

await rm(out, { recursive: true, force: true });
await mkdir(out, { recursive: true });
await mkdir(themeImg, { recursive: true });
const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', args: ['--allow-file-access-from-files'] });
const page = await browser.newPage();
await page.setViewport({ width: W, height: H });
// A real file page, so the local font files are allowed to load.
const host = `${out}_render.html`;
await writeFile(host, `<!doctype html><style>
  @font-face { font-family: Grotesk; src: url('${font('schibsted-grotesk.woff2')}') format('woff2'); font-weight: 400 900; }
  @font-face { font-family: Plex; src: url('${font('plex-mono-400.woff2')}') format('woff2'); }
  html, body { margin: 0; } svg { display: block; }
</style><div id="c"></div><span style="font-family:Grotesk;font-weight:800">.</span><span style="font-family:Plex">.</span>`);
await page.goto(pathToFileURL(host).href, { waitUntil: 'load' });
await page.evaluate(() => document.fonts.ready);
for (const [name, svg] of jobs) {
  await page.$eval('#c', (el, s) => (el.innerHTML = s), svg);
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({ path: `${out}${name}.jpg`, type: 'jpeg', quality: 86, clip: { x: 0, y: 0, width: W, height: H } });
  process.stdout.write('.');
}
await rm(host);
await page.goto(pathToFileURL(src).href);
for (const [name, [file, fx, fy, w, h]] of Object.entries(PHOTOS)) {
  const data = await page.evaluate(async ({ url, w, h, fx, fy, grade }) => {
    const img = new Image();
    img.src = url;
    await img.decode();
    const scale = Math.max(w / img.naturalWidth, h / img.naturalHeight);
    const sw = w / scale;
    const sh = h / scale;
    const sx = Math.min(Math.max(img.naturalWidth * fx - sw / 2, 0), img.naturalWidth - sw);
    const sy = Math.min(Math.max(img.naturalHeight * fy - sh / 2, 0), img.naturalHeight - sh);
    const c = Object.assign(document.createElement('canvas'), { width: w, height: h });
    const ctx = c.getContext('2d');
    ctx.filter = grade;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(img, sx, sy, sw, sh, 0, 0, w, h);
    return c.toDataURL('image/jpeg', 0.82);
  }, { url: pathToFileURL(`${src}${file}.jpg`).href, w, h, fx, fy, grade: GRADE });
  const target = name === 'hero' || name.startsWith('promo-') || name.startsWith('about-') ? `${themeImg}${name}.jpg` : `${out}${name}.jpg`;
  await writeFile(target, Buffer.from(data.split(',')[1], 'base64'));
  if (name.startsWith('promo-')) await writeFile(`${out}${name}.jpg`, Buffer.from(data.split(',')[1], 'base64'));
  process.stdout.write('·');
}
await browser.close();
await writeFile(new URL('data/images.json', root), JSON.stringify(images, null, 1));
console.log(`\n${jobs.length} labels and tiles, ${Object.keys(PHOTOS).length} photos, ${Object.keys(images).length} products mapped`);
