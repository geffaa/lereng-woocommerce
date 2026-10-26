/**
 * Registers the sandbox QRIS and virtual account gateways with the block checkout.
 * The virtual account method asks for a bank and passes it as payment data (lereng_bank),
 * which WooCommerce hands to the gateway's process_payment().
 */
(() => {
  const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
  const { getSetting } = window.wc.wcSettings;
  const { createElement: h, useEffect, useState } = window.wp.element;
  const { decodeEntities } = window.wp.htmlEntities;

  const register = (name) => {
    const s = getSetting(`${name}_data`, null);
    if (!s) return;
    const Label = () => h('span', { className: `lereng-pm lereng-pm--${name}` }, decodeEntities(s.title));
    const Content = (props) => {
      const [bank, setBank] = useState(s.banks ? s.banks[0].id : null);
      const { onPaymentSetup } = props.eventRegistration;
      useEffect(() => onPaymentSetup(() => ({
        type: props.emitResponse.responseTypes.SUCCESS,
        meta: { paymentMethodData: bank ? { lereng_bank: bank } : {} },
      })), [onPaymentSetup, bank]);
      const desc = h('p', { className: 'lereng-pm__desc' }, decodeEntities(s.description));
      if (!s.banks) return desc;
      return h('div', null, desc, h('div', { className: 'lereng-banks', role: 'radiogroup', 'aria-label': 'Bank' },
        s.banks.map((b) => h('label', { key: b.id, className: bank === b.id ? 'is-selected' : '' },
          h('input', { type: 'radio', name: 'lereng_bank', value: b.id, checked: bank === b.id, onChange: () => setBank(b.id) }),
          ' ', b.name))));
    };
    registerPaymentMethod({
      name,
      label: h(Label),
      content: h(Content),
      edit: h(Content),
      canMakePayment: () => true,
      ariaLabel: decodeEntities(s.title),
      supports: { features: s.supports || ['products'] },
    });
  };
  ['lereng_qris', 'lereng_va'].forEach(register);
})();
