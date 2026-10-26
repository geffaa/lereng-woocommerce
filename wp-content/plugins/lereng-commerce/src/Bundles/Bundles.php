<?php

namespace Lereng\Bundles;

use WC_Order;
use WC_Product;

/**
 * Two kinds of bundle, both ordinary simple products with a little extra:
 *  - pick: the Discovery Box. The customer chooses N single origins on the product page; the
 *    price is computed from what they chose (Pricing::pick) and the picks go on the order.
 *  - fixed: the Brew Starter Kit and Office Pack. A fixed list of components priced as a set;
 *    stock is taken from the components, and the bundle is out of stock if any component is.
 */
final class Bundles
{
    public function register(): void
    {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'pickFields']);
        add_action('woocommerce_before_add_to_cart_form', [$this, 'inTheBox']);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [$this, 'cartData'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'price'], 20);
        add_filter('woocommerce_get_item_data', [$this, 'itemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'lineMeta'], 10, 3);
        add_filter('woocommerce_get_price_html', [$this, 'fromPrice'], 10, 2);
        add_filter('woocommerce_product_is_in_stock', [$this, 'inStock'], 10, 2);
        add_action('woocommerce_reduce_order_stock', [$this, 'reduceComponents']);
        add_action('woocommerce_restore_order_stock', [$this, 'restoreComponents']);
        add_filter('woocommerce_product_add_to_cart_url', [$this, 'chooseUrl'], 10, 2);
        add_filter('woocommerce_product_add_to_cart_text', [$this, 'chooseText'], 10, 2);
        add_filter('woocommerce_product_supports', [$this, 'noAjaxAdd'], 10, 3);
    }

    public static function kind(?WC_Product $p): ?string
    {
        return $p ? (get_post_meta($p->get_id(), '_lereng_bundle', true) ?: null) : null;
    }

    /** Single origins that can go in a Discovery Box, with their 200 g price. */
    public static function pickable(): array
    {
        $out = [];
        $ids = wc_get_products(['status' => 'publish', 'type' => 'variable', 'category' => ['single-origin'], 'limit' => -1, 'return' => 'ids', 'orderby' => 'menu_order', 'order' => 'ASC']);
        foreach ($ids as $id) {
            $p = wc_get_product($id);
            $price = (int) $p->get_variation_price('min', true);
            $out[$id] = ['name' => $p->get_name(), 'notes' => get_post_meta($id, '_lereng_notes', true), 'price200' => $price, 'island' => $p->get_attribute('pa_island')];
        }
        return $out;
    }

    /* ---------------- Discovery Box ---------------- */

    public function pickFields(): void
    {
        global $product;
        if (self::kind($product) !== 'pick') {
            return;
        }
        $n = (int) get_post_meta($product->get_id(), '_lereng_pick_count', true) ?: 3;
        $discount = (float) get_post_meta($product->get_id(), '_lereng_bundle_discount', true);
        $chosen = array_map('intval', (array) wp_unslash($_POST['lereng_picks'] ?? [])); // phpcs:ignore WordPress.Security.NonceVerification
        wp_enqueue_script('lereng-bundle', LERENG_URL . 'assets/js/bundle.js', [], LERENG_VERSION, true);
        printf('<fieldset class="pick" data-pick="%d" data-discount="%s" data-format="%s"><legend>%s <span data-count>0/%d</span></legend><div class="pick__grid">',
            $n, esc_attr((string) $discount), esc_attr(wp_strip_all_tags(wc_price(123456))), esc_html(sprintf(__('Choose %d coffees (100 g each)', 'lereng'), $n)), $n);
        foreach (self::pickable() as $id => $c) {
            printf('<label class="pick__item"><input type="checkbox" name="lereng_picks[]" value="%d" data-price="%d" %s><span class="pick__name">%s</span><span class="pick__meta">%s · %s</span><span class="pick__price">%s</span></label>',
                $id, (int) round($c['price200'] / 2), checked(in_array($id, $chosen, true), true, false), esc_html($c['name']), esc_html($c['island']), esc_html($c['notes']), wp_kses_post(wc_price((int) round($c['price200'] / 2))));
        }
        echo '</div><p class="pick__total">' . esc_html__('Box total', 'lereng') . ' <b data-total>—</b> <s data-before></s></p>';
        echo '<p><label>' . esc_html__('Grind', 'lereng') . ' <select name="lereng_grind">';
        foreach (['Whole bean', 'Espresso', 'Pour over', 'French press', 'Moka pot'] as $g) {
            printf('<option>%s</option>', esc_html($g));
        }
        echo '</select></label></p></fieldset>';
    }

    public function validate(bool $ok, int $productId, int $qty): bool
    {
        $p = wc_get_product($productId);
        if (self::kind($p) !== 'pick') {
            return $ok;
        }
        $n = (int) get_post_meta($productId, '_lereng_pick_count', true) ?: 3;
        $picks = array_unique(array_map('intval', (array) wp_unslash($_POST['lereng_picks'] ?? []))); // phpcs:ignore WordPress.Security.NonceVerification
        $valid = array_intersect($picks, array_keys(self::pickable()));
        if (count($valid) !== $n) {
            wc_add_notice(sprintf(__('Choose exactly %d different coffees for your box.', 'lereng'), $n), 'error');
            return false;
        }
        return $ok;
    }

    public function cartData(array $data, int $productId): array
    {
        if (self::kind(wc_get_product($productId)) !== 'pick') {
            return $data;
        }
        $picks = array_values(array_unique(array_map('intval', (array) wp_unslash($_POST['lereng_picks'] ?? [])))); // phpcs:ignore WordPress.Security.NonceVerification
        sort($picks);
        $data['lereng_picks'] = $picks;
        $data['lereng_grind'] = sanitize_text_field(wp_unslash($_POST['lereng_grind'] ?? 'Whole bean')); // phpcs:ignore
        return $data;
    }

    public function price($cart): void
    {
        if (did_action('woocommerce_before_calculate_totals') > 50) {
            return;
        }
        $all = null;
        foreach ($cart->get_cart() as $item) {
            if (empty($item['lereng_picks'])) {
                continue;
            }
            $all ??= self::pickable();
            $prices = array_map(static fn($id) => $all[$id]['price200'] ?? 0, $item['lereng_picks']);
            $discount = (float) get_post_meta($item['product_id'], '_lereng_bundle_discount', true);
            $item['data']->set_price(Pricing::pick($prices, $discount));
            $item['data']->set_weight((string) (count($prices) * 130));
        }
    }

    public function itemData(array $out, array $item): array
    {
        if (!empty($item['lereng_picks'])) {
            $names = array_map(static fn($id) => get_the_title($id), $item['lereng_picks']);
            $out[] = ['key' => __('Coffees', 'lereng'), 'value' => implode(', ', $names)];
            $out[] = ['key' => __('Grind', 'lereng'), 'value' => $item['lereng_grind'] ?? 'Whole bean'];
        }
        return $out;
    }

    public function lineMeta($item, string $key, array $values): void
    {
        if (!empty($values['lereng_picks'])) {
            $item->add_meta_data('_lereng_picks', $values['lereng_picks']);
            $item->add_meta_data(__('Coffees', 'lereng'), implode(', ', array_map('get_the_title', $values['lereng_picks'])));
            $item->add_meta_data(__('Grind', 'lereng'), $values['lereng_grind'] ?? 'Whole bean');
        }
    }

    /** Shop grids can't add a box without choosing: send people to the product page. */
    public function chooseUrl(string $url, WC_Product $p): string
    {
        return self::kind($p) === 'pick' ? $p->get_permalink() : $url;
    }

    public function chooseText(string $text, WC_Product $p): string
    {
        return self::kind($p) === 'pick' ? __('Choose coffees', 'lereng') : $text;
    }

    public function noAjaxAdd(bool $supports, string $feature, WC_Product $p): bool
    {
        return $feature === 'ajax_add_to_cart' && self::kind($p) === 'pick' ? false : $supports;
    }

    public function fromPrice(string $html, WC_Product $p): string
    {
        return self::kind($p) === 'pick' ? sprintf(__('From %s', 'lereng'), $html) : $html;
    }

    /* ---------------- fixed bundles ---------------- */

    /** @return list<array{product:WC_Product,qty:int}> */
    public static function components(WC_Product $p): array
    {
        $out = [];
        foreach ((array) get_post_meta($p->get_id(), '_lereng_bundle_items', true) as $row) {
            [$id, $qty] = $row;
            if ($c = wc_get_product((int) $id)) {
                $out[] = ['product' => $c, 'qty' => (int) $qty];
            }
        }
        return $out;
    }

    public function inTheBox(): void
    {
        global $product;
        if (self::kind($product) !== 'fixed') {
            return;
        }
        $sum = 0;
        echo '<div class="in-box"><p class="in-box__title">' . esc_html__('In the box', 'lereng') . '</p><ul>';
        foreach (self::components($product) as ['product' => $c, 'qty' => $qty]) {
            $line = (int) wc_get_price_including_tax($c) * $qty;
            $sum += $line;
            printf('<li><span>%s%s</span><span>%s</span></li>', $qty > 1 ? esc_html($qty . '× ') : '', esc_html($c->get_name()), wp_kses_post(wc_price($line)));
        }
        $save = $sum - (int) wc_get_price_including_tax($product);
        echo '</ul>';
        if ($save > 0) {
            printf('<p class="in-box__save">%s</p>', wp_kses_post(sprintf(__('Bought separately %1$s · you save %2$s', 'lereng'), wc_price($sum), wc_price($save))));
        }
        echo '</div>';
    }

    public function inStock(bool $in, WC_Product $p): bool
    {
        if (self::kind($p) !== 'fixed') {
            return $in;
        }
        foreach (self::components($p) as ['product' => $c, 'qty' => $qty]) {
            if (!$c->is_in_stock() || ($c->managing_stock() && $c->get_stock_quantity() < $qty && !$c->backorders_allowed())) {
                return false;
            }
        }
        return $in;
    }

    public function reduceComponents(WC_Order $order): void
    {
        $this->moveComponents($order, 'decrease');
    }

    public function restoreComponents(WC_Order $order): void
    {
        $this->moveComponents($order, 'increase');
    }

    private function moveComponents(WC_Order $order, string $direction): void
    {
        foreach ($order->get_items() as $item) {
            $p = $item->get_product();
            if (self::kind($p) !== 'fixed') {
                continue;
            }
            $done = $item->get_meta('_lereng_components_reduced') === 'yes';
            if (($direction === 'decrease') === $done) {
                continue;
            }
            $notes = [];
            foreach (self::components($p) as ['product' => $c, 'qty' => $qty]) {
                if ($c->managing_stock()) {
                    $new = wc_update_product_stock($c, $qty * $item->get_quantity(), $direction);
                    $notes[] = sprintf('%s → %d', $c->get_name(), $new);
                }
            }
            $item->update_meta_data('_lereng_components_reduced', $direction === 'decrease' ? 'yes' : 'no');
            $item->save();
            if ($notes) {
                $order->add_order_note(sprintf(__('%1$s stock %2$s: %3$s', 'lereng'), $p->get_name(), $direction === 'decrease' ? __('taken from components', 'lereng') : __('returned to components', 'lereng'), implode(', ', $notes)));
            }
        }
    }
}
