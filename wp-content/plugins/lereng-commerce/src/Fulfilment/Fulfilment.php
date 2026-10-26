<?php

namespace Lereng\Fulfilment;

use Automattic\WooCommerce\Utilities\OrderUtil;
use WC_Order;

/**
 * From paid to delivered:
 *  - statuses Roasting and Shipped (both count as paid, so reports include them)
 *  - WooCommerce → Roast list: grams to roast per coffee for a roast day, bags per grind, and a
 *    button that moves those orders to Roasting
 *  - a Shipment box on the order screen (courier + waybill) and a bulk "Ship" action
 *  - "Shipped" and "How was it?" emails, tracking in My account and at /track-order/
 *  - parcels are marked Completed once the courier's last checkpoint says Delivered
 */
final class Fulfilment
{
    public const STATUSES = ['wc-roasting' => 'Roasting', 'wc-shipped' => 'Shipped'];

    public function register(): void
    {
        add_action('init', [$this, 'statuses']);
        add_filter('wc_order_statuses', [$this, 'orderStatuses']);
        add_filter('woocommerce_order_is_paid_statuses', static fn(array $s): array => [...$s, 'roasting', 'shipped']);
        add_filter('woocommerce_reports_order_statuses', static fn($s) => is_array($s) ? [...$s, 'roasting', 'shipped'] : $s);
        add_filter('woocommerce_analytics_excluded_statuses', static fn(array $s): array => $s);
        add_filter('woocommerce_valid_order_statuses_for_cancel', static fn(array $s): array => $s);

        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_lereng_start_roast', [$this, 'startRoast']);
        add_action('add_meta_boxes', [$this, 'metabox']);
        add_action('woocommerce_process_shop_order_meta', [$this, 'saveShipment'], 50);
        foreach (['bulk_actions-woocommerce_page_wc-orders', 'bulk_actions-edit-shop_order'] as $hook) {
            add_filter($hook, static fn(array $a): array => $a + ['lereng_roasting' => __('Change status to roasting', 'lereng'), 'lereng_ship' => __('Ship with tracking number', 'lereng')]);
        }
        foreach (['handle_bulk_actions-woocommerce_page_wc-orders', 'handle_bulk_actions-edit-shop_order'] as $hook) {
            add_filter($hook, [$this, 'bulk'], 10, 3);
        }

        add_filter('woocommerce_email_classes', [$this, 'emailClasses']);
        // WooCommerce loads its mailer lazily; these hooks wake it so our emails always fire.
        add_filter('woocommerce_email_actions', static fn(array $a): array => [...$a, 'woocommerce_order_status_shipped', 'lereng_review_request']);
        add_action('lereng_check_delivery', [$this, 'checkDelivery']);
        add_action('woocommerce_order_details_before_order_table', [$this, 'customerTimeline']);
        add_shortcode('lereng_track_order', [$this, 'trackPage']);
    }

    public function statuses(): void
    {
        foreach (self::STATUSES as $key => $label) {
            register_post_status($key, [
                'label' => $label,
                'public' => false,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                /* translators: %s: count */
                'label_count' => _n_noop("$label <span class=\"count\">(%s)</span>", "$label <span class=\"count\">(%s)</span>", 'lereng'),
            ]);
        }
    }

    public function orderStatuses(array $statuses): array
    {
        $out = [];
        foreach ($statuses as $k => $v) {
            $out[$k] = $v;
            if ($k === 'wc-processing') {
                $out['wc-roasting'] = _x('Roasting', 'Order status', 'lereng');
                $out['wc-shipped'] = _x('Shipped', 'Order status', 'lereng');
            }
        }
        return $out;
    }

    /* ---------------- roast list ---------------- */

    public function menu(): void
    {
        add_submenu_page('woocommerce', __('Roast list', 'lereng'), __('Roast list', 'lereng'), 'edit_shop_orders', 'lereng-roast-list', [$this, 'roastList']);
    }

