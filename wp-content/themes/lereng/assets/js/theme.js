/**
 * Lereng theme behaviour (no dependencies).
 * - Variation dropdowns become pill buttons. The <select> stays in the DOM (hidden) so
 *   WooCommerce's own variation script keeps doing the pricing and stock work.
 * - Newsletter form posts to the store's REST endpoint.
 */
(() => {
  function pills(select) {
    if (select.dataset.pills) return;
    select.dataset.pills = '1';
    const group = document.createElement('div');
    group.className = 'pills';
    group.setAttribute('role', 'radiogroup');
    group.setAttribute('aria-label', select.closest('tr')?.querySelector('label')?.textContent || select.name);
    const render = () => {
      group.innerHTML = '';
      [...select.options].filter((o) => o.value).forEach((o) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'pill';
        b.textContent = o.textContent;
        b.setAttribute('role', 'radio');
        b.setAttribute('aria-checked', String(select.value === o.value));
        b.disabled = o.disabled;
        b.addEventListener('click', () => {
          select.value = select.value === o.value ? '' : o.value;
          select.dispatchEvent(new Event('change', { bubbles: true }));
          // jQuery listeners (WooCommerce) need a jQuery-triggered change too.
          if (window.jQuery) window.jQuery(select).trigger('change');
        });
        group.append(b);
      });
    };
    select.classList.add('pills-source');
    select.after(group);
    render();
    // WooCommerce rewrites the options when another attribute changes; follow it.
    new MutationObserver(render).observe(select, { childList: true, subtree: true, attributes: true });
    select.addEventListener('change', render);
    if (window.jQuery) window.jQuery(select.form).on('woocommerce_update_variation_values reset_data', render);
  }

  const init = () => document.querySelectorAll('.variations_form select').forEach(pills);
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();

  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('[data-newsletter]');
    if (!form) return;
    e.preventDefault();
    const msg = form.querySelector('.newsletter__msg');
    const res = await fetch('/wp-json/lereng/v1/newsletter', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: form.email.value }) }).catch(() => null);
    const data = res ? await res.json().catch(() => ({})) : {};
    msg.textContent = data.message || 'Something went wrong. Please try again.';
    msg.hidden = false;
    if (res?.ok) form.reset();
  });
})();
