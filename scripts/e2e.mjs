/**
 * End-to-end checks against the running store (node scripts/wp.mjs serve, seeded).
 *   node scripts/e2e.mjs            BASE=http://127.0.0.1:9400 by default
 * Each check runs in a fresh browser context, i.e. a new guest with an empty cart.
 */
import puppeteer from 'puppeteer-core';

const BASE = process.env.BASE || 'http://127.0.0.1:9400';
const only = process.argv[2];
const results = [];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const assert = (c, m) => {
  if (!c) throw new Error(m);
};
const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });

async function check(name, fn) {
  if (only && !name.includes(only)) return;
  const ctx = await browser.createBrowserContext();
  const page = await ctx.newPage();
  await page.setViewport({ width: 1366, height: 900 });
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  const t = Date.now();
  try {
    await Promise.race([fn(page, errors), sleep(180000).then(() => { throw new Error('timed out'); })]);
    assert(!errors.length, `script errors: ${errors.join(' | ')}`);
    results.push(true);
    console.log(`✓ ${name} (${((Date.now() - t) / 1000).toFixed(0)}s)`);
  } catch (e) {
    results.push(false);
    console.log(`✗ ${name}\n    ${e.message.split('\n')[0]}`);
    await page.screenshot({ path: `/tmp/e2e-fail-${results.length}.png`, fullPage: true }).catch(() => {});
  } finally {
    await ctx.close();
  }
}

/* ---------------- helpers ---------------- */

const text = (page, sel) => page.$eval(sel, (e) => e.textContent.replace(/\s+/g, ' ').trim());
const rupiah = (s) => Number(String(s).replace(/[^\d]/g, ''));

async function addCoffee(page, slug, size = '200g', grind = 'Whole bean', qty = 1) {
  await page.goto(`${BASE}/product/${slug}/`, { waitUntil: 'networkidle0' });
  for (const label of [size, grind]) {
    await page.evaluate((l) => [...document.querySelectorAll('.pill')].find((b) => b.textContent.trim() === l && b.getAttribute('aria-checked') !== 'true')?.click(), label);
    await sleep(150);
  }
  if (qty > 1) await page.$eval('form.cart input.qty', (i, q) => (i.value = q), qty);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.single_add_to_cart_button')]);
}

async function checkout(page, { city = 'Canggu', state = 'BA', method = 'lereng_qris', bank = null, coupon = null, email = `guest${Date.now()}@example.com`, ship = true, extra } = {}) {
  await page.goto(`${BASE}/checkout/`, { waitUntil: 'networkidle0' });
  await page.waitForSelector('.wc-block-checkout__shipping-method-option, #shipping-first_name, #billing-first_name', { timeout: 60000 });
  if (ship) await page.evaluate(() => [...document.querySelectorAll('.wc-block-checkout__shipping-method-option')].find((b) => /ship/i.test(b.textContent))?.click());
  const prefix = ship ? 'shipping' : 'billing';
  // A returning session shows the saved address as a summary card: open it for editing.
  await page.waitForFunction((p) => document.querySelector(`#${p}-first_name`) || document.querySelector('.wc-block-components-address-card__edit'), { timeout: 60000 }, prefix);
  if (!(await page.$(`#${prefix}-first_name`))) await page.click('.wc-block-components-address-card__edit');
  await page.waitForSelector(`#${prefix}-first_name`, { timeout: 60000 });
  const fill = async (sel, v) => {
    const el = await page.$(sel);
    if (!el) return;
    await el.click();
    await el.evaluate((i) => i.select());
    await page.keyboard.press('Backspace');
    await el.type(v);
  };
  await fill('#email', email);
  await fill(`#${prefix}-first_name`, 'Ana');
  await fill(`#${prefix}-last_name`, 'Putri');
  await fill(`#${prefix}-address_1`, 'Jl. Pantai Batu Bolong 12');
  await fill(`#${prefix}-city`, city);
  if (await page.$(`#${prefix}-state`)) await page.select(`#${prefix}-state`, state);
  await fill(`#${prefix}-postcode`, '80351');
  await fill(`#${prefix}-phone`, '081234567890');
  if (extra) await extra(page);
  if (coupon) {
    await page.click('.wc-block-components-totals-coupon .wc-block-components-panel__button');
    await page.waitForSelector('#wc-block-components-totals-coupon__input-coupon');
    await page.type('#wc-block-components-totals-coupon__input-coupon', coupon);
    await page.click('.wc-block-components-totals-coupon__button');
    await sleep(1500);
  }
  if (ship) await page.waitForFunction(() => document.querySelectorAll('.wc-block-components-shipping-rates-control .wc-block-components-radio-control__option').length > 0, { timeout: 60000 });
  await page.click(`label[for="radio-control-wc-payment-method-options-${method}"]`);
  if (bank) await page.evaluate((b) => [...document.querySelectorAll('.lereng-banks label')].find((l) => l.textContent.trim() === b)?.click(), bank);
  await page.waitForFunction(() => !document.querySelector('.wc-block-components-checkout-place-order-button')?.disabled && !document.querySelector('.wc-block-components-skeleton'), { timeout: 60000 });
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 90000 }), page.click('.wc-block-components-checkout-place-order-button')]);
}