    /** Orders for a roast day that still need roasting, with what's in them. */
    public static function roastPlan(string $date): array
    {
        $orders = wc_get_orders(['limit' => -1, 'status' => ['processing'], 'meta_key' => '_lereng_roast_date', 'meta_value' => $date]);
        $coffees = [];
        $ids = [];
        foreach ($orders as $order) {
            $ids[] = $order->get_id();
            foreach ($order->get_items() as $item) {
                $p = $item->get_product();
                if (!$p) {
                    continue;
                }
                $parent = $p->get_parent_id() ?: $p->get_id();
                if (!has_term(['coffee', 'subscriptions', 'bundles'], 'product_cat', $parent)) {
                    continue;
                }
                // Discovery boxes: three 100 g bags of different coffees.
                $picks = (array) $item->get_meta('_lereng_picks');
                if ($picks && array_filter($picks)) {
                    foreach ($picks as $pid) {
                        self::add($coffees, (int) $pid, get_the_title((int) $pid), 100 * $item->get_quantity(), (string) $item->get_meta('Grind'), $item->get_quantity(), '100g');
                    }
                    continue;
                }
                $size = $p->get_attribute('pa_size');
                $grams = (int) preg_replace('/\D/', '', $size) * (str_contains($size, 'kg') ? 1000 : 1);
                if (!$grams) {
                    continue;
                }
                self::add($coffees, $parent, get_the_title($parent), $grams * $item->get_quantity(), $p->get_attribute('pa_grind') ?: 'Whole bean', $item->get_quantity(), $size);
            }
        }
        uasort($coffees, static fn($a, $b) => $b['grams'] <=> $a['grams']);
        return ['orders' => $ids, 'coffees' => $coffees];
    }

    private static function add(array &$c, int $id, string $name, int $grams, string $grind, int $bags, string $size): void
    {
        $c[$id] ??= ['name' => $name, 'grams' => 0, 'bags' => []];
        $c[$id]['grams'] += $grams;
        $key = "$size · " . ($grind ?: 'Whole bean');
        $c[$id]['bags'][$key] = ($c[$id]['bags'][$key] ?? 0) + $bags;
    }

    public function roastList(): void
    {
        $dates = [];
        foreach (wc_get_orders(['limit' => 300, 'status' => ['processing'], 'return' => 'objects']) as $o) {
            if ($d = $o->get_meta('_lereng_roast_date')) {
                $dates[$d] = ($dates[$d] ?? 0) + 1;
            }
        }
        ksort($dates);
        $date = sanitize_text_field($_GET['date'] ?? (array_key_first($dates) ?: \Lereng\Subscriptions\Schedule::roastDayOnOrAfter(wp_date('Y-m-d')))); // phpcs:ignore WordPress.Security.NonceVerification
        $plan = self::roastPlan($date);
        echo '<div class="wrap"><h1>' . esc_html__('Roast list', 'lereng') . '</h1><p>';
        foreach ($dates as $d => $n) {
            printf('<a class="button %s" href="%s">%s · %d</a> ', $d === $date ? 'button-primary' : '', esc_url(admin_url('admin.php?page=lereng-roast-list&date=' . $d)), esc_html(wp_date('D j M', strtotime($d))), $n);
        }
        echo '</p>';
        $total = array_sum(array_column($plan['coffees'], 'grams'));
        // Roasting loses about 15% of the weight: green coffee needed = roasted / 0.85.
        printf('<p><b>%s</b> · %s · %s</p>', esc_html(sprintf(_n('%d order', '%d orders', count($plan['orders']), 'lereng'), count($plan['orders']))), esc_html(sprintf(__('%.1f kg roasted', 'lereng'), $total / 1000)), esc_html(sprintf(__('%.1f kg green coffee (15%% roast loss)', 'lereng'), $total / 850)));
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Coffee', 'lereng') . '</th><th>' . esc_html__('Roasted', 'lereng') . '</th><th>' . esc_html__('Green', 'lereng') . '</th><th>' . esc_html__('Bags (size · grind)', 'lereng') . '</th></tr></thead><tbody>';
        foreach ($plan['coffees'] as $c) {
            ksort($c['bags']);
            printf('<tr><td><b>%s</b></td><td>%s kg</td><td>%s kg</td><td>%s</td></tr>', esc_html($c['name']), esc_html(number_format($c['grams'] / 1000, 1)), esc_html(number_format($c['grams'] / 850, 1)), esc_html(implode(' · ', array_map(static fn($k, $v) => "$v× $k", array_keys($c['bags']), $c['bags']))));
        }
        echo '</tbody></table>';
        if ($plan['orders']) {
            printf('<form method="post" action="%s" style="margin-top:16px"><input type="hidden" name="action" value="lereng_start_roast"><input type="hidden" name="date" value="%s">%s<button class="button button-primary">%s</button> <button type="button" class="button" onclick="print()">%s</button></form>', esc_url(admin_url('admin-post.php')), esc_attr($date), wp_nonce_field('lereng_start_roast', '_wpnonce', true, false), esc_html(sprintf(__('Start roasting: move %d orders to Roasting', 'lereng'), count($plan['orders']))), esc_html__('Print', 'lereng'));
        }
        echo '</div>';
    }

