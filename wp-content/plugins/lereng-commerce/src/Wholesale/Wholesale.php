<?php

namespace Lereng\Wholesale;

use WC_Product;

/**
 * Wholesale for cafés and offices:
 *  - application form ([lereng_wholesale_form]) → WooCommerce → Wholesale, where staff approve
 *  - approved accounts get the wholesale_customer role, wholesale prices on 1 kg coffee (25% off,
 *    30% from 10 kg in one order), a 5 kg minimum, and can pay by invoice (bank transfer, 14 days)
 *  - My account → Quick order: every coffee in one table, quantities in, one click to the cart
 */
final class Wholesale
{
    public const ROLE = 'wholesale_customer';
    public const MIN_KG = 5;
    public const TIERS = [[10, 30.0], [0, 25.0]]; // [from kg, % off]

    public function register(): void
    {
        add_action('init', [$this, 'setup']);
        add_shortcode('lereng_wholesale_form', [$this, 'form']);
        add_action('admin_menu', fn() => add_submenu_page('woocommerce', __('Wholesale', 'lereng'), __('Wholesale', 'lereng'), 'manage_woocommerce', 'lereng-wholesale', [$this, 'admin']));
        add_action('admin_post_lereng_wholesale', [$this, 'decide']);
        add_action('template_redirect', [$this, 'submit']);

        add_action('woocommerce_before_calculate_totals', [$this, 'prices'], 40);
        add_filter('woocommerce_get_price_html', [$this, 'priceHtml'], 20, 2);
        add_action('woocommerce_check_cart_items', [$this, 'minimumNotice']);
        add_action('woocommerce_store_api_cart_errors', [$this, 'minimumError'], 10, 2);
        add_filter('woocommerce_available_payment_gateways', [$this, 'gateways']);

        add_filter('woocommerce_account_menu_items', [$this, 'menu']);
        add_action('woocommerce_account_quick-order_endpoint', [$this, 'quickOrder']);
        add_filter('woocommerce_get_query_vars', static fn(array $v): array => $v + ['quick-order' => 'quick-order']);
    }

    public function setup(): void
    {
        if (!get_role(self::ROLE)) {
            add_role(self::ROLE, __('Wholesale customer', 'lereng'), ['read' => true]);
        }
        register_post_type('lereng_wholesale', ['public' => false, 'show_ui' => false, 'label' => 'Wholesale applications', 'supports' => ['title']]);
        add_rewrite_endpoint('quick-order', EP_ROOT | EP_PAGES);
    }

    public static function isWholesale(?int $userId = null): bool
    {
        $user = $userId ? get_userdata($userId) : wp_get_current_user();
        return $user && in_array(self::ROLE, (array) $user->roles, true);
    }

    /** Discount for an order holding $kg of coffee. */
    public static function discount(float $kg): float
    {
        foreach (self::TIERS as [$from, $pct]) {
            if ($kg >= $from) {
                return $pct;
            }
        }
        return 0.0;
    }

    private static function kgOf(WC_Product $p): float
    {
        $size = $p->get_attribute('pa_size');
        return str_contains($size, 'kg') ? (float) $size : ((float) $size) / 1000;
    }

    private static function isCoffee(WC_Product $p): bool
    {
        return has_term(['coffee'], 'product_cat', $p->get_parent_id() ?: $p->get_id()) && self::kgOf($p) > 0;
    }

    public static function cartKg(): float
    {
        $kg = 0.0;
        foreach (WC()->cart ? WC()->cart->get_cart() : [] as $item) {
            if (self::isCoffee($item['data'])) {
                $kg += self::kgOf($item['data']) * $item['quantity'];
            }
        }
        return $kg;
    }

    public function prices($cart): void
    {
        if (!self::isWholesale()) {
            return;
        }
        $pct = self::discount(self::cartKg());
        foreach ($cart->get_cart() as $item) {
            $p = $item['data'];
            if (self::isCoffee($p) && self::kgOf($p) >= 1) {
                $p->set_price(\Lereng\Bundles\Pricing::round((float) $p->get_regular_price() * (1 - $pct / 100)));
            }
        }
    }

