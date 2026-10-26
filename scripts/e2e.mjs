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
  if (ship) await page.evaluate(() => [...document.querySelectorAll('.wc-block-checkout__shipping-method-option')].find((b) => /ship|kirim/i.test(b.textContent))?.click());
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

async function login(page, user = 'admin', pass = 'password') {
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'networkidle0' });
  await page.type('#user_login', user);
  await page.type('#user_pass', pass);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('#wp-submit')]);
}

async function inbox(page, pattern) {
  await page.goto(`${BASE}/demo-inbox/`, { waitUntil: 'networkidle0' });
  return page.$$eval('td a', (as, src) => as.filter((a) => new RegExp(src).test(a.textContent)).map((a) => a.href), pattern.source);
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

await check('subscription: subscribe, pay, skip, change, pause, renew and pay, cancel', async (page) => {
  await page.goto(`${BASE}/product/gayo-washed/?purchase=subscribe`, { waitUntil: 'networkidle0' });
  assert(await page.$eval('input[value="subscribe"]', (i) => i.checked), 'subscribe not preselected from the link');
  assert(rupiah(await text(page, '[data-sub]')) === 113000, `sub price ${await text(page, '[data-sub]')}`);
  await page.select('[name=lereng_interval]', '2');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.single_add_to_cart_button')]);
  const cart = await api(page, '/wc/store/v1/cart');
  const line = cart.body.items[0];
  assert(Number(line.prices.price) === 113000, `cart price ${line.prices.price}`);
  assert(line.item_data.some((d) => /every 2 weeks/.test(d.value)), JSON.stringify(line.item_data));
  const email = `sub${Date.now()}@example.com`;
  await checkout(page, { method: 'lereng_va', bank: 'BCA', email });
  await pay(page);
  // Paying created the account and signed the customer in.
  await page.goto(`${BASE}/my-account/subscriptions/`, { waitUntil: 'networkidle0' });
  assert(/Gayo Washed/.test(await text(page, '.subs-table')), 'subscription missing from My account');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.subs-table a.button')]);
  const next1 = await text(page, '.sub-view__next b');

  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('form.sub-form input[value="skip"] ~ button, form.sub-form:has(input[value="skip"]) button')]);
  const next2 = await text(page, '.sub-view__next b');
  assert(next1 !== next2, `skip did not move the date (${next1})`);

  await page.select('.sub-change select[name=interval]', '4');
  await page.select('.sub-change select[name=size]', '500g');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.sub-change button')]);
  assert(/every 4 weeks/.test(await text(page, '.sub-view__head')), await text(page, '.sub-view__head'));
  assert(/500g/.test(await text(page, '.sub-view__head')), 'size change');

  const until = new Date(Date.now() + 20 * 864e5).toISOString().slice(0, 10);
  await page.$eval('form.sub-form input[name=until]', (i, v) => (i.value = v), until);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('form.sub-form.is-inline button')]);
  assert(/Paused until/.test(await text(page, '.sub-view__head')), await text(page, '.sub-view__head'));
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('form.sub-form:has(input[value="resume"]) button')]);
  assert(/Active/.test(await text(page, '.sub-view__head')), 'resume');

  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('form.sub-form:has(input[value="renew_now"]) button')]);
  const payLink = await page.$$eval('.sub-timeline a', (as) => as.find((a) => a.textContent === 'pay')?.href);
  assert(payLink, 'renewal order with a pay link');
  await page.goto(payLink, { waitUntil: 'networkidle0' });
  // The renewal reuses the bank chosen at the first checkout (BCA).
  assert(/^12345\d{11}$/.test((await text(page, '[data-va]')).replace(/\s/g, '')), `renewal VA ${await text(page, '[data-va]')}`);
  await pay(page);
  await page.goto(`${BASE}/my-account/subscriptions/`, { waitUntil: 'networkidle0' });
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.subs-table a.button')]);
  const timeline = await text(page, '.sub-timeline');
  for (const ev of ['Started', 'Skipped', 'Changed', 'Paused', 'Resumed', 'Renewal ordered', 'Renewal paid']) assert(timeline.includes(ev), `timeline lacks ${ev}`);

  await page.click('.sub-cancel summary');
  await page.select('.sub-cancel select[name=reason]', 'too_much');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.sub-cancel button')]);
  assert(/Cancelled/.test(await text(page, '.sub-view__head')), 'cancel');
  await page.goto(`${BASE}/demo-inbox/`, { waitUntil: 'networkidle0' });
  const subjects = await page.$$eval('td a', (as) => as.map((a) => a.textContent));
  assert(subjects.some((s) => /subscription is on/.test(s)) && subjects.some((s) => /next Lereng delivery/.test(s)), 'subscription emails');
});

