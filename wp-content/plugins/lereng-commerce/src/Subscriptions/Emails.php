<?php

namespace Lereng\Subscriptions;

use Lereng\Payments\Payments;
use WC_Order;

/** Subscription emails, in the store's WooCommerce email template. */
final class Emails
{
    private static function send(int $customerId, string $subject, string $body): void
    {
        $user = get_userdata($customerId);
        if (!$user) {
            return;
        }
        WC()->mailer()->send($user->user_email, $subject, WC()->mailer()->wrap_message($subject, $body));
    }

    private static function name(array $sub): string
    {
        $p = wc_get_product($sub['variation_id'] ?: $sub['product_id']);
        return $p ? wp_strip_all_tags($p->get_name()) : __('your coffee', 'lereng');
    }

    private static function manageLink(array $sub): string
    {
        return '<p><a href="' . esc_url(wc_get_account_endpoint_url('view-subscription') . $sub['id'] . '/') . '">' . esc_html__('Manage your subscription', 'lereng') . '</a> · ' . esc_html__('skip, pause, change coffee or cancel', 'lereng') . '</p>';
    }

    public static function started(?array $sub): void
    {
        if (!$sub) {
            return;
        }
        $body = '<p>' . sprintf(esc_html__('Your subscription to %1$s is on: %2$s, %3$d%% off every bag.', 'lereng'), '<b>' . esc_html(self::name($sub)) . '</b>', esc_html(Subscriptions::intervalLabel($sub['interval_weeks'])), (int) $sub['discount_pct']) . '</p>'
            . '<p>' . sprintf(esc_html__('Next delivery: roasted on %s.', 'lereng'), '<b>' . esc_html(wp_date('l j F', strtotime($sub['next_date']))) . '</b>') . ' ' . esc_html__("Two days before, we'll email you a payment link for that delivery.", 'lereng') . '</p>'
            . self::manageLink($sub);
        self::send($sub['customer_id'], __('Your Lereng subscription is on', 'lereng'), $body);
    }

    public static function renewal(?array $sub, WC_Order $order): void
    {
        if (!$sub) {
            return;
        }
        $txn = (array) $order->get_meta('_lereng_payment');
        $body = '<p>' . sprintf(esc_html__('Your next bag of %1$s is roasting on %2$s.', 'lereng'), '<b>' . esc_html(self::name($sub)) . '</b>', '<b>' . esc_html(wp_date('l j F', strtotime($order->get_meta('_lereng_renewal_for')))) . '</b>') . '</p>'
            . '<p style="font-size:20px;margin:16px 0"><b>' . wp_kses_post(wc_price($order->get_total())) . '</b></p>'
            . '<p><a href="' . esc_url(Payments::payUrl($order)) . '" style="display:inline-block;background:#141412;color:#fff;padding:12px 20px;text-decoration:none">' . esc_html__('Pay for this delivery', 'lereng') . '</a></p>'
            . (!empty($txn['va']) ? '<p>' . sprintf(esc_html__('Or transfer to your %1$s virtual account %2$s.', 'lereng'), esc_html(strtoupper($txn['bank'] ?? '')), '<b>' . esc_html(\Lereng\Payments\VirtualAccount::format($txn['va'])) . '</b>') . '</p>' : '')
            . '<p>' . esc_html__('Not paid by 10:00 on roast day? We skip this delivery and keep your subscription.', 'lereng') . '</p>'
            . self::manageLink($sub);
        self::send($sub['customer_id'], sprintf(__('Your next Lereng delivery: order #%s', 'lereng'), $order->get_order_number()), $body);
    }

    public static function changed(array $sub, string $what): void
    {
        self::send($sub['customer_id'], __('Your Lereng subscription was updated', 'lereng'), '<p>' . esc_html($what) . '</p>' . self::manageLink($sub));
    }
}