    public function priceHtml(string $html, WC_Product $p): string
    {
        if (!self::isWholesale() || !self::isCoffee($p) && !$p->is_type('variable')) {
            return $html;
        }
        if ($p->is_type('variable') && has_term(['coffee'], 'product_cat', $p->get_id())) {
            return $html . '<br><small class="ws-note">' . esc_html(sprintf(__('Wholesale: %1$d%% off 1 kg bags, %2$d%% from 10 kg', 'lereng'), self::TIERS[1][1], self::TIERS[0][1])) . '</small>';
        }
        return $html;
    }

    public static function minimumProblem(): ?string
    {
        if (!self::isWholesale() || !WC()->cart || WC()->cart->is_empty()) {
            return null;
        }
        $kg = self::cartKg();
        return $kg < self::MIN_KG ? sprintf(__('Wholesale orders start at %1$d kg of coffee; this order has %2$s kg.', 'lereng'), self::MIN_KG, number_format_i18n($kg, 1)) : null;
    }

    public function minimumNotice(): void
    {
        if ($m = self::minimumProblem()) {
            wc_add_notice($m, 'error');
        }
    }

    public function minimumError($errors, $cart): void
    {
        if ($m = self::minimumProblem()) {
            $errors->add('lereng_wholesale_minimum', $m);
        }
    }

    /** Invoice payment (bank transfer within 14 days) only for wholesale accounts. */
    public function gateways(array $gateways): array
    {
        if (!self::isWholesale() && !is_admin()) {
            unset($gateways['bacs']);
        }
        return $gateways;
    }

    public function menu(array $items): array
    {
        if (!self::isWholesale()) {
            return $items;
        }
        return ['quick-order' => __('Quick order', 'lereng')] + $items;
    }

    public function quickOrder(): void
    {
        if (!self::isWholesale()) {
            return;
        }
        $ids = wc_get_products(['status' => 'publish', 'type' => 'variable', 'category' => ['single-origin', 'blends'], 'limit' => -1, 'return' => 'ids', 'orderby' => 'menu_order', 'order' => 'ASC']);
        echo '<p>' . esc_html(sprintf(__('Wholesale prices: %1$d%% off 1 kg bags, %2$d%% when the order reaches 10 kg. Minimum %3$d kg.', 'lereng'), self::TIERS[1][1], self::TIERS[0][1], self::MIN_KG)) . '</p>';
        echo '<form method="post" class="quick-order">' . wp_nonce_field('lereng_quick_order', '_lereng_qo', true, false) . '<table class="shop_table"><thead><tr><th>' . esc_html__('Coffee', 'lereng') . '</th><th>' . esc_html__('1 kg list', 'lereng') . '</th><th>' . esc_html__('Wholesale', 'lereng') . '</th><th>' . esc_html__('Grind', 'lereng') . '</th><th>' . esc_html__('Kg', 'lereng') . '</th></tr></thead><tbody>';
        foreach ($ids as $id) {
            $p = wc_get_product($id);
            $kilo = null;
            foreach ($p->get_children() as $c) {
                $v = wc_get_product($c);
                if ($v->get_attribute('pa_size') === '1kg' && $v->get_attribute('pa_grind') === 'Whole bean') {
                    $kilo = $v;
                }
            }
            if (!$kilo) {
                continue;
            }
            printf('<tr><td>%s<br><small>%s</small></td><td>%s</td><td><b>%s</b></td><td><select name="grind[%d]"><option>Whole bean</option><option>Espresso</option><option>Pour over</option></select></td><td><input type="number" min="0" max="200" step="1" name="qty[%d]" value="0" style="width:4.5rem"></td></tr>',
                esc_html($p->get_name()), esc_html($p->get_attribute('pa_island') . ' · ' . $p->get_attribute('pa_process')), wp_kses_post(wc_price($kilo->get_regular_price())), wp_kses_post(wc_price(\Lereng\Bundles\Pricing::round((float) $kilo->get_regular_price() * (1 - self::TIERS[1][1] / 100)))), $id, $id);
        }
        echo '</tbody></table><p><button type="submit" name="lereng_quick_order" value="1" class="button">' . esc_html__('Add to cart', 'lereng') . '</button></p></form>';
    }