await check('fulfilment: roast list, ship with tracking, tracking page, invoice', async (page) => {
  await addCoffee(page, 'mamasa-natural', '500g', 'Pour over');
  const email = `track${Date.now()}@example.com`;
  await checkout(page, { method: 'lereng_qris', email, city: 'Bandung', state: 'JB' });
  await pay(page);
  const orderId = orderIdFromUrl(page.url());
  const invoiceUrl = await page.$eval('.invoice-link a', (a) => a.href);
  await page.goto(invoiceUrl, { waitUntil: 'networkidle0' });
  const inv = await text(page, '.sheet');
  assert(/PPN 11%/.test(inv) && /DPP/.test(inv) && /PAID/.test(inv), 'invoice content');

  const admin = await browser.createBrowserContext();
  const ap = await admin.newPage();
  await login(ap);
  await ap.goto(`${BASE}/wp-admin/admin.php?page=lereng-roast-list`, { waitUntil: 'networkidle0' });
  const date = await ap.evaluate((id) => [...document.querySelectorAll('.wrap .button')].map((b) => b.href).find(Boolean), orderId);
  assert(/Mamasa Natural/.test(await text(ap, '.wrap table')), 'coffee missing from the roast list');
  await Promise.all([ap.waitForNavigation({ waitUntil: 'networkidle0' }), ap.click('form button.button-primary')]);
  await ap.goto(`${BASE}/wp-admin/admin.php?page=wc-orders&action=edit&id=${orderId}`, { waitUntil: 'networkidle0' });
  assert(/Roasting/.test(await ap.$eval('#order_status', (s) => s.selectedOptions[0].textContent)), 'order not roasting');
  const placeholder = await ap.$eval('[name=lereng_tracking]', (i) => i.placeholder);
  await ap.type('[name=lereng_tracking]', placeholder);
  await Promise.all([ap.waitForNavigation({ waitUntil: 'networkidle0' }), ap.click('button.save_order')]);
  assert(/Shipped/.test(await ap.$eval('#order_status', (s) => s.selectedOptions[0].textContent)), 'order not shipped');
  await admin.close();

  await page.goto(`${BASE}/track-order/`, { waitUntil: 'networkidle0' });
  await page.type('[name=order]', String(orderId));
  await page.type('[name=email]', email);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.track-form button')]);
  const track = await text(page, '.track');
  assert(track.includes(placeholder) && /Shipment received by courier/.test(track), track.slice(0, 200));
  assert((await inbox(page, /on its way/)).length > 0, 'shipped email');
});

