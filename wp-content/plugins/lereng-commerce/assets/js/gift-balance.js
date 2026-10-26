/** Gift card balance check (shortcode [lereng_gift_card_balance]). */
var lerengT = (s) => (window.lerengBase && window.lerengBase.i18n && window.lerengBase.i18n[s]) || s;
document.addEventListener('submit', async (e) => {
  const form = e.target.closest('[data-gc-balance]');
  if (!form) return;
  e.preventDefault();
  const out = form.querySelector('[data-result]');
  out.textContent = '…';
  const res = await fetch(`${(window.lerengBase && window.lerengBase.rest) || '/wp-json/'}lereng/v1/gift-cards/balance?code=${encodeURIComponent(form.code.value)}`).catch(() => null);
  const data = res ? await res.json().catch(() => ({})) : {};
  out.innerHTML = res?.ok ? `<b>${data.code}</b>: ` + lerengT('%1$s left of %2$s').replace('%1$s', data.balance_html).replace('%2$s', data.initial_html) : (data.message || lerengT('Something went wrong.'));
});
