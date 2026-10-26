/**
 * Wishlist hearts. Guests: localStorage. Signed in: the account, via REST. On the first signed-in
 * page view, whatever a guest saved is merged into the account.
 */
(() => {
  const cfg = window.lerengWishlist || {};
  const KEY = 'lereng-wishlist';
  const local = () => { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch { return []; } };
  const saveLocal = (ids) => { try { localStorage.setItem(KEY, JSON.stringify(ids)); } catch {} };
  let ids = local();
  const paint = () => document.querySelectorAll('[data-wish]').forEach((b) => {
    const on = ids.includes(Number(b.dataset.wish));
    b.setAttribute('aria-pressed', String(on));
    b.classList.toggle('is-on', on);
  });
  const send = (body) => fetch(cfg.url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce }, body: JSON.stringify(body) }).then((r) => r.json());
  (async () => {
    if (cfg.loggedIn) {
      const guest = local();
      const data = guest.length ? await send({ ids: guest, merge: true }) : await (await fetch(cfg.url, { headers: { 'X-WP-Nonce': cfg.nonce } })).json();
      ids = data.ids || [];
      saveLocal([]);
    }
    paint();
  })();
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-wish]');
    if (!b) return;
    e.preventDefault();
    const id = Number(b.dataset.wish);
    ids = ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id];
    paint();
    if (cfg.loggedIn) ids = (await send({ ids })).ids || ids;
    else saveLocal(ids);
    if (b.closest('.wish-list') && !ids.includes(id)) b.closest('li').remove();
  });
})();