    public function startRoast(): void
    {
        check_admin_referer('lereng_start_roast');
        if (!current_user_can('edit_shop_orders')) {
            wp_die('Not allowed');
        }
        $plan = self::roastPlan(sanitize_text_field($_POST['date'] ?? ''));
        foreach ($plan['orders'] as $id) {
            wc_get_order($id)->update_status('roasting', __('Roasting started from the roast list.', 'lereng'));
        }
        wp_safe_redirect(admin_url('admin.php?page=lereng-roast-list&date=' . rawurlencode(sanitize_text_field($_POST['date'] ?? ''))));
        exit;
    }

    /* ---------------- shipping ---------------- */

    public function metabox(): void
    {
        $screen = class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id('shop-order') : 'shop_order';
        add_meta_box('lereng-shipment', __('Shipment', 'lereng'), [$this, 'renderMetabox'], $screen, 'side', 'high');
    }

    public function renderMetabox($post): void
    {
        $order = $post instanceof WC_Order ? $post : wc_get_order($post->ID);
        $courier = $order->get_meta('_lereng_courier') ?: Tracking::courierFor($order->get_shipping_method());
        echo '<p><label>' . esc_html__('Courier', 'lereng') . '<br><select name="lereng_courier" style="width:100%">';
        foreach (Tracking::COURIERS as $k => [$name]) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($k, $courier, false), esc_html($name));
        }
        echo '</select></label></p><p><label>' . esc_html__('Waybill / tracking number', 'lereng') . '<input type="text" name="lereng_tracking" style="width:100%" value="' . esc_attr($order->get_meta('_lereng_tracking')) . '" placeholder="' . esc_attr(Tracking::number($courier, $order->get_id())) . '"></label></p>';
        echo '<p class="description">' . esc_html__('Saving a number marks the order Shipped and emails the customer.', 'lereng') . '</p>';
    }

    public function saveShipment(int $orderId): void
    {
        $number = sanitize_text_field(wp_unslash($_POST['lereng_tracking'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce checked the order form nonce.
        if ($number) {
            self::ship(wc_get_order($orderId), sanitize_key($_POST['lereng_courier'] ?? 'jne'), $number); // phpcs:ignore
        }
    }

    public static function ship(WC_Order $order, ?string $courier = null, ?string $number = null): void
    {
        if ($order->get_meta('_lereng_tracking') && $order->has_status(['shipped', 'completed'])) {
            return;
        }
        $courier ??= Tracking::courierFor($order->get_shipping_method());
        $number ??= Tracking::number($courier, $order->get_id());
        $order->update_meta_data('_lereng_courier', $courier);
        $order->update_meta_data('_lereng_tracking', $number);
        $order->update_meta_data('_lereng_shipped_at', time());
        $order->save();
        $order->update_status('shipped', sprintf(__('Shipped with %1$s, waybill %2$s.', 'lereng'), Tracking::COURIERS[$courier][0] ?? $courier, $number));
        as_schedule_single_action(time() + 6 * HOUR_IN_SECONDS, 'lereng_check_delivery', ['order_id' => $order->get_id()], 'lereng');
    }

    public function bulk(string $redirect, string $action, array $ids): string
    {
        foreach ($ids as $id) {
            $order = wc_get_order($id);
            if (!$order) {
                continue;
            }
            if ($action === 'lereng_roasting' && $order->has_status('processing')) {
                $order->update_status('roasting', __('Roasting (bulk action).', 'lereng'));
            } elseif ($action === 'lereng_ship' && $order->has_status(['processing', 'roasting']) && $order->needs_shipping_address()) {
                self::ship($order);
            }
        }
        return $redirect;
    }

    /** Timeline for an order: what happened when, and the courier's checkpoints. */
    public static function timeline(WC_Order $order, ?int $now = null): array
    {
        $now ??= time();
        $steps = [['Order placed', $order->get_date_created()?->getTimestamp()]];
        if ($paid = $order->get_date_paid()) {
            $steps[] = ['Paid', $paid->getTimestamp()];
        }
        if ($roast = $order->get_meta('_lereng_roast_date')) {
            $at = strtotime($roast . ' 10:00:00 Asia/Makassar');
            $steps[] = [$at <= $now ? 'Roasted' : 'Roasting on', $at];
        }
        $checkpoints = [];
        if ($shipped = (int) $order->get_meta('_lereng_shipped_at')) {
            $method = $order->get_shipping_method();
            $eta = preg_match('/(\d)(?:–(\d))? days?/', $method, $m) ? [(int) $m[1], (int) ($m[2] ?? $m[1])] : (str_contains($method, 'next day') ? [1, 1] : [0, 0]);
            $checkpoints = Tracking::checkpoints($order->get_meta('_lereng_courier'), $order->get_meta('_lereng_tracking'), $order->get_shipping_state(), $order->get_shipping_city(), $shipped, $eta, $now);
        }
        return ['steps' => $steps, 'checkpoints' => array_reverse($checkpoints), 'courier' => $order->get_meta('_lereng_courier'), 'number' => $order->get_meta('_lereng_tracking'), 'status' => $order->get_status()];
    }

    public function checkDelivery(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order || !$order->has_status('shipped')) {
            return;
        }
        $t = self::timeline($order);
        if ($t['checkpoints'] && $t['checkpoints'][0]['text'] === 'Delivered') {
            $order->update_status('completed', __('Delivered, according to the courier.', 'lereng'));
            as_schedule_single_action(time() + 3 * DAY_IN_SECONDS, 'lereng_review_request', ['order_id' => $order_id], 'lereng');
        } else {
            as_schedule_single_action(time() + 6 * HOUR_IN_SECONDS, 'lereng_check_delivery', ['order_id' => $order_id], 'lereng');
        }
    }

    public static function renderTimeline(WC_Order $order): string
    {
        $t = self::timeline($order);
        $html = '<section class="track"><ol class="track__steps">';
        foreach ($t['steps'] as [$label, $at]) {
            $done = $at && $at <= time();
            $html .= sprintf('<li class="%s"><b>%s</b><time>%s</time></li>', $done ? 'is-done' : '', esc_html__($label, 'lereng'), $at ? esc_html(wp_date('D j M, H:i', $at)) : '');
        }
        $html .= sprintf('<li class="%s"><b>%s</b>%s</li>', $t['number'] ? 'is-done' : '', esc_html__('Shipped', 'lereng'), $t['number'] ? '<time>' . esc_html((Tracking::COURIERS[$t['courier']][0] ?? '') . ' ' . $t['number']) . '</time>' : '');
        $delivered = $t['checkpoints'] && $t['checkpoints'][0]['text'] === 'Delivered';
        $html .= sprintf('<li class="%s"><b>%s</b></li></ol>', $delivered ? 'is-done' : '', esc_html__('Delivered', 'lereng'));
        if ($t['checkpoints']) {
            $html .= '<ul class="track__log">';
            foreach ($t['checkpoints'] as $c) {
                $html .= sprintf('<li><time>%s</time><span>%s</span><small>%s</small></li>', esc_html(wp_date('D j M H:i', $c['time'])), esc_html__($c['text'], 'lereng'), esc_html($c['place']));
            }
            $html .= '</ul>';
            if ($url = Tracking::url($t['courier'], $t['number'])) {
                $html .= '<p class="track__link"><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html(sprintf(__('Open on the %s website', 'lereng'), Tracking::COURIERS[$t['courier']][0])) . '</a></p>';
            }
        }
        return $html . '</section>';
    }

    public function customerTimeline(WC_Order $order): void
    {
        if ($order->is_paid() || $order->has_status(['roasting', 'shipped'])) {
            echo self::renderTimeline($order); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
        }
    }

    public function trackPage(): string
    {
        $out = '<form class="track-form" method="post"><p><label>' . esc_html__('Order number', 'lereng') . '<input name="order" required inputmode="numeric" value="' . esc_attr(sanitize_text_field(wp_unslash($_POST['order'] ?? ''))) . '"></label></p><p><label>' . esc_html__('Email used at checkout', 'lereng') . '<input name="email" type="email" required value="' . esc_attr(sanitize_email(wp_unslash($_POST['email'] ?? ''))) . '"></label></p>' . wp_nonce_field('lereng_track', '_lereng_track', true, false) . '<p><button type="submit">' . esc_html__('Track', 'lereng') . '</button></p></form>'; // phpcs:ignore WordPress.Security.NonceVerification
        if (empty($_POST['order'])) { // phpcs:ignore WordPress.Security.NonceVerification
            return $out;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_lereng_track'] ?? '')), 'lereng_track')) {
            return $out . '<p class="notice">' . esc_html__('Please try again.', 'lereng') . '</p>';
        }
        $order = wc_get_order(absint($_POST['order']));
        if (!$order || strcasecmp($order->get_billing_email(), sanitize_email(wp_unslash($_POST['email'] ?? ''))) !== 0) {
            return $out . '<p class="notice">' . esc_html__("We couldn't find an order with that number and email.", 'lereng') . '</p>';
        }
        return $out . sprintf('<h2>%s</h2>', esc_html(sprintf(__('Order #%1$s · %2$s', 'lereng'), $order->get_order_number(), wc_get_order_status_name($order->get_status())))) . self::renderTimeline($order);
    }

    public function emailClasses(array $emails): array
    {
        $emails['Lereng_Email_Shipped'] = new ShippedEmail();
        $emails['Lereng_Email_Review'] = new ReviewEmail();
        return $emails;
    }
}
