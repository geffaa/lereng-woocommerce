<?php

namespace Lereng\Payments;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/** Makes a sandbox gateway selectable in the block checkout. */
final class BlocksIntegration extends AbstractPaymentMethodType
{
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function initialize(): void
    {
        $this->settings = get_option("woocommerce_{$this->name}_settings", []);
    }

    public function is_active(): bool
    {
        $gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
        return isset($gateways[$this->name]) && $gateways[$this->name]->enabled === 'yes';
    }

    public function get_payment_method_script_handles(): array
    {
        wp_register_script('lereng-payment-blocks', LERENG_URL . 'assets/js/payment-blocks.js', ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'], LERENG_VERSION, true);
        return ['lereng-payment-blocks'];
    }

    public function get_payment_method_data(): array
    {
        $gateway = WC()->payment_gateways()->payment_gateways()[$this->name] ?? null;
        $data = [
            'title' => $gateway ? $gateway->get_title() : $this->name,
            'description' => $gateway ? $gateway->get_description() : '',
            'supports' => $gateway ? array_values($gateway->supports) : ['products'],
        ];
        if ($this->name === 'lereng_va') {
            $data['banks'] = array_map(static fn($id, $b) => ['id' => $id, 'name' => $b[0]], array_keys(VirtualAccount::BANKS), VirtualAccount::BANKS);
        }
        return $data;
    }
}
