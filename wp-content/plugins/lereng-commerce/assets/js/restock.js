/** Restock form: shows itself when the chosen variation is sold out, and posts the email. */
var lerengT = (s) => (window.lerengBase && window.lerengBase.i18n && window.lerengBase.i18n[s]) || s;
(() => {
  const form = document.querySelector('[data-restock]');
  if (!form) return;
  let variation = 0;
  if (window.jQuery) {
    const $form = window.jQuery('.variations_form');
    $form.on('found_variation', (e, v) => { variation = v.variation_id; form.hidden = v.is_in_stock; });
    $form.on('reset_data', () => { variation = 0; form.hidden = true; });
  }
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = form.querySelector('.restock__msg');
    const res = await fetch(`${(window.lerengBase && window.lerengBase.rest) || '/wp-json/'}lereng/v1/restock`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: form.email.value, product_id: Number(form.dataset.product), variation_id: variation }) }).catch(() => null);
    const data = res ? await res.json().catch(() => ({})) : {};
    msg.textContent = data.message || lerengT('Something went wrong.');
  });
})();
