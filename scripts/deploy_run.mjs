/**
 * Drives the one-off deploy script on the host, one step per request (see deploy_host.py).
 * Runs in Chrome because free hosts answer non-browser clients with a JavaScript cookie check.
 *   node scripts/deploy_run.mjs https://example.rf.gd
 */
import puppeteer from 'puppeteer-core';
import { readFileSync } from 'node:fs';

const base = process.argv[2].replace(/\/$/, '');
const meta = JSON.parse(readFileSync(new URL('../.wp/deploy/meta.json', import.meta.url)));
const steps = [
  ...Array.from({ length: meta.zips }, (_, i) => `step=unzip&part=${i + 1}`),
  ...Array.from({ length: meta.sql }, (_, i) => `step=sql&part=${i + 1}`),
  'step=finish',
];
const b = await puppeteer.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
const p = await b.newPage();
for (const q of steps) {
  const url = `${base}/deploy-${meta.token}.php?${q}`;
  let text = '';
  for (let attempt = 0; attempt < 4 && !/^ok|^skip/.test(text); attempt++) {
    const res = await p.goto(url, { waitUntil: 'networkidle0', timeout: 120000 }).catch((e) => ({ status: () => 0, e }));
    text = (await p.evaluate(() => document.body?.innerText || '').catch(() => '')).trim();
    if (/aes\.js|slowAES|Checking your browser/i.test(await p.content())) { await new Promise((r) => setTimeout(r, 4000)); text = ''; continue; }
    if (!/^ok|^skip/.test(text)) console.log(`  retry ${q} (${res.status?.()}): ${text.slice(0, 200)}`);
  }
  console.log(`${q} → ${text.slice(0, 300)}`);
  if (!/^ok|^skip/.test(text)) { await b.close(); process.exit(1); }
}
await b.close();
