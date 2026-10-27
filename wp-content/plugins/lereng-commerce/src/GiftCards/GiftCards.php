<?php

namespace Lereng\GiftCards;

use WC_Order;
use WC_Order_Item_Product;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Gift cards, end to end:
 *  - the product page asks who it's for and what to say (and optionally when to send it)
 *  - when the order is paid, one card per unit is issued and emailed to the recipient
 *  - the code is entered in the coupon field at checkout; it works as a store credit coupon
 *    for whatever balance is left, and the ledger is debited when that order is paid
 *  - cancelled or refunded orders give the money back to the card
 *  - balance check at /gift-card-balance/, cards in My account, a list in WooCommerce admin
 */
final class GiftCards
{
    public const META_FLAG = '_lereng_gift_card';
    private const FIELDS = ['recipient_name', 'recipient_email', 'message', 'deliver_on'];

    public function register(): void
    {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'fields']);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [$this, 'cartData'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this, 'itemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'lineMeta'], 10, 3);

        add_action('woocommerce_order_status_processing', [$this, 'onPaid']);
        add_action('woocommerce_order_status_completed', [$this, 'onPaid']);
        add_action('woocommerce_order_status_cancelled', [$this, 'onReversed']);
        add_action('woocommerce_order_status_refunded', [$this, 'onReversed']);
        add_action('lereng_gift_card_deliver', [$this, 'deliver']);

        add_filter('woocommerce_get_shop_coupon_data', [$this, 'virtualCoupon'], 10, 2);
        add_filter('woocommerce_coupon_message', [$this, 'couponMessage'], 10, 3);

        add_action('rest_api_init', [$this, 'routes']);
        add_action('init', [$this, 'endpoint']);
        add_filter('woocommerce_account_menu_items', [$this, 'menu']);
        add_action('woocommerce_account_gift-cards_endpoint', [$this, 'account']);
        add_action('admin_menu', [$this, 'adminMenu']);
        add_shortcode('lereng_gift_card_balance', [$this, 'balanceForm']);
    }

    public static function isGiftCard($product): bool
    {
        if (!$product) {
            return false;
        }
        $id = $product->get_parent_id() ?: $product->get_id();
        return get_post_meta($id, self::META_FLAG, true) === 'yes';
    }

    /* ---------------- product page ---------------- */

    public function fields(): void
    {
        global $product;
        if (!self::isGiftCard($product)) {
            return;
        }
        $v = static fn(string $k): string => esc_attr(wp_unslash($_POST["lereng_gc_$k"] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification
        ?>
        <fieldset class="gc-fields">
            <legend><?php esc_html_e('Who is it for?', 'lereng'); ?></legend>
            <p><label><?php esc_html_e("Recipient's name", 'lereng'); ?><input type="text" name="lereng_gc_recipient_name" value="<?php echo $v('recipient_name'); ?>" maxlength="80"></label></p>
            <p><label><?php esc_html_e("Recipient's email", 'lereng'); ?> <abbr title="required">*</abbr><input type="email" name="lereng_gc_recipient_email" value="<?php echo $v('recipient_email'); ?>" required></label></p>
            <p><label><?php esc_html_e('Message', 'lereng'); ?><textarea name="lereng_gc_message" maxlength="240" rows="3" placeholder="<?php esc_attr_e('Happy birthday! Your next bag is on me.', 'lereng'); ?>"><?php echo esc_textarea(wp_unslash($_POST['lereng_gc_message'] ?? '')); // phpcs:ignore ?></textarea></label></p>
            <p><label><?php esc_html_e('Send on', 'lereng'); ?><input type="date" name="lereng_gc_deliver_on" min="<?php echo esc_attr(wp_date('Y-m-d')); ?>" value="<?php echo $v('deliver_on'); ?>"><small><?php esc_html_e('Leave empty to send as soon as the order is paid.', 'lereng'); ?></small></label></p>
        </fieldset>
        <?php
    }

    public function validate(bool $ok, int $productId, int $qty): bool
    {
        if (!self::isGiftCard(wc_get_product($productId))) {
            return $ok;
        }
        $email = sanitize_email(wp_unslash($_POST['lereng_gc_recipient_email'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification
        if (!is_email($email)) {
            wc_add_notice(__('Please enter the email address the gift card should go to.', 'lereng'), 'error');
            return false;
        }
        $date = sanitize_text_field(wp_unslash($_POST['lereng_gc_deliver_on'] ?? '')); // phpcs:ignore
        if ($date && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < wp_date('Y-m-d'))) {
            wc_add_notice(__('The send date has to be today or later.', 'lereng'), 'error');
            return false;
        }
        return $ok;
    }

    public function cartData(array $data, int $productId): array
    {
        if (!self::isGiftCard(wc_get_product($productId))) {
            return $data;
        }
        $gc = [];
        foreach (self::FIELDS as $f) {
            $raw = wp_unslash($_POST["lereng_gc_$f"] ?? ''); // phpcs:ignore WordPress.Security.NonceVerification
            $gc[$f] = $f === 'recipient_email' ? sanitize_email($raw) : ($f === 'message' ? sanitize_textarea_field($raw) : sanitize_text_field($raw));
        }
        $data['lereng_gc'] = $gc;
        $data['unique_key'] = md5(wp_json_encode($gc) . microtime()); // two cards to two people stay two lines
        return $data;
    }

    public function itemData(array $out, array $item): array
    {
        if (empty($item['lereng_gc'])) {
            return $out;
        }
        $gc = $item['lereng_gc'];
        $out[] = ['key' => __('To', 'lereng'), 'value' => trim(($gc['recipient_name'] ? $gc['recipient_name'] . ' ' : '') . '<' . $gc['recipient_email'] . '>')];
        if ($gc['message']) {
            $out[] = ['key' => __('Message', 'lereng'), 'value' => $gc['message']];
        }
        if ($gc['deliver_on']) {
            $out[] = ['key' => __('Send on', 'lereng'), 'value' => wp_date('j M Y', strtotime($gc['deliver_on']))];
        }
        return $out;
    }

    public function lineMeta(WC_Order_Item_Product $item, string $key, array $values): void
    {
        if (empty($values['lereng_gc'])) {
            return;
        }
        $gc = $values['lereng_gc'];
        $item->add_meta_data('_lereng_gc', $gc);
        $item->add_meta_data(__('To', 'lereng'), $gc['recipient_email']);
        if ($gc['message']) {
            $item->add_meta_data(__('Message', 'lereng'), $gc['message']);
        }
    }

    /* ---------------- issuing ---------------- */

    public function onPaid(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order) {
            return;
        }
        $this->issue($order);
        $this->debitRedemptions($order);
    }

    private function issue(WC_Order $order): void
    {
        foreach ($order->get_items() as $item) {
            $gc = $item->get_meta('_lereng_gc');
            if (!$gc || Repository::forOrderItem($item->get_id())) {
                continue; // not a gift card, or already issued (status changes can repeat)
            }
            $unit = (int) round(((float) $item->get_subtotal() + (float) $item->get_subtotal_tax()) / max(1, $item->get_quantity()));
            for ($i = 0; $i < $item->get_quantity(); $i++) {
                $card = Repository::create([
                    'initial_amount' => $unit,
                    'order_id' => $order->get_id(),
                    'order_item_id' => $item->get_id(),
                    'purchaser_email' => $order->get_billing_email(),
                    'recipient_name' => $gc['recipient_name'] ?? '',
                    'recipient_email' => $gc['recipient_email'] ?? $order->get_billing_email(),
                    'message' => $gc['message'] ?? '',
                    'deliver_at' => !empty($gc['deliver_on']) ? $gc['deliver_on'] . ' 01:00:00' : null,
                ]);
                $order->add_order_note(sprintf(__('Gift card %1$s issued for %2$s to %3$s.', 'lereng'), $card['code'], wc_price($unit), $card['recipient_email']));
                $when = !empty($gc['deliver_on']) ? strtotime($gc['deliver_on'] . ' 08:00:00 Asia/Makassar') : 0;
                if ($when > time()) {
                    as_schedule_single_action($when, 'lereng_gift_card_deliver', ['code' => $card['code']], 'lereng');
                } else {
                    $this->deliver($card['code']);
                }
            }
        }
    }

    public function deliver(string $code): void
    {
        $card = Repository::find($code);
        if (!$card || $card['delivered_at']) {
            return;
        }
        $from = '';
        if ($card['order_id'] && ($order = wc_get_order((int) $card['order_id']))) {
            $from = $order->get_billing_first_name();
        }
        $heading = $from ? sprintf(__('%s sent you a Lereng gift card', 'lereng'), $from) : __('You have a Lereng gift card', 'lereng');
        ob_start();
        ?>
        <p><?php echo esc_html($card['recipient_name'] ? sprintf(__('Hi %s,', 'lereng'), $card['recipient_name']) : __('Hi,', 'lereng')); ?></p>
        <?php if ($card['message']) : ?><blockquote style="margin:16px 0;padding:12px 16px;border-left:3px solid #B23A26;font-style:italic"><?php echo nl2br(esc_html($card['message'])); ?></blockquote><?php endif; ?>
        <p style="font-size:14px;color:#6B675F;margin:24px 0 4px"><?php esc_html_e('Your gift card code', 'lereng'); ?></p>
        <p style="font:600 26px/1.2 monospace;letter-spacing:2px;margin:0 0 4px"><?php echo esc_html($card['code']); ?></p>
        <p style="font-size:18px;margin:0 0 24px"><?php echo wp_kses_post(wc_price((int) $card['initial_amount'])); ?></p>
        <p><?php esc_html_e('Enter the code in the coupon box at checkout. Use it across as many orders as you like until the balance runs out; it never expires.', 'lereng'); ?></p>
        <p><a href="<?php echo esc_url(home_url('/product-category/coffee/')); ?>"><?php esc_html_e('Choose some coffee', 'lereng'); ?></a> · <a href="<?php echo esc_url(home_url('/gift-card-balance/')); ?>"><?php esc_html_e('Check the balance', 'lereng'); ?></a></p>
        <?php
        $body = WC()->mailer()->wrap_message($heading, ob_get_clean());
        WC()->mailer()->send($card['recipient_email'], $heading, $body);
        Repository::markDelivered((int) $card['id']);
    }

    /* ---------------- redeeming ---------------- */

    /** A gift card code entered as a coupon becomes a store-credit coupon for its balance. */
    public function virtualCoupon($data, $code)
    {
        $card = Repository::find((string) $code);
        if (!$card || $card['status'] !== 'active' || (int) $card['balance'] <= 0) {
            return $data;
        }
        return [
            'code' => strtolower($card['code']),
            'discount_type' => 'fixed_cart',
            'amount' => (int) $card['balance'],
            'individual_use' => false,
            'description' => sprintf(__('Gift card, balance %s', 'lereng'), wp_strip_all_tags(wc_price((int) $card['balance']))),
            'virtual' => true,
        ];
    }

    public function couponMessage($msg, $code, $coupon)
    {
        return $coupon && Repository::find($coupon->get_code()) ? __('Gift card applied.', 'lereng') : $msg;
    }

    private function debitRedemptions(WC_Order $order): void
    {
        if ($order->get_meta('_lereng_gc_debited')) {
            return;
        }
        $done = [];
        foreach ($order->get_coupons() as $c) {
            $card = Repository::find($c->get_code());
            if (!$card) {
                continue;
            }
            $used = (int) round((float) $c->get_discount() + (float) $c->get_discount_tax());
            $moved = Repository::move((int) $card['id'], -$used, $order->get_id(), sprintf('Order #%s', $order->get_order_number()));
            $done[$card['code']] = -$moved;
            $order->add_order_note(sprintf(__('Gift card %1$s: %2$s used.', 'lereng'), $card['code'], wc_price(-$moved)));
        }
        if ($done) {
            $order->update_meta_data('_lereng_gc_debited', $done);
            $order->save();
        }
    }

    public function onReversed(int $orderId): void
    {
        $order = wc_get_order($orderId);
        $done = $order ? (array) $order->get_meta('_lereng_gc_debited') : [];
        if (!$done || $order->get_meta('_lereng_gc_credited')) {
            return;
        }
        foreach ($done as $code => $amount) {
            if (($card = Repository::find($code)) && $amount > 0) {
                Repository::move((int) $card['id'], (int) $amount, $order->get_id(), sprintf('Order #%s %s', $order->get_order_number(), $order->get_status()));
                $order->add_order_note(sprintf(__('Gift card %1$s: %2$s returned to the card.', 'lereng'), $code, wc_price((int) $amount)));
            }
        }
        $order->update_meta_data('_lereng_gc_credited', 'yes');
        $order->save();
    }

    /* ---------------- balance, account, admin ---------------- */

    public function routes(): void
    {
        register_rest_route('lereng/v1', '/gift-cards/balance', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'args' => ['code' => ['required' => true, 'type' => 'string']],
            'callback' => static function (WP_REST_Request $r): WP_REST_Response {
                // A light brake on guessing: 20 lookups per address per 10 minutes.
                $key = 'lereng_gc_rate_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
                $n = (int) get_transient($key);
                if ($n >= 20) {
                    return new WP_REST_Response(['message' => __('Too many tries. Please wait a few minutes.', 'lereng')], 429);
                }
                set_transient($key, $n + 1, 10 * MINUTE_IN_SECONDS);
                $card = Repository::find((string) $r['code']);
                if (!$card) {
                    return new WP_REST_Response(['message' => __("We couldn't find that code. Check it and try again.", 'lereng')], 404);
                }
                return new WP_REST_Response([
                    'code' => $card['code'],
                    'balance' => (int) $card['balance'],
                    'balance_html' => wc_price((int) $card['balance']),
                    'initial_html' => wc_price((int) $card['initial_amount']),
                    'status' => $card['status'],
                ]);
            },
        ]);
    }

    public function balanceForm(): string
    {
        wp_enqueue_script('lereng-gift-balance', LERENG_URL . 'assets/js/gift-balance.js', [], LERENG_VERSION, true);
        return '<form class="gc-balance" data-gc-balance><label for="gc-code">' . esc_html__('Gift card code', 'lereng') . '</label><div><input id="gc-code" name="code" placeholder="LRG-XXXX-XXXX" required autocomplete="off"><button type="submit">' . esc_html__('Check balance', 'lereng') . '</button></div><p data-result role="status"></p></form>';
    }

    public function endpoint(): void
    {
        add_rewrite_endpoint('gift-cards', EP_PAGES);
    }

    public function menu(array $items): array
    {
        $out = [];
        foreach ($items as $k => $v) {
            $out[$k] = $v;
            if ($k === 'orders') {
                $out['gift-cards'] = __('Gift cards', 'lereng');
            }
        }
        return $out;
    }

    public function account(): void
    {
        $cards = Repository::forEmail(wp_get_current_user()->user_email);
        if (!$cards) {
            echo '<p>' . esc_html__('No gift cards yet.', 'lereng') . ' <a href="' . esc_url(home_url('/product/lereng-gift-card/')) . '">' . esc_html__('Send one', 'lereng') . '</a></p>';
            return;
        }
        echo '<table class="shop_table"><thead><tr><th>' . esc_html__('Code', 'lereng') . '</th><th>' . esc_html__('For', 'lereng') . '</th><th>' . esc_html__('Value', 'lereng') . '</th><th>' . esc_html__('Balance', 'lereng') . '</th></tr></thead><tbody>';
        $me = wp_get_current_user()->user_email;
        foreach ($cards as $c) {
            $mine = strcasecmp($c['recipient_email'], $me) === 0;
            printf('<tr><td><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html($mine ? $c['code'] : substr($c['code'], 0, 8) . '••••'), esc_html($mine ? __('You', 'lereng') : $c['recipient_email']), wp_kses_post(wc_price((int) $c['initial_amount'])), wp_kses_post(wc_price((int) $c['balance'])));
        }
        echo '</tbody></table>';
    }

    public function adminMenu(): void
    {
        add_submenu_page('woocommerce', __('Gift cards', 'lereng'), __('Gift cards', 'lereng'), 'manage_woocommerce', 'lereng-gift-cards', static function (): void {
            echo '<div class="wrap"><h1>' . esc_html__('Gift cards', 'lereng') . '</h1><table class="widefat striped"><thead><tr><th>Code</th><th>Recipient</th><th>Value</th><th>Balance</th><th>Order</th><th>Sent</th></tr></thead><tbody>';
            foreach (Repository::all() as $c) {
                printf('<tr><td><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html($c['code']), esc_html($c['recipient_email']), wp_kses_post(wc_price((int) $c['initial_amount'])), wp_kses_post(wc_price((int) $c['balance'])),
                    $c['order_id'] ? '<a href="' . esc_url(admin_url('admin.php?page=wc-orders&action=edit&id=' . (int) $c['order_id'])) . '">#' . (int) $c['order_id'] . '</a>' : '—',
                    esc_html($c['delivered_at'] ? wp_date('j M Y', strtotime($c['delivered_at'] . ' UTC')) : ($c['deliver_at'] ? sprintf(__('Scheduled %s', 'lereng'), wp_date('j M', strtotime($c['deliver_at'])) ) : '—')));
            }
            echo '</tbody></table></div>';
        });
    }
}
