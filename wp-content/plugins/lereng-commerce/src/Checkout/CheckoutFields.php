<?php

namespace Lereng\Checkout;

use Lereng\Catalog\RoastSchedule;
use WC_Order;
use WP_Error;

/**
 * Checkout additions, using WooCommerce's Additional Checkout Fields API so they work in the
 * block checkout and the classic one, show in admin and emails, and save to the order:
 *  - a gift note we handwrite on a card
 *  - notes for the courier (gate code, landmark), part of the address
 *  - tax invoice (faktur pajak) with an NPWP, checked for 15 or 16 digits
 * Plus the roast date: worked out when the order is placed and shown on the thank-you page
 * and in the customer's emails.
 */
final class CheckoutFields
{
    public function register(): void
    {
        add_action('woocommerce_init', [$this, 'fields']);
        add_action('woocommerce_blocks_validate_location_contact_fields', [$this, 'requireNpwp'], 10, 3);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'roastDate']);
        add_action('woocommerce_checkout_order_created', [$this, 'roastDate']);
        add_action('woocommerce_thankyou', [$this, 'thankYou'], 4);
        add_action('woocommerce_email_before_order_table', [$this, 'email'], 5, 4);
    }

    public function fields(): void
    {
        if (!function_exists('woocommerce_register_additional_checkout_field')) {
            return;
        }
        woocommerce_register_additional_checkout_field([
            'id' => 'lereng/delivery-notes', 'label' => __('Notes for the courier (optional)', 'lereng'), 'optionalLabel' => __('Notes for the courier (optional)', 'lereng'),
            'location' => 'address', 'type' => 'text', 'required' => false,
            'attributes' => ['maxLength' => 120, 'placeholder' => __('Gate code, landmark, “leave with security”', 'lereng')],
        ]);
        woocommerce_register_additional_checkout_field([
            'id' => 'lereng/tax-invoice', 'label' => __('I need a tax invoice (faktur pajak)', 'lereng'),
            'location' => 'contact', 'type' => 'checkbox',
        ]);
        woocommerce_register_additional_checkout_field([
            'id' => 'lereng/npwp', 'label' => __('NPWP for the tax invoice', 'lereng'), 'optionalLabel' => __('NPWP for the tax invoice (if you need one)', 'lereng'),
            'location' => 'contact', 'type' => 'text', 'required' => false,
            'attributes' => ['inputmode' => 'numeric', 'maxLength' => 22],
            'sanitize_callback' => static fn($v) => preg_replace('/\D/', '', (string) $v),
            'validate_callback' => static function ($v) {
                $digits = preg_replace('/\D/', '', (string) $v);
                if ($digits !== '' && !in_array(strlen($digits), [15, 16], true)) {
                    return new WP_Error('lereng_npwp', __('An NPWP has 15 digits, or 16 for the new NIK-based format.', 'lereng'));
                }
                return true;
            },
        ]);
        woocommerce_register_additional_checkout_field([
            'id' => 'lereng/gift-note', 'label' => __('Gift note, handwritten on a card (optional)', 'lereng'), 'optionalLabel' => __('Gift note, handwritten on a card (optional)', 'lereng'),
            'location' => 'order', 'type' => 'text', 'required' => false, 'attributes' => ['maxLength' => 160],
        ]);
    }

    public function requireNpwp(WP_Error $errors, $fields, string $group): void
    {
        if (!empty($fields['lereng/tax-invoice']) && empty($fields['lereng/npwp'])) {
            $errors->add('lereng_npwp_required', __('Add your NPWP so we can issue the tax invoice.', 'lereng'));
        }
    }

    public static function preorderFloor(WC_Order $order): ?string
    {
        $floor = null;
        foreach ($order->get_items() as $item) {
            $p = $item->get_product();
            $id = $p ? ($p->get_parent_id() ?: $p->get_id()) : 0;
            $pre = $id ? get_post_meta($id, '_lereng_preorder', true) : null;
            if (!empty($pre['ships']) && (!$floor || $pre['ships'] > $floor)) {
                $floor = $pre['ships'];
            }
        }
        return $floor;
    }

    public static function hasCoffee(WC_Order $order): bool
    {
        foreach ($order->get_items() as $item) {
            $p = $item->get_product();
            if ($p && has_term(['coffee', 'bundles', 'subscriptions'], 'product_cat', $p->get_parent_id() ?: $p->get_id())) {
                return true;
            }
        }
        return false;
    }

    public function roastDate($order): void
    {
        $order = $order instanceof WC_Order ? $order : wc_get_order($order);
        if (!$order || !self::hasCoffee($order) || $order->get_meta('_lereng_roast_date')) {
            return;
        }
        $created = $order->get_date_created() ?: new \WC_DateTime();
        $date = RoastSchedule::nextRoast(new \DateTimeImmutable('@' . $created->getTimestamp()), self::preorderFloor($order));
        $order->update_meta_data('_lereng_roast_date', $date->format('Y-m-d'));
        $order->save();
    }

    public static function roastLine(WC_Order $order): ?string
    {
        $d = $order->get_meta('_lereng_roast_date');
        if (!$d) {
            return null;
        }
        $label = wp_date('l j F', strtotime($d . ' 12:00:00'));
        return $order->get_shipping_method() && str_contains($order->get_shipping_method(), 'Pick up')
            ? sprintf(__('We roast your coffee on %s; it is ready to collect from 14:00 that day.', 'lereng'), $label)
            : sprintf(__('We roast your coffee on %s and ship it the same afternoon.', 'lereng'), $label);
    }

    public function thankYou(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if ($order && ($line = self::roastLine($order))) {
            echo '<p class="roast-note">' . esc_html($line) . '</p>';
        }
    }

    public function email($order, bool $sentToAdmin, bool $plain, $email): void
    {
        if ($sentToAdmin || !$order instanceof WC_Order || !($line = self::roastLine($order))) {
            return;
        }
        echo $plain ? esc_html($line) . "\n\n" : '<p style="margin:0 0 16px;padding:12px 16px;background:#F6F4EF;border-left:3px solid #B23A26">' . esc_html($line) . '</p>';
    }
}
