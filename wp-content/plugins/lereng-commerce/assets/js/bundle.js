/** Discovery Box: enforce exactly N picks and show the box price as they're chosen. */
(() => {
  const box = document.querySelector('[data-pick]');
  if (!box) return;
  const n = Number(box.dataset.pick);
  const discount = Number(box.dataset.discount) / 100;
  const sample = box.dataset.format; // "Rp 123.456" → separators and symbol
  const fmt = (v) => sample.replace(/[\d.,\s]+/, ' ').trim().replace(/^(\D*)$/, '$1') + ' ' + Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const button = box.closest('form').querySelector('[type=submit]');
  const update = () => {
    const on = [...box.querySelectorAll('input[type=checkbox]:checked')];
    box.querySelectorAll('input[type=checkbox]').forEach((i) => (i.disabled = !i.checked && on.length >= n));
    box.querySelector('[data-count]').textContent = `${on.length}/${n}`;
    const sum = on.reduce((s, i) => s + Number(i.dataset.price), 0);
    box.querySelector('[data-total]').textContent = on.length ? fmt(Math.round((sum * (1 - discount)) / 1000) * 1000) : '—';
    box.querySelector('[data-before]').textContent = on.length ? fmt(sum) : '';
    button.disabled = on.length !== n;
  };
  box.addEventListener('change', update);
  update();
})();
