import puppeteer from 'puppeteer-core';
const [out, ...paths] = process.argv.slice(2);
const b = await puppeteer.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
const p = await b.newPage();
for (const path of paths) {
  const [url, w] = path.split('@');
  await p.setViewport({ width: Number(w || 1440), height: 900 });
  await p.goto('http://127.0.0.1:9400' + url, { waitUntil: 'networkidle0' });
  await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 60)); } window.scrollTo(0, 0); });
  const name = (url.replace(/\W+/g, '-').replace(/^-|-$/g, '') || 'home') + '-' + (w || 1440);
  await p.screenshot({ path: `${out}/${name}.png`, fullPage: true });
}
await b.close();