    /** Form posts: the public application, and the quick order. */
    public function submit(): void
    {
        if (!empty($_POST['lereng_quick_order']) && self::isWholesale() && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_lereng_qo'] ?? '')), 'lereng_quick_order')) {
            $added = 0;
            foreach ((array) ($_POST['qty'] ?? []) as $id => $qty) {
                $qty = absint($qty);
                if (!$qty) {
                    continue;
                }
                $p = wc_get_product(absint($id));
                $grind = sanitize_text_field(wp_unslash($_POST['grind'][$id] ?? 'Whole bean'));
                $vid = (new \WC_Product_Data_Store_CPT())->find_matching_product_variation($p, ['attribute_pa_size' => '1kg', 'attribute_pa_grind' => sanitize_title($grind)]);
                if ($vid && WC()->cart->add_to_cart($p->get_id(), $qty, $vid)) {
                    $added += $qty;
                }
            }
            wc_add_notice(sprintf(_n('%d kg added to the cart.', '%d kg added to the cart.', $added, 'lereng'), $added), $added ? 'success' : 'notice');
            wp_safe_redirect($added ? wc_get_cart_url() : wc_get_account_endpoint_url('quick-order'));
            exit;
        }
        if (empty($_POST['lereng_wholesale_apply'])) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_lereng_ws'] ?? '')), 'lereng_wholesale')) {
            return;
        }
        $f = static fn($k) => sanitize_text_field(wp_unslash($_POST[$k] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        if (!$f('business') || !is_email($email)) {
            wc_add_notice(__('Please add your business name and a valid email.', 'lereng'), 'error');
            return;
        }
        $id = wp_insert_post(['post_type' => 'lereng_wholesale', 'post_status' => 'private', 'post_title' => $f('business')]);
        foreach (['contact', 'type', 'city', 'volume', 'npwp', 'phone'] as $k) {
            update_post_meta($id, "_$k", $f($k));
        }
        update_post_meta($id, '_email', $email);
        update_post_meta($id, '_status', 'pending');
        WC()->mailer()->send(get_option('admin_email'), sprintf(__('Wholesale application: %s', 'lereng'), $f('business')), WC()->mailer()->wrap_message(__('New wholesale application', 'lereng'), '<p>' . esc_html(sprintf('%s (%s, %s), about %s kg a month. Review it in WooCommerce → Wholesale.', $f('business'), $f('type'), $f('city'), $f('volume'))) . '</p>'));
        wc_add_notice(__("Thanks! We'll review your application within two working days and email you.", 'lereng'), 'success');
        wp_safe_redirect(add_query_arg('applied', 1, get_permalink()));
        exit;
    }

    public function form(): string
    {
        if (!empty($_GET['applied'])) { // phpcs:ignore WordPress.Security.NonceVerification
            return '<p class="notice">' . esc_html__("Application received. We'll email you within two working days.", 'lereng') . '</p>';
        }
        if (self::isWholesale()) {
            return '<p>' . sprintf(esc_html__('You have a wholesale account. %s', 'lereng'), '<a href="' . esc_url(wc_get_account_endpoint_url('quick-order')) . '">' . esc_html__('Go to quick order', 'lereng') . '</a>') . '</p>';
        }
        $field = static fn($name, $label, $type = 'text', $extra = '') => sprintf('<p><label>%s<input type="%s" name="%s" %s></label></p>', esc_html($label), $type, $name, $extra);
        return '<form method="post" class="ws-form">' . wp_nonce_field('lereng_wholesale', '_lereng_ws', true, false)
            . $field('business', __('Business name', 'lereng'), 'text', 'required') . $field('contact', __('Your name', 'lereng'), 'text', 'required')
            . $field('email', __('Email', 'lereng'), 'email', 'required') . $field('phone', __('WhatsApp', 'lereng'), 'tel')
            . '<p><label>' . esc_html__('Type of business', 'lereng') . '<select name="type"><option>Café</option><option>Restaurant</option><option>Hotel or villa</option><option>Office</option><option>Reseller</option></select></label></p>'
            . $field('city', __('City', 'lereng'), 'text', 'required') . '<p><label>' . esc_html__('Coffee per month', 'lereng') . '<select name="volume"><option>5–10 kg</option><option>10–25 kg</option><option>25–50 kg</option><option>50+ kg</option></select></label></p>'
            . $field('npwp', __('NPWP (optional)', 'lereng'))
            . '<p><button type="submit" name="lereng_wholesale_apply" value="1">' . esc_html__('Apply', 'lereng') . '</button></p></form>';
    }

    public function admin(): void
    {
        $apps = get_posts(['post_type' => 'lereng_wholesale', 'post_status' => 'private', 'posts_per_page' => 100]);
        echo '<div class="wrap"><h1>' . esc_html__('Wholesale applications', 'lereng') . '</h1><table class="widefat striped"><thead><tr><th>Business</th><th>Contact</th><th>Type</th><th>City</th><th>Per month</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach ($apps as $a) {
            $m = static fn($k) => get_post_meta($a->ID, "_$k", true);
            $buttons = $m('status') === 'pending' ? implode(' ', array_map(static fn($d, $l) => sprintf('<form method="post" action="%s" style="display:inline"><input type="hidden" name="action" value="lereng_wholesale"><input type="hidden" name="app" value="%d"><input type="hidden" name="decision" value="%s">%s<button class="button">%s</button></form>', esc_url(admin_url('admin-post.php')), $a->ID, $d, wp_nonce_field('lereng_wholesale_' . $a->ID, '_wpnonce', true, false), esc_html($l)), ['approve', 'decline'], [__('Approve', 'lereng'), __('Decline', 'lereng')])) : '';
            printf('<tr><td><b>%s</b></td><td>%s<br>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html($a->post_title), esc_html($m('contact')), esc_html($m('email')), esc_html($m('type')), esc_html($m('city')), esc_html($m('volume')), esc_html($m('status')), $buttons); // phpcs:ignore WordPress.Security.EscapeOutput
        }
        echo '</tbody></table></div>';
    }

    public function decide(): void
    {
        $id = (int) ($_POST['app'] ?? 0);
        check_admin_referer('lereng_wholesale_' . $id);
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Not allowed');
        }
        $decision = sanitize_key($_POST['decision'] ?? '');
        update_post_meta($id, '_status', $decision === 'approve' ? 'approved' : 'declined');
        if ($decision === 'approve') {
            self::approve($id);
        }
        wp_safe_redirect(admin_url('admin.php?page=lereng-wholesale'));
        exit;
    }

