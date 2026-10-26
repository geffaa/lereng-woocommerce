<?php

namespace Lereng\Catalog;

/**
 * Coffee-specific product data: where it's from, how it was processed, how it tastes and
 * how to brew it. Stored as product meta (prefixed _lereng_) and shown in its own product tab.
 * Filterable facts (origin, process, roast, taste) are global product attributes instead,
 * so WooCommerce's own filters and the Store API can use them.
 */
final class CoffeeData
{
    public const FIELDS = [
        'producer' => 'Producer',
        'region' => 'Region',
        'altitude' => 'Altitude',
        'varietals' => 'Varietals',
        'harvest' => 'Harvest',
        'notes' => 'Tasting notes',
        'score' => 'Cupping score',
        'recipe' => 'Brew recipe',
        'story' => 'Story',
    ];

    public function register(): void
    {
        add_filter('woocommerce_product_data_tabs', [$this, 'tab']);
        add_action('woocommerce_product_data_panels', [$this, 'panel']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save']);
    }

    /** A "Coffee" tab in the product editor, so staff edit these fields without touching code. */
    public function tab(array $tabs): array
    {
        $tabs['lereng_coffee'] = ['label' => __('Coffee', 'lereng'), 'target' => 'lereng_coffee_data', 'priority' => 15, 'class' => []];
        return $tabs;
    }

    public function panel(): void
    {
        global $post;
        echo '<div id="lereng_coffee_data" class="panel woocommerce_options_panel hidden"><div class="options_group">';
        $long = ['recipe', 'story'];
        foreach (self::FIELDS as $key => $label) {
            $args = ['id' => "_lereng_$key", 'label' => __($label, 'lereng'), 'value' => get_post_meta($post->ID, "_lereng_$key", true)];
            if ($key === 'notes') {
                $args['description'] = __('Comma separated, shown as chips. Example: Lime, Brown sugar, Black tea', 'lereng');
                $args['desc_tip'] = true;
            }
            in_array($key, $long, true) ? woocommerce_wp_textarea_input($args + ['rows' => 3]) : woocommerce_wp_text_input($args);
        }
        echo '</div><div class="options_group">';
        woocommerce_wp_checkbox(['id' => '_lereng_subscribable', 'label' => __('Can be subscribed to', 'lereng'), 'description' => __('Shows “Subscribe & save” on the product page.', 'lereng'), 'cbvalue' => 'yes', 'value' => get_post_meta($post->ID, '_lereng_subscribable', true)]);
        echo '</div></div>';
    }

    public function save(\WC_Product $product): void
    {
        foreach (array_keys(self::FIELDS) as $key) {
            $field = "_lereng_$key";
            if (isset($_POST[$field])) { // phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce verified the product form nonce.
                $raw = wp_unslash($_POST[$field]);
                $product->update_meta_data($field, in_array($key, ['recipe', 'story'], true) ? sanitize_textarea_field($raw) : sanitize_text_field($raw));
            }
        }
        $product->update_meta_data('_lereng_subscribable', isset($_POST['_lereng_subscribable']) ? 'yes' : 'no');
    }

    /** @return array<string, string> */
    public static function get(int $productId): array
    {
        $out = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $value = get_post_meta($productId, "_lereng_$key", true);
            if ($value !== '' && $value !== null) {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}
