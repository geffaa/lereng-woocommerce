<?php

namespace Lereng\Shipping;

use WC_Shipping_Method;

/**
 * "Courier" shipping method: one WooCommerce method that offers every service the roastery
 * uses (SiCepat, JNE, GoSend same-day), priced by RateTable. Add it to a shipping zone covering
 * Indonesia; pickup at the roastery is WooCommerce's own Local Pickup.
 */
final class CourierMethod extends WC_Shipping_Method
{
    public const ID = 'lereng_courier';
    public const CHILLED_CLASS = 'chilled-bali-only';

    public function __construct($instance_id = 0)
    {
        $this->id = self::ID;
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Lereng couriers', 'lereng');
        $this->method_description = __('JNE, SiCepat and GoSend same-day rates from the roastery in Bali, by destination province and weight.', 'lereng');
        $this->supports = ['shipping-zones', 'instance-settings'];
        $this->instance_form_fields = [
            'title' => ['title' => __('Title', 'lereng'), 'type' => 'text', 'default' => __('Courier', 'lereng')],
        ];
        $this->title = $this->get_option('title', __('Courier', 'lereng'));
        $this->tax_status = 'taxable';
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }

    public function calculate_shipping($package = []): void
    {
        $dest = $package['destination'] ?? [];
        if (($dest['country'] ?? '') !== 'ID') {
            return;
        }
        $grams = 0;
        $chilled = false;
        foreach ($package['contents'] ?? [] as $item) {
            $product = $item['data'];
            if (!$product->needs_shipping()) {
                continue;
            }
            $grams += (int) round((float) wc_get_weight((float) $product->get_weight(), 'g') * $item['quantity']);
            $chilled = $chilled || $product->get_shipping_class() === self::CHILLED_CLASS;
        }
        $subtotal = (int) round(WC()->cart ? (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_subtotal_tax() : (float) ($package['contents_cost'] ?? 0));
        foreach (RateTable::quote((string) ($dest['state'] ?? ''), (string) ($dest['city'] ?? ''), $grams, $subtotal, $chilled) as $r) {
            $this->add_rate([
                'id' => $this->get_rate_id($r['id']),
                'label' => sprintf('%s · %s', $r['label'], RateTable::eta($r['days'])),
                // Shelf prices include PPN; courier fees are quoted the same way, so take the tax back out.
                'cost' => $r['cost'] / 1.11,
                'package' => $package,
                'meta_data' => ['service' => $r['id'], 'eta' => RateTable::eta($r['days'])],
            ]);
        }
    }
}
