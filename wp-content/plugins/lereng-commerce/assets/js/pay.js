/**
 * Payment page: draws the QRIS code, counts down the payment window, polls the order status and
 * moves on to the thank-you page once the gateway has confirmed. Sandbox buttons post to the
 * simulate endpoint, which sends a signed notification to the same webhook a gateway uses.
 */
(() => {
  const root = document.querySelector('[data-pay]');
  if (!root) return;
  const cfg = JSON.parse(root.dataset.pay);
  const status = root.querySelector('[data-status]');
  const q = (url) => `${url}${url.includes('?') ? '&' : '?'}key=${encodeURIComponent(cfg.key)}`;

  if (cfg.qr && window.qrcode) {
    const qr = window.qrcode(0, 'M');
    qr.addData(cfg.qr);
    qr.make();
    root.querySelector('[data-qr]').innerHTML = qr.createSvgTag({ cellSize: 6, margin: 2, scalable: true });
  }

  const countdown = root.querySelector('[data-countdown]');
  const tick = () => {
    const left = Math.max(0, cfg.expires - Math.floor(Date.now() / 1000));
    const h = Math.floor(left / 3600);
    const m = Math.floor((left % 3600) / 60);
    const s = left % 60;
    countdown.textContent = h ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}` : `${m}:${String(s).padStart(2, '0')}`;
    if (!left) check();
  };
  tick();
  const timer = setInterval(tick, 1000);

  const show = (state, text) => {
    root.dataset.state = state;
    status.lastChild.textContent = text;
  };
  async function check() {
    const res = await fetch(q(cfg.statusUrl), { cache: 'no-store' }).catch(() => null);
    if (!res?.ok) return;
    const data = await res.json();
    if (data.status === 'paid') {
      clearInterval(timer);
      clearInterval(poll);
      show('paid', 'Payment received. Taking you to your order…');
      setTimeout(() => location.assign(data.redirect), 900);
    } else if (data.status === 'expired' || data.status === 'failed') {
      clearInterval(timer);
      clearInterval(poll);
      show('expired', data.status === 'expired' ? 'This payment window has closed and the order was cancelled. Your items are back in stock.' : 'The payment was declined.');
    }
  }
  const poll = setInterval(check, 3000);

  root.addEventListener('click', async (e) => {
    const copy = e.target.closest('[data-copy]');
    if (copy) {
      await navigator.clipboard?.writeText(copy.dataset.copy).catch(() => {});
      copy.textContent = 'Copied';
      setTimeout(() => (copy.textContent = 'Copy'), 1500);
    }
    const sim = e.target.closest('[data-simulate]');
    if (sim) {
      sim.disabled = true;
      await fetch(q(cfg.simulateUrl), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status: sim.dataset.simulate }) });
      check();
    }
  });
})();
