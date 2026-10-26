/**
 * Product page purchase options: shows the subscription price for the chosen variation and
 * hides the frequency picker for one-time purchases.
 */
(() => {
  const box = document.querySelector('.purchase');
  if (!box) return;
  const discount = Number(box.dataset.discount) / 100;
  const sample = box.dataset.format;
  const fmt = (v) => sample.replace(/[\d.,]+/, Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
  const sync = () => {
    const sub = box.querySelector('input[value="subscribe"]').checked;
    box.dataset.mode = sub ? 'subscribe' : 'once';
    box.querySelector('.purchase__freq select').disabled = !sub;
  };
  box.addEventListener('change', sync);
  sync();
  if (window.jQuery) {
    window.jQuery('.variations_form').on('found_variation', (e, v) => {
      const once = box.querySelector('[data-once]');
      if (once) once.textContent = fmt(v.display_regular_price);
      box.querySelector('[data-sub]').textContent = fmt(Math.round((v.display_regular_price * (1 - discount)) / 1000) * 1000);
    });
  }
})();
