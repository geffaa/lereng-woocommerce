/** Gift card balance check (shortcode [lereng_gift_card_balance]). */
document.addEventListener('submit', async (e) => {
  const form = e.target.closest('[data-gc-balance]');
  if (!form) return;
  e.preventDefault();
  const out = form.querySelector('[data-result]');
  out.textContent = '…';
  const res = await fetch(`/wp-json/lereng/v1/gift-cards/balance?code=${encodeURIComponent(form.code.value)}`).catch(() => null);
  const data = res ? await res.json().catch(() => ({})) : {};
  out.innerHTML = res?.ok ? `<b>${data.code}</b>: ${data.balance_html} left of ${data.initial_html}` : (data.message || 'Something went wrong.');
});
