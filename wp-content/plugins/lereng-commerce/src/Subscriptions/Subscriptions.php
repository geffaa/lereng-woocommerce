<?php

namespace Lereng\Subscriptions;

use Lereng\Payments\Payments;
use Lereng\Payments\SandboxGateway;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Product;

/**
 * Subscribe & save, without a subscriptions plugin:
 *  - product page: one-time or subscribe (every 1, 2 or 4 weeks, 10% off)
 *  - the cart prices subscription lines at the subscription price; checkout requires an account
 *  - when the first order is paid, each subscription line becomes a subscription
 *  - an hourly job creates each renewal as an order two days before its roast day and emails a
 *    payment link; unpaid renewals are skipped on roast day, the subscription carries on
 * Customer controls live in Account, staff tools in Admin.
 */
final class Subscriptions
{
    public const TICK = 'lereng_subscriptions_tick';

    public function register(): void
    {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'purchaseOptions'], 5);
        add_filter('woocommerce_add_cart_item_data', [$this, 'cartData'], 10, 3);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate'], 10, 3);
        add_action('woocommerce_before_calculate_totals', [$this, 'price'], 30);
        add_filter('woocommerce_get_item_data', [$this, 'itemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'lineMeta'], 10, 3);
        add_filter('woocommerce_checkout_registration_required', [$this, 'requireAccount']);
        add_filter('woocommerce_product_add_to_cart_text', [$this, 'chooseText'], 10, 2);

        add_action('woocommerce_order_status_processing', [$this, 'onPaid'], 20);
        add_action('woocommerce_order_status_completed', [$this, 'onPaid'], 20);
        add_action('woocommerce_order_status_cancelled', [$this, 'onRenewalCancelled']);
        add_action(self::TICK, [$this, 'tick']);
        add_action('init', static function (): void {
            if (function_exists('as_has_scheduled_action') && !as_has_scheduled_action(self::TICK, [], 'lereng')) {
                as_schedule_recurring_action(time() + 60, HOUR_IN_SECONDS, self::TICK, [], 'lereng');
            }
        });
        add_shortcode('lereng_subscription_picker', [$this, 'picker']);

        (new Account())->register();
        (new Admin())->register();
    }

    public static function subscribable(?WC_Product $p): string
    {
        if (!$p) {
            return 'no';
        }
        $v = get_post_meta($p->get_parent_id() ?: $p->get_id(), '_lereng_subscribable', true);
        return in_array($v, ['yes', 'only'], true) ? $v : 'no';
    }

    public static function today(): string
    {
        return wp_date('Y-m-d');
    }

    /* ---------------- buying ---------------- */

    public function purchaseOptions(): void
    {
        global $product;
        $mode = self::subscribable($product);
        if ($mode === 'no') {
            return;
        }
        $pre = $mode === 'only' || (sanitize_key($_GET['purchase'] ?? '') === 'subscribe'); // phpcs:ignore WordPress.Security.NonceVerification
        wp_enqueue_script('lereng-subscribe', LERENG_URL . 'assets/js/subscribe.js', ['jquery'], LERENG_VERSION, true);
        $price = (int) wc_get_price_including_tax($product);
        ?>
        <fieldset class="purchase" data-discount="<?php echo esc_attr((string) Schedule::DISCOUNT); ?>" data-format="<?php echo esc_attr(wp_strip_all_tags(wc_price(123456))); ?>">
            <legend class="screen-reader-text"><?php esc_html_e('Purchase option', 'lereng'); ?></legend>
            <?php if ($mode !== 'only') : ?>
            <label class="purchase__opt"><input type="radio" name="lereng_purchase" value="once" <?php checked(!$pre); ?>>
                <span class="purchase__label"><?php esc_html_e('One-time purchase', 'lereng'); ?></span><b data-once><?php echo wp_kses_post(wc_price($price)); ?></b></label>
            <?php endif; ?>
            <label class="purchase__opt"><input type="radio" name="lereng_purchase" value="subscribe" <?php checked($pre); ?>>
                <span class="purchase__label"><?php echo esc_html(sprintf(__('Subscribe & save %d%%', 'lereng'), Schedule::DISCOUNT)); ?></span><b data-sub><?php echo wp_kses_post(wc_price(Schedule::price($price))); ?></b>
                <span class="purchase__freq"><?php esc_html_e('Deliver every', 'lereng'); ?>
                    <select name="lereng_interval" aria-label="<?php esc_attr_e('Delivery frequency', 'lereng'); ?>">
                        <option value="1"><?php esc_html_e('week', 'lereng'); ?></option>
                        <option value="2" selected><?php esc_html_e('2 weeks', 'lereng'); ?></option>
                        <option value="4"><?php esc_html_e('4 weeks', 'lereng'); ?></option>
                    </select>
                    <small><?php esc_html_e('Skip, pause or cancel anytime', 'lereng'); ?></small></span></label>
        </fieldset>
        <?php
    }

    public function validate(bool $ok, int $productId, int $qty): bool
    {
        if (self::subscribable(wc_get_product($productId)) === 'only' && ($_POST['lereng_purchase'] ?? '') !== 'subscribe') { // phpcs:ignore WordPress.Security.NonceVerification
            wc_add_notice(__('This one is subscription only.', 'lereng'), 'error');
            return false;
        }
        return $ok;
    }

    public function cartData(array $data, int $productId, int $variationId): array
    {
        if (($_POST['lereng_purchase'] ?? '') === 'subscribe' && self::subscribable(wc_get_product($variationId ?: $productId)) !== 'no') { // phpcs:ignore WordPress.Security.NonceVerification
            $interval = (int) ($_POST['lereng_interval'] ?? 2); // phpcs:ignore WordPress.Security.NonceVerification
            $data['lereng_sub'] = ['interval' => in_array($interval, Schedule::INTERVALS, true) ? $interval : 2];
        }
        return $data;
    }

    public function price($cart): void
    {
        foreach ($cart->get_cart() as $item) {
            if (!empty($item['lereng_sub'])) {
                $shelf = (int) wc_get_price_including_tax($item['data'], ['price' => $item['data']->get_regular_price()]);
                $item['data']->set_price(Schedule::price($shelf));
            }
        }
    }

    public static function intervalLabel(int $weeks): string
    {
        return $weeks === 1 ? __('every week', 'lereng') : sprintf(__('every %d weeks', 'lereng'), $weeks);
    }

    public function itemData(array $out, array $item): array
    {
        if (!empty($item['lereng_sub'])) {
            $out[] = ['key' => __('Subscription', 'lereng'), 'value' => sprintf(__('%1$s, %2$d%% off', 'lereng'), self::intervalLabel((int) $item['lereng_sub']['interval']), Schedule::DISCOUNT)];
        }
        return $out;
    }

    public function lineMeta(WC_Order_Item_Product $item, string $key, array $values): void
    {
        if (!empty($values['lereng_sub'])) {
            $item->add_meta_data('_lereng_sub_interval', (int) $values['lereng_sub']['interval']);
            $item->add_meta_data(__('Subscription', 'lereng'), self::intervalLabel((int) $values['lereng_sub']['interval']));
        }
    }

    public function requireAccount(bool $required): bool
    {
        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $item) {
                if (!empty($item['lereng_sub'])) {
                    return true;
                }
            }
        }
        return $required;
    }

    public function chooseText(string $text, WC_Product $p): string
    {
        return self::subscribable($p) === 'only' ? __('Subscribe', 'lereng') : $text;
    }

    /* ---------------- creating subscriptions ---------------- */

    public function onPaid(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order) {
            return;
        }
        if ($subId = (int) $order->get_meta('_lereng_subscription_id')) {
            if (!$order->get_meta('_lereng_renewal_counted')) {
                $sub = Repository::find($subId);
                Repository::update($subId, ['renewals' => ($sub['renewals'] ?? 0) + 1], 'renewal_paid', wc_price((float) $order->get_total()), $orderId);
                $order->update_meta_data('_lereng_renewal_counted', 'yes');
                $order->save();
            }
            return;
        }
        foreach ($order->get_items() as $item) {
            $interval = (int) $item->get_meta('_lereng_sub_interval');
            if (!$interval || Repository::forParentItem($orderId, $item->get_product_id(), $item->get_variation_id())) {
                continue;
            }
            if (!$order->get_customer_id()) {
                $order->add_order_note(__('Subscription not started: the order has no customer account.', 'lereng'));
                continue;
            }
            $first = $order->get_meta('_lereng_roast_date') ?: Schedule::roastDayOnOrAfter(self::today());
            $shipping = $order->get_items('shipping');
            $ship = $shipping ? reset($shipping) : null;
            $id = Repository::create([
                'customer_id' => $order->get_customer_id(),
                'status' => 'active',
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'quantity' => $item->get_quantity(),
                'interval_weeks' => $interval,
                'discount_pct' => Schedule::DISCOUNT,
                'price' => (int) round(((float) $item->get_subtotal() + (float) $item->get_subtotal_tax()) / max(1, $item->get_quantity())),
                'shipping_method' => $ship ? $ship->get_method_id() . ':' . ($ship->get_meta('service') ?: $ship->get_instance_id()) . '|' . $ship->get_name() : null,
                'shipping_cost' => $ship ? (int) round((float) $ship->get_total() + (float) $ship->get_total_tax()) : 0,
                'payment_method' => in_array($order->get_payment_method(), ['lereng_qris', 'lereng_va'], true) ? $order->get_payment_method() : 'lereng_va',
                'address' => wp_json_encode(['shipping' => $order->get_address('shipping'), 'billing' => $order->get_address('billing'), 'bank' => ((array) $order->get_meta('_lereng_payment'))['bank'] ?? 'bca']),
                'next_date' => Schedule::next($first, $interval),
                'parent_order_id' => $orderId,
            ]);
            $item->update_meta_data('_lereng_subscription_id', $id);
            $item->save();
            $order->add_order_note(sprintf(__('Subscription #%1$d started: %2$s, next delivery %3$s.', 'lereng'), $id, self::intervalLabel($interval), wp_date('j M', strtotime(Schedule::next($first, $interval)))));
            Emails::started(Repository::find($id));
        }
    }

    /* ---------------- renewals ---------------- */

    public function tick(): void
    {
        $today = self::today();
        foreach (Repository::pausedUntil($today) as $sub) {
            Repository::update($sub['id'], ['status' => 'active', 'paused_until' => null, 'next_date' => Schedule::roastDayOnOrAfter($sub['paused_until'])], 'resumed', 'pause ended');
        }
        foreach (Repository::due($today) as $sub) {
            self::renew($sub);
        }
    }

    /** Creates the renewal order for the subscription's next delivery and opens its payment. */
    public static function renew(array $sub): ?WC_Order
    {
        $product = wc_get_product($sub['variation_id'] ?: $sub['product_id']);
        if (!$product || !$product->is_purchasable()) {
            Repository::update($sub['id'], ['status' => 'on-hold'], 'held', __('Product no longer available', 'lereng'));
            return null;
        }
        $deliver = $sub['next_date'];
        $order = wc_create_order(['customer_id' => $sub['customer_id'], 'created_via' => 'lereng_subscription']);
        $tax = 1.11;
        $item = new WC_Order_Item_Product();
        $item->set_product($product);
        $item->set_quantity($sub['quantity']);
        $line = $sub['price'] * $sub['quantity'] / $tax;
        $item->set_subtotal((string) $line);
        $item->set_total((string) $line);
        $item->add_meta_data(__('Subscription', 'lereng'), sprintf('#%d, %s', $sub['id'], self::intervalLabel($sub['interval_weeks'])));
        $order->add_item($item);
        if ($sub['shipping_method']) {
            [$method, $name] = array_pad(explode('|', $sub['shipping_method'], 2), 2, '');
            $ship = new WC_Order_Item_Shipping();
            $ship->set_method_title($name);
            $ship->set_method_id(strtok($method, ':'));
            $ship->set_total((string) ($sub['shipping_cost'] / $tax));
            $order->add_item($ship);
        }
        $addr = $sub['address'];
        $order->set_address($addr['billing'] ?? [], 'billing');
        $order->set_address($addr['shipping'] ?? [], 'shipping');
        $order->update_meta_data('_lereng_subscription_id', $sub['id']);
        $order->update_meta_data('_lereng_roast_date', $deliver);
        $order->update_meta_data('_lereng_renewal_for', $deliver);
        $order->calculate_totals();
        $order->save();

        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway = $gateways[$sub['payment_method']] ?? $gateways['lereng_va'] ?? null;
        if ($gateway instanceof SandboxGateway) {
            if ($gateway->id === 'lereng_va') {
                $_POST['lereng_bank'] = $addr['bank'] ?? 'bca'; // the bank the customer chose first time
            }
            // Pay any time until 10:00 on roast day, when the batch is weighed out.
            $gateway->startPayment($order, strtotime($deliver . ' 10:00:00 Asia/Makassar'));
            unset($_POST['lereng_bank']);
        }
        $order->add_order_note(sprintf(__('Renewal of subscription #%1$d for delivery on %2$s.', 'lereng'), $sub['id'], wp_date('j M', strtotime($deliver))));
        Repository::update($sub['id'], ['next_date' => Schedule::next($deliver, $sub['interval_weeks']), 'last_order_id' => $order->get_id()], 'renewal_created', wp_date('j M', strtotime($deliver)), $order->get_id());
        Emails::renewal(Repository::find($sub['id']), $order);
        return $order;
    }

    public function onRenewalCancelled(int $orderId): void
    {
        $order = wc_get_order($orderId);
        $subId = $order ? (int) $order->get_meta('_lereng_subscription_id') : 0;
        if ($subId && !$order->get_meta('_lereng_renewal_skipped')) {
            Repository::update($subId, ['skipped' => (Repository::find($subId)['skipped'] ?? 0) + 1], 'renewal_unpaid', __('Not paid by roast day, delivery skipped', 'lereng'), $orderId);
            $order->update_meta_data('_lereng_renewal_skipped', 'yes');
            $order->save();
        }
    }

    /* ---------------- landing page picker ---------------- */

    public function picker(): string
    {
        $ids = wc_get_products(['status' => 'publish', 'limit' => 8, 'return' => 'ids', 'featured' => true, 'category' => ['coffee']]);
        $choice = get_page_by_path('roasters-choice-subscription', OBJECT, 'product');
        $html = '<div class="sub-picker">';
        if ($choice) {
            $p = wc_get_product($choice->ID);
            $html .= sprintf('<a class="sub-picker__hero" href="%s"><span class="kicker">%s</span><b>%s</b><span>%s</span><span class="arrow-link">%s</span></a>', esc_url(add_query_arg('purchase', 'subscribe', $p->get_permalink())), esc_html__('Let us choose', 'lereng'), esc_html($p->get_name()), esc_html(wp_strip_all_tags($p->get_short_description())), esc_html(sprintf(__('From %s a delivery', 'lereng'), wp_strip_all_tags(wc_price($p->get_price())))));
        }
        foreach ($ids as $id) {
            $p = wc_get_product($id);
            $shelf = (int) wc_get_price_including_tax($p, ['price' => $p->is_type('variable') ? $p->get_variation_regular_price('min') : $p->get_regular_price()]);
            $html .= sprintf('<a class="sub-picker__item" href="%s">%s<b>%s</b><span>%s</span></a>', esc_url(add_query_arg('purchase', 'subscribe', $p->get_permalink())), $p->get_image('woocommerce_thumbnail'), esc_html($p->get_name()), wp_kses_post(sprintf(__('%s a delivery', 'lereng'), wc_price(Schedule::price($shelf)))));
        }
        return $html . '</div>';
    }
}
