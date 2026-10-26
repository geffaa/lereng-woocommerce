<?php

namespace Lereng\Promotions;

use WC_Coupon;

/** Coupon rules WooCommerce doesn't have: "first order only". */
final class Coupons
{
    public function register(): void
    {
        add_filter('woocommerce_coupon_is_valid', [$this, 'firstOrder'], 10, 3);
        add_action('woocommerce_coupon_options_usage_restriction', [$this, 'field'], 10, 2);
        add_action('woocommerce_coupon_options_save', [$this, 'save'], 10, 2);
    }

    public static function hasPaidOrders(string $email, int $customerId = 0): bool
    {
        $args = ['limit' => 1, 'return' => 'ids', 'status' => ['wc-processing', 'wc-completed', 'wc-roasting', 'wc-shipped']];
        if ($customerId && wc_get_orders($args + ['customer_id' => $customerId])) {
            return true;
        }
        return $email !== '' && (bool) wc_get_orders($args + ['billing_email' => $email]);
    }

    public function firstOrder(bool $valid, WC_Coupon $coupon, $discounts): bool
    {
        if (!$valid || $coupon->get_meta('_lereng_first_order') !== 'yes') {
            return $valid;
        }
        $customer = WC()->customer;
        $email = $customer ? (string) $customer->get_billing_email() : '';
        if (self::hasPaidOrders($email, get_current_user_id())) {
            throw new \Exception(esc_html__('This code is for your first order only.', 'lereng'), 109);
        }
        return true;
    }

    public function field(int $id, WC_Coupon $coupon): void
    {
        woocommerce_wp_checkbox(['id' => '_lereng_first_order', 'label' => __('First order only', 'lereng'), 'description' => __('Valid only for customers without a paid order (by account or billing email).', 'lereng'), 'value' => $coupon->get_meta('_lereng_first_order'), 'cbvalue' => 'yes']);
    }

    public function save(int $id, WC_Coupon $coupon): void
    {
        $coupon->update_meta_data('_lereng_first_order', isset($_POST['_lereng_first_order']) ? 'yes' : 'no'); // phpcs:ignore WordPress.Security.NonceVerification
        $coupon->save();
    }
}