async function pay(page, status = 'settlement') {
  assert(page.url().includes('/pay/'), `not on the payment page: ${page.url()}`);
  if (status === 'settlement') {
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 60000 }), page.click('[data-simulate="settlement"]')]);
  } else {
    await page.click(`[data-simulate="${status}"]`);
    await page.waitForFunction(() => document.querySelector('[data-pay]').dataset.state === 'expired', { timeout: 30000 });
  }
}

const orderIdFromUrl = (url) => Number(url.match(/order-received\/(\d+)/)?.[1]);
const api = async (page, path, opts) => page.evaluate(async (u, o) => { const r = await fetch(u, o); return { status: r.status, body: await r.json().catch(() => null) }; }, `${BASE}/wp-json${path}`, opts);

/* ---------------- checks ---------------- */

await check('shop: filters narrow the coffee list by process', async (page) => {
  await page.goto(`${BASE}/product-category/coffee/`, { waitUntil: 'networkidle0' });
  const all = await page.$$eval('.product-grid > li', (l) => l.length);
  await page.goto(`${BASE}/product-category/coffee/?filter_process=honey`, { waitUntil: 'networkidle0' });
  const honey = await page.$$eval('.card__title', (t) => t.map((x) => x.textContent.trim()));
  assert(honey.length > 0 && honey.length < all, `honey ${honey.length} of ${all}`);
  assert(honey.every((n) => /Honey/i.test(n)), honey.join(', '));
});

await check('product: size and grind pills pick the variation and its price', async (page) => {
  await page.goto(`${BASE}/product/gayo-wine-natural/`, { waitUntil: 'networkidle0' });
  await page.evaluate(() => [...document.querySelectorAll('.pill')].find((b) => b.textContent.trim() === '1kg').click());
  await sleep(400);
  assert(rupiah(await text(page, '.woocommerce-variation-price')) === 665000, await text(page, '.woocommerce-variation-price'));
  assert(await page.$eval('.coffee-facts', (e) => e.textContent.includes('Koperasi Tani Ketiara Atas')), 'facts sheet');
});

await check('checkout: Bali delivery rates, QRIS paid by webhook, order processing', async (page) => {
  await addCoffee(page, 'java-puntang-honey');
  await checkout(page, { method: 'lereng_qris' });
  assert(await page.$('[data-qr] svg'), 'no QR code on the payment page');
  await pay(page);
  assert(page.url().includes('order-received'), page.url());
  assert(/roast your coffee on/i.test(await text(page, '.roast-note')), 'roast date note');
});

await check('checkout: virtual account (BNI) shows a 16-digit number and pays', async (page) => {
  await addCoffee(page, 'kerinci-honey');
  await checkout(page, { method: 'lereng_va', bank: 'BNI', city: 'Bandung', state: 'JB' });
  const va = (await text(page, '[data-va]')).replace(/\s/g, '');
  assert(/^9881\d{12}$/.test(va), `va ${va}`);
  await pay(page);
  assert(page.url().includes('order-received'), page.url());
});

await check('payments: a forged webhook is refused, an expired one cancels the order', async (page) => {
  await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' });
  const forged = await api(page, '/lereng/v1/payments/notify', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: 'LRG1', status_code: '200', gross_amount: '1.00', transaction_status: 'settlement', signature_key: 'x' }) });
  assert(forged.status === 401, `forged → ${forged.status}`);
  await addCoffee(page, 'bali-kintamani-washed');
  await checkout(page, { method: 'lereng_qris' });
  await pay(page, 'expire');
  assert(/closed/i.test(await text(page, '[data-status]')), await text(page, '[data-status]'));
});

await check('shipping: cold brew is refused outside south Bali', async (page) => {
  await page.goto(`${BASE}/product/cold-brew-concentrate-500/`, { waitUntil: 'networkidle0' });
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.single_add_to_cart_button')]);
  await page.goto(`${BASE}/checkout/`, { waitUntil: 'networkidle0' });
  await page.waitForSelector('.wc-block-checkout__shipping-method-option');
  await page.evaluate(() => [...document.querySelectorAll('.wc-block-checkout__shipping-method-option')].find((b) => /ship/i.test(b.textContent))?.click());
  await page.waitForSelector('#shipping-city');
  await page.type('#shipping-city', 'Jakarta Selatan');
  await page.select('#shipping-state', 'JK');
  await page.type('#shipping-address_1', 'Jl. Kemang 1');
  await page.type('#shipping-postcode', '12730');
  await page.waitForFunction(() => /delivered chilled/.test(document.body.textContent), { timeout: 60000 });
});