await check('restock: a sold-out grinder takes an email, restocking sends the alert', async (page) => {
  const admin = await browser.createBrowserContext();
  const ap = await admin.newPage();
  await login(ap);
  await ap.goto(`${BASE}/wp-admin/`, { waitUntil: 'domcontentloaded' });
  // Stock changes through WooCommerce's REST API as staff, the way a stock sync would.
  const setStock = (id, qty) => ap.evaluate(async (u, q) => {
    const nonce = await (await fetch('/wp-admin/admin-ajax.php?action=rest-nonce')).text();
    const r = await fetch(u, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify({ stock_quantity: q }) });
    return r.status;
  }, `${BASE}/wp-json/wc/v3/products/${id}`, qty);
  await page.goto(`${BASE}/product/electric-grinder-flat/`, { waitUntil: 'networkidle0' });
  const id = await page.$eval('[data-restock]', (f) => f.dataset.product);
  assert((await setStock(id, 0)) === 200, 'set sold out');
  await page.reload({ waitUntil: 'networkidle0' });
  await page.waitForSelector('[data-restock]:not([hidden])');
  await page.type('[data-restock] [name=email]', 'waiting@example.com');
  await page.click('[data-restock] button');
  await page.waitForFunction(() => /once when it.s back/i.test(document.querySelector('.restock__msg').textContent));
  assert((await setStock(id, 5)) === 200, 'restock via REST');
  assert((await inbox(ap, /Flat Burr is back/)).length > 0, 'restock email');
  await setStock(id, 0); // sold out again for the next run
  await admin.close();
});

await check('wishlist: saved as a guest, merged into the account on sign-in', async (page) => {
  await page.goto(`${BASE}/product-category/coffee/`, { waitUntil: 'networkidle0' });
  const id = await page.$eval('.card [data-wish]', (b) => b.dataset.wish);
  await page.click('.card [data-wish]');
  assert(await page.$eval('.card [data-wish]', (b) => b.getAttribute('aria-pressed') === 'true'), 'heart not on');
  await login(page);
  await page.goto(`${BASE}/my-account/wishlist/`, { waitUntil: 'networkidle0' });
  await page.waitForFunction(() => document.querySelector('.wish-list') || /Nothing saved/.test(document.body.textContent));
  await page.reload({ waitUntil: 'networkidle0' });
  assert(await page.$(`.wish-list [data-wish="${id}"]`), 'guest wishlist not merged into the account');
  await page.click(`.wish-list [data-wish="${id}"]`); // clean up
  await sleep(800);
});

await check('wholesale: register, apply, approval, quick order at wholesale prices, invoice payment', async (page) => {
  const email = `cafe${Date.now()}@example.com`;
  await page.goto(`${BASE}/my-account/`, { waitUntil: 'networkidle0' });
  await page.type('#reg_email', email);
  await page.type('#reg_password', 'Kopi-Lereng-2026!');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('[name=register]')]);
  await page.goto(`${BASE}/wholesale/`, { waitUntil: 'networkidle0' });
  await page.type('.ws-form [name=business]', 'Kedai Uji Coba');
  await page.type('.ws-form [name=contact]', 'Rani');
  await page.type('.ws-form [name=email]', email);
  await page.type('.ws-form [name=city]', 'Denpasar');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.ws-form button')]);
  assert(/Application received/.test(await page.evaluate(() => document.body.textContent)), 'application');

  const admin = await browser.createBrowserContext();
  const ap = await admin.newPage();
  await login(ap);
  await ap.goto(`${BASE}/wp-admin/admin.php?page=lereng-wholesale`, { waitUntil: 'networkidle0' });
  const row = await ap.$$eval('.wrap tbody tr', (rows, e) => rows.findIndex((r) => r.textContent.includes(e)), email);
  const forms = await ap.$$(`.wrap tbody tr:nth-child(${row + 1}) form`);
  await Promise.all([ap.waitForNavigation({ waitUntil: 'networkidle0' }), forms[0].$eval('button', (b) => b.click())]);
  await admin.close();

  await page.goto(`${BASE}/my-account/quick-order/`, { waitUntil: 'networkidle0' });
  const first = await page.$eval('.quick-order tbody tr', (tr) => ({ name: tr.querySelector('td').textContent, list: tr.children[1].textContent, ws: tr.children[2].textContent }));
  await page.$eval('.quick-order tbody tr input[type=number]', (i) => (i.value = '6'));
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.quick-order button')]);
  const cart = await api(page, '/wc/store/v1/cart');
  const line = cart.body.items[0];
  assert(line.quantity === 6 && Number(line.prices.price) === rupiah(first.ws), `cart ${line.quantity} × ${line.prices.price} vs ${first.ws}`);
  await page.goto(`${BASE}/checkout/`, { waitUntil: 'networkidle0' });
  await page.waitForSelector('label[for="radio-control-wc-payment-method-options-bacs"]', { timeout: 60000 });
});