    /** Creates or upgrades the account and emails how to sign in. */
    public static function approve(int $appId): int
    {
        $email = get_post_meta($appId, '_email', true);
        $user = get_user_by('email', $email);
        if (!$user) {
            $userId = wc_create_new_customer($email, '', wp_generate_password(), ['first_name' => get_post_meta($appId, '_contact', true)]);
            $user = get_userdata($userId);
        }
        $user->set_role(self::ROLE);
        update_user_meta($user->ID, 'billing_company', get_the_title($appId));
        update_user_meta($user->ID, 'billing_email', $email);
        $body = '<p>' . esc_html(sprintf(__('Welcome, %s. Your wholesale account is ready: 1 kg bags at wholesale prices, invoice payment, and a quick order page.', 'lereng'), get_the_title($appId))) . '</p><p><a href="' . esc_url(wc_get_account_endpoint_url('quick-order')) . '">' . esc_html__('Sign in and order', 'lereng') . '</a> · <a href="' . esc_url(wp_lostpassword_url()) . '">' . esc_html__('Set a password', 'lereng') . '</a></p>';
        WC()->mailer()->send($email, __('Your Lereng wholesale account', 'lereng'), WC()->mailer()->wrap_message(__('Wholesale account approved', 'lereng'), $body));
        return $user->ID;
    }
}
