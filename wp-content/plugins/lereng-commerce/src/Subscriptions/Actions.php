<?php

namespace Lereng\Subscriptions;

use WP_Error;

/** The changes a customer (or staff) can make to a subscription, in one place. */
final class Actions
{
    /** @return string|WP_Error confirmation message */
    public static function apply(array $sub, string $action, array $in, string $by)
    {
        $today = Subscriptions::today();
        $id = $sub['id'];
        switch ($action) {
            case 'skip':
                if ($sub['status'] !== 'active') {
                    return new WP_Error('state', __('Only active subscriptions can skip a delivery.', 'lereng'));
                }
                $next = Schedule::skip($sub['next_date'], $sub['interval_weeks']);
                Repository::update($id, ['next_date' => $next, 'skipped' => $sub['skipped'] + 1], 'skipped', sprintf('%s → %s (%s)', wp_date('j M', strtotime($sub['next_date'])), wp_date('j M', strtotime($next)), $by));
                return sprintf(__('Skipped. Your next delivery is roasted on %s.', 'lereng'), wp_date('l j F', strtotime($next)));

            case 'renew_now':
                if ($sub['status'] !== 'active') {
                    return new WP_Error('state', __('Resume the subscription first.', 'lereng'));
                }
                $order = Subscriptions::renew(['next_date' => Schedule::roastDayOnOrAfter(wp_date('Y-m-d', strtotime('+' . Schedule::LEAD_DAYS . ' days')))] + $sub);
                return $order ? sprintf(__('Order #%s created for the next roast day. We emailed you the payment link.', 'lereng'), $order->get_order_number()) : new WP_Error('product', __('That coffee is not available right now.', 'lereng'));

            case 'pause':
                $resume = Schedule::resumeDate(sanitize_text_field($in['until'] ?? ''), $today);
                if (!$resume) {
                    return new WP_Error('date', sprintf(__('Choose a date within the next %d days.', 'lereng'), Schedule::MAX_PAUSE_DAYS));
                }
                Repository::update($id, ['status' => 'paused', 'paused_until' => sanitize_text_field($in['until'])], 'paused', sprintf(__('until %s (%s)', 'lereng'), wp_date('j M', strtotime($in['until'])), $by));
                return sprintf(__('Paused. Deliveries restart on %s.', 'lereng'), wp_date('l j F', strtotime($resume)));

            case 'resume':
                $next = Schedule::roastDayOnOrAfter(wp_date('Y-m-d', strtotime('+' . Schedule::LEAD_DAYS . ' days')));
                Repository::update($id, ['status' => 'active', 'paused_until' => null, 'next_date' => $next], 'resumed', $by);
                return sprintf(__('Welcome back. Next delivery roasted on %s.', 'lereng'), wp_date('l j F', strtotime($next)));

            case 'change':
                $pid = (int) ($in['product_id'] ?? $sub['product_id']);
                $product = wc_get_product($pid);
                if (!$product || Subscriptions::subscribable($product) === 'no') {
                    return new WP_Error('product', __('That coffee is not available as a subscription.', 'lereng'));
                }
                $variationId = 0;
                $shelf = (int) wc_get_price_including_tax($product);
                if ($product->is_type('variable')) {
                    $match = (new \WC_Product_Data_Store_CPT())->find_matching_product_variation($product, ['attribute_pa_size' => sanitize_title($in['size'] ?? '200g'), 'attribute_pa_grind' => sanitize_title($in['grind'] ?? 'whole-bean')]);
                    if (!$match) {
                        return new WP_Error('variation', __('That size and grind is not available for this coffee.', 'lereng'));
                    }
                    $variationId = (int) $match;
                    $v = wc_get_product($variationId);
                    $shelf = (int) wc_get_price_including_tax($v, ['price' => $v->get_regular_price()]);
                }
                $interval = in_array((int) ($in['interval'] ?? 0), Schedule::INTERVALS, true) ? (int) $in['interval'] : $sub['interval_weeks'];
                $qty = max(1, min(10, (int) ($in['quantity'] ?? $sub['quantity'])));
                $fields = ['product_id' => $pid, 'variation_id' => $variationId, 'quantity' => $qty, 'interval_weeks' => $interval, 'price' => Schedule::price($shelf, (float) $sub['discount_pct'])];
                $changes = [];
                if ($pid !== $sub['product_id'] || $variationId !== $sub['variation_id']) {
                    $changes[] = wp_strip_all_tags(wc_get_product($variationId ?: $pid)->get_name());
                }
                if ($qty !== $sub['quantity']) {
                    $changes[] = sprintf(_n('%d bag', '%d bags', $qty, 'lereng'), $qty);
                }
                if ($interval !== $sub['interval_weeks']) {
                    $changes[] = Subscriptions::intervalLabel($interval);
                }
                if (!$changes) {
                    return __('Nothing changed.', 'lereng');
                }
                Repository::update($id, $fields, 'changed', implode(', ', $changes) . " ($by)");
                return sprintf(__('Updated: %s. It applies from your next delivery.', 'lereng'), implode(', ', $changes));

            case 'cancel':
                $reason = sanitize_key($in['reason'] ?? 'other');
                $label = Account::REASONS[$reason] ?? 'Something else';
                Repository::update($id, ['status' => 'cancelled', 'cancel_reason' => $reason], 'cancelled', $label . " ($by)");
                return __("Cancelled. No more deliveries; you can restart it from here anytime.", 'lereng');

            case 'restart':
                $next = Schedule::roastDayOnOrAfter(wp_date('Y-m-d', strtotime('+' . Schedule::LEAD_DAYS . ' days')));
                Repository::update($id, ['status' => 'active', 'next_date' => $next, 'cancel_reason' => null], 'restarted', $by);
                return sprintf(__('Restarted. First delivery roasted on %s.', 'lereng'), wp_date('l j F', strtotime($next)));
        }
        return new WP_Error('action', __('Unknown action.', 'lereng'));
    }
}