await browser.close();
await check('newsletter: sign-up keeps the address and sends the welcome code', async (page) => {
  const email = `news${Date.now()}@example.com`;
  await page.goto(`${BASE}/`, { waitUntil: 'networkidle0' });
  await page.type('.newsletter input[type=email]', email);
  await page.click('.newsletter button');
  await page.waitForFunction(() => /you.re in/i.test(document.querySelector('.newsletter__msg')?.textContent || ''), { timeout: 30000 });
  const again = await api(page, '/lereng/v1/newsletter', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email }) });
  assert(again.status === 200 && /already/i.test(again.body.message), 'second sign-up is recognised');
  const bad = await api(page, '/lereng/v1/newsletter', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: 'nope' }) });
  assert(bad.status === 422, 'invalid email refused');
  assert((await inbox(page, /Welcome to Lereng/)).length > 0, 'welcome email');
});

await check('language: Bahasa Indonesia storefront, checkout, payment page and order email', async (page) => {
  await page.goto(`${BASE}/?lang=id`, { waitUntil: 'networkidle0' });
  assert((await page.$eval('html', (h) => h.lang)) === 'id', 'html lang');
  assert(/Panduan seduh/.test(await text(page, '.site-nav')), 'navigation in Indonesian');
  assert(!page.url().includes('lang='), 'the switch leaves a clean URL');
  await page.goto(`${BASE}/product/gayo-wine-natural/`, { waitUntil: 'networkidle0' });
  const facts = await text(page, '.coffee-facts');
  assert(/Cara kami menyeduhnya/.test(facts) && /para-para/.test(facts), 'coffee facts and the farm story in Indonesian');
  assert(/Anggur merah/.test(await text(page, '.notes-chips')), 'tasting notes in Indonesian');
  await addCoffee(page, 'gayo-wine-natural', '200g', 'Biji utuh');
  const email = `bahasa${Date.now()}@example.com`;
  await checkout(page, { method: 'lereng_qris', email });
  assert(/Pindai untuk bayar/.test(await page.$eval('body', (b) => b.textContent)), 'payment page in Indonesian');
  await pay(page);
  assert(/Kami menyangrai kopi Anda/.test(await text(page, '.roast-note')), 'roast note in Indonesian');
  const id = orderIdFromUrl(page.url());
  await page.goto(`${BASE}/brew-guides/`, { waitUntil: 'networkidle0' });
  assert(/Giling sedang-halus/.test(await page.$eval('main', (m) => m.textContent)), 'page content in Indonesian');
  await page.goto(`${BASE}/demo-inbox/`, { waitUntil: 'networkidle0' });
  const row = await page.$$eval('tr', (rows, e) => rows.map((r) => r.textContent).find((t) => t.includes(e.split('@')[0].slice(0, 6)) || t.includes(e)), email);
  assert(row && !/Your .* order has been received/i.test(row), `order email subject is Indonesian: ${row}`);
  await page.goto(`${BASE}/?lang=en`, { waitUntil: 'networkidle0' });
  assert((await page.$eval('html', (h) => h.lang)) === 'en-US', 'back to English');
  assert(id > 0, 'order placed');
});

const passed = results.filter(Boolean).length;
console.log(`\n${passed}/${results.length} passed`);
process.exitCode = passed === results.length ? 0 : 1;