await check('coupons: expired code is refused, BALI50 needs Rp 400.000', async (page) => {
  await addCoffee(page, 'gayo-washed');
  await page.goto(`${BASE}/cart/`, { waitUntil: 'networkidle0' });
  const tryCode = async (code) => {
    await page.waitForSelector('.wc-block-components-totals-coupon .wc-block-components-panel__button');
    if (!(await page.$('#wc-block-components-totals-coupon__input-coupon'))) await page.click('.wc-block-components-totals-coupon .wc-block-components-panel__button');
    await page.waitForSelector('#wc-block-components-totals-coupon__input-coupon');
    await page.$eval('#wc-block-components-totals-coupon__input-coupon', (i) => (i.value = ''));
    await page.type('#wc-block-components-totals-coupon__input-coupon', code);
    await page.click('.wc-block-components-totals-coupon__button');
    await sleep(2500);
    return page.evaluate(() => document.querySelector('.wc-block-components-totals-coupon .wc-block-components-validation-error, .wc-block-components-notice-banner')?.textContent || '');
  };
  assert(/expired/i.test(await tryCode('KOPIPAGI')), 'expired message');
  assert(/minimum/i.test(await tryCode('BALI50')), 'minimum spend message');
});

await check('gift card: bought, emailed to the recipient, then redeemed', async (page) => {
  await page.goto(`${BASE}/product/lereng-gift-card/`, { waitUntil: 'networkidle0' });
  await page.evaluate(() => [...document.querySelectorAll('.pill')].find((b) => b.textContent.trim() === 'Rp 250.000').click());
  await sleep(300);
  await page.type('[name=lereng_gc_recipient_name]', 'Maya');
  await page.type('[name=lereng_gc_recipient_email]', 'maya@example.com');
  await page.type('[name=lereng_gc_message]', 'Selamat ulang tahun!');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.single_add_to_cart_button')]);
  await checkout(page, { method: 'lereng_qris', ship: false });
  await pay(page);
  await page.goto(`${BASE}/demo-inbox/`, { waitUntil: 'networkidle0' });
  const link = await page.$$eval('a', (as) => as.find((a) => /sent you a Lereng gift card/.test(a.textContent))?.href);
  assert(link, 'gift card email not in the outbox');
  await page.goto(link, { waitUntil: 'networkidle0' });
  const code = (await page.evaluate(() => document.body.textContent.match(/LRG-[A-Z0-9]{4}-[A-Z0-9]{4}/)?.[0])) || '';
  assert(code, 'no code in the email');
  const before = await api(page, `/lereng/v1/gift-cards/balance?code=${code}`);
  assert(before.body.balance === 250000, JSON.stringify(before.body));
  await addCoffee(page, 'gayo-washed'); // 125.000
  await checkout(page, { method: 'lereng_qris', coupon: code });
  // Covered entirely by the gift card? The order total is 0, WooCommerce skips payment.
  if (page.url().includes('/pay/')) await pay(page);
  const after = await api(page, `/lereng/v1/gift-cards/balance?code=${code}`);
  assert(after.body.balance === 125000, `balance after ${JSON.stringify(after.body)}`);
});

await check('bundle: Discovery Box prices three 100 g bags less 15%', async (page) => {
  await page.goto(`${BASE}/product/discovery-box/`, { waitUntil: 'networkidle0' });
  const picks = await page.$$('.pick__item input');
  for (const i of [0, 1, 2]) await picks[i].click();
  const prices = await page.$$eval('.pick__item input', (els) => els.slice(0, 3).map((e) => Number(e.dataset.price)));
  const expected = Math.round((prices.reduce((a, b) => a + b, 0) * 0.85) / 1000) * 1000;
  assert(rupiah(await text(page, '[data-total]')) === expected, `${await text(page, '[data-total]')} vs ${expected}`);
  assert(await picks[3].evaluate((i) => i.disabled), 'a fourth pick is still allowed');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.single_add_to_cart_button')]);
  const cart = await api(page, '/wc/store/v1/cart');
  const line = cart.body.items.find((i) => i.name.startsWith('Discovery Box'));
  assert(Number(line.prices.price) === expected, `cart ${line.prices.price} vs ${expected}`);
  assert(line.item_data.some((d) => (d.name || d.key) === 'Coffees'), `picks missing from cart: ${JSON.stringify(line.item_data)}`);
});

await browser.close();
const passed = results.filter(Boolean).length;
console.log(`\n${passed}/${results.length} passed`);
process.exitCode = passed === results.length ? 0 : 1;
