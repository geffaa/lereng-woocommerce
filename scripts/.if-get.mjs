// Fetches a page on the InfinityFree site in Chrome (it answers bots with a JS cookie challenge).
import puppeteer from 'puppeteer-core';
const b = await puppeteer.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
const p = await b.newPage();
for (const u of process.argv.slice(2)) {
  try {
    await p.goto(u, { waitUntil: 'networkidle0', timeout: 120000 });
    if (/aes\.js|slowAES/.test(await p.content())) await p.waitForNavigation({ waitUntil: 'networkidle0', timeout: 60000 }).catch(() => {});
    console.log(`== ${u}\n` + (await p.evaluate(() => document.body.innerText)).slice(0, 3000));
  } catch (e) { console.log(`== ${u}\nERROR ${e.message}`); }
}
await b.close();
