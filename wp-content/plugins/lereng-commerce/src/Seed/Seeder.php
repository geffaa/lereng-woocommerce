<?php

namespace Lereng\Seed;

use WC_Product_Attribute;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;

/**
 * Builds the demo store from data/catalog.json. Each step can be run on its own and again:
 * products are matched by SKU or slug and updated in place.
 */
final class Seeder
{
    public const STEPS = ['settings', 'taxonomies', 'products'];

    /** Global attributes: [slug => [label, used for variations?, filterable?]] */
    public const ATTRIBUTES = [
        'island' => ['Island', false],
        'process' => ['Process', false],
        'roast' => ['Roast', false],
        'taste' => ['Taste', false],
        'species' => ['Species', false],
        'size' => ['Size', true],
        'grind' => ['Grind', true],
    ];

    private const ROAST_LABEL = ['light' => 'Light', 'medium' => 'Medium', 'medium-dark' => 'Medium-dark', 'dark' => 'Dark'];

    /** @var callable */
    private $log;

    public function __construct(private array $catalog, callable $log)
    {
        $this->log = $log;
    }

    public function run(string $step): void
    {
        match ($step) {
            'settings' => $this->settings(),
            'taxonomies' => $this->taxonomies(),
            'products' => $this->products(),
            default => throw new \InvalidArgumentException("Unknown seed step: $step"),
        };
    }

    private function say(string $msg): void
    {
        ($this->log)("  $msg");
    }

    /* ------------------------------------------------------------------ */

    private function settings(): void
    {
        $opts = [
            'blogname' => 'Lereng Coffee Roasters',
            'blogdescription' => 'Specialty coffee from the slopes of Indonesia, roasted in Bali',
            'timezone_string' => 'Asia/Makassar',
            'date_format' => 'j F Y',
            'permalink_structure' => '/%postname%/',
            'woocommerce_currency' => 'IDR',
            'woocommerce_currency_pos' => 'left_space',
            'woocommerce_price_thousand_sep' => '.',
            'woocommerce_price_decimal_sep' => ',',
            'woocommerce_price_num_decimals' => '0',
            'woocommerce_default_country' => 'ID:BA',
            'woocommerce_store_address' => 'Jl. Raya Kerobokan No. 88',
            'woocommerce_store_city' => 'Badung',
            'woocommerce_store_postcode' => '80361',
            'woocommerce_allowed_countries' => 'specific',
            'woocommerce_specific_allowed_countries' => ['ID'],
            'woocommerce_ship_to_countries' => '',
            'woocommerce_weight_unit' => 'g',
            'woocommerce_dimension_unit' => 'cm',
            // PPN: 12% on a base of 11/12, so 11% effective. Prices on the shelf include it.
            'woocommerce_calc_taxes' => 'yes',
            'woocommerce_prices_include_tax' => 'yes',
            'woocommerce_tax_display_shop' => 'incl',
            'woocommerce_tax_display_cart' => 'incl',
            'woocommerce_tax_total_display' => 'single',
            'woocommerce_enable_reviews' => 'yes',
            'woocommerce_review_rating_verification_label' => 'yes',
            'woocommerce_enable_review_rating' => 'yes',
            'woocommerce_manage_stock' => 'yes',
            'woocommerce_notify_low_stock_amount' => '5',
            'woocommerce_enable_guest_checkout' => 'yes',
            'woocommerce_enable_checkout_login_reminder' => 'yes',
            'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
            'woocommerce_enable_myaccount_registration' => 'yes',
            'woocommerce_coming_soon' => 'no',
            'woocommerce_onboarding_profile' => ['completed' => true, 'skipped' => true],
            'woocommerce_task_list_hidden' => 'yes',
            'woocommerce_show_marketplace_suggestions' => 'no',
            'woocommerce_allow_tracking' => 'no',
        ];
        foreach ($opts as $k => $v) {
            update_option($k, $v);
        }

        // Tax rate.
        global $wpdb;
        $exists = $wpdb->get_var("SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_name = 'PPN' LIMIT 1");
        if (!$exists) {
            \WC_Tax::_insert_tax_rate(['tax_rate_country' => 'ID', 'tax_rate' => '11.0000', 'tax_rate_name' => 'PPN', 'tax_rate_priority' => 1, 'tax_rate_shipping' => 1, 'tax_rate_compound' => 0, 'tax_rate_order' => 0, 'tax_rate_class' => '']);
        }

        // Order storage tables (HPOS), the default for new stores.
        $sync = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class);
        if (!$sync->check_orders_table_exists()) {
            $sync->create_database_tables();
        }
        update_option('woocommerce_custom_orders_table_enabled', 'yes');
        update_option('woocommerce_custom_orders_table_data_sync_enabled', 'no');

        flush_rewrite_rules();
        $this->say('IDR without decimals, PPN 11% included, Bali store address, HPOS on');
    }

    private function taxonomies(): void
    {
        $ids = [];
        foreach ($this->catalog['categories'] as $c) {
            $parent = $c['parent'] ? ($ids[$c['parent']] ?? 0) : 0;
            $term = get_term_by('slug', $c['slug'], 'product_cat');
            $args = ['slug' => $c['slug'], 'parent' => $parent];
            $id = $term ? wp_update_term($term->term_id, 'product_cat', ['name' => $c['name']] + $args)['term_id'] : wp_insert_term($c['name'], 'product_cat', $args)['term_id'];
            update_term_meta($id, '_lereng_name_id', $c['name_id']);
            $ids[$c['slug']] = $id;
        }
        foreach (self::ATTRIBUTES as $slug => [$label]) {
            if (!wc_attribute_taxonomy_id_by_name($slug)) {
                wc_create_attribute(['name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => true]);
            }
            $tax = wc_attribute_taxonomy_name($slug);
            if (!taxonomy_exists($tax)) {
                register_taxonomy($tax, ['product'], ['hierarchical' => false, 'label' => $label]);
            }
        }
        delete_transient('wc_attribute_taxonomies');
        \WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
        $this->say(count($ids) . ' categories, ' . count(self::ATTRIBUTES) . ' attributes');
    }

    /* ------------------------------------------------------------------ */

    private function products(): void
    {
        $made = ['coffee' => 0, 'simple' => 0, 'variable-merch' => 0];
        foreach ($this->catalog['products'] as $i => $p) {
            switch ($p['type']) {
                case 'coffee':
                    $this->coffee($p, $i);
                    break;
                case 'simple':
                    $this->simple($p, $i);
                    break;
                case 'variable-merch':
                    $this->merch($p, $i);
                    break;
                default:
                    continue 2; // bundles, gift cards and subscriptions arrive with their modules
            }
            $made[$p['type']]++;
        }
        wc_delete_product_transients();
        $this->say(sprintf('%d coffees, %d simple products, %d merch', $made['coffee'], $made['simple'], $made['variable-merch']));
    }

    private function findBySlug(string $slug): ?int
    {
        $post = get_page_by_path($slug, OBJECT, 'product');
        return $post ? (int) $post->ID : null;
    }

    private function catId(string $slug): int
    {
        return (int) get_term_by('slug', $slug, 'product_cat')->term_id;
    }

    /** A global attribute on a product, creating terms as needed. */
    private function attribute(string $slug, array $values, bool $variation, int $position): WC_Product_Attribute
    {
        $tax = wc_attribute_taxonomy_name($slug);
        $termIds = [];
        foreach ($values as $order => $v) {
            $term = term_exists($v, $tax) ?: wp_insert_term($v, $tax);
            $termIds[] = (int) $term['term_id'];
            if ($slug === 'size' || $slug === 'grind') {
                update_term_meta((int) $term['term_id'], 'order', $order);
            }
        }
        $a = new WC_Product_Attribute();
        $a->set_id(wc_attribute_taxonomy_id_by_name($slug));
        $a->set_name($tax);
        $a->set_options($termIds);
        $a->set_position($position);
        $a->set_visible(!$variation);
        $a->set_variation($variation);
        return $a;
    }

    private function coffee(array $p, int $order): void
    {
        $id = $this->findBySlug($p['slug']);
        $product = $id ? new WC_Product_Variable($id) : new WC_Product_Variable();
        $product->set_name($p['name']);
        $product->set_slug($p['slug']);
        $product->set_status('publish');
        $product->set_menu_order($order);
        $product->set_catalog_visibility('visible');
        $product->set_short_description(sprintf('%s. %s.', implode(' · ', $p['notes']), self::ROAST_LABEL[$p['roast']] . ' roast'));
        $product->set_description(wpautop($p['story']));
        $product->set_category_ids(array_filter([$this->catId('coffee'), $this->catId($p['category'])]));
        $product->set_featured(in_array($p['slug'], ['gayo-wine-natural', 'java-puntang-honey', 'bali-kintamani-natural', 'lereng-house-espresso'], true));

        $sizes = array_values(array_unique(array_column($p['variations'], 'size')));
        $grinds = array_values(array_unique(array_column($p['variations'], 'grind')));
        $attrs = [
            $this->attribute('size', $sizes, true, 0),
            $this->attribute('grind', $grinds, true, 1),
            $this->attribute('roast', [self::ROAST_LABEL[$p['roast']]], false, 2),
            $this->attribute('process', [$p['process']], false, 3),
            $this->attribute('island', [$p['island']], false, 4),
            $this->attribute('taste', array_map('ucfirst', $p['taste']), false, 5),
            $this->attribute('species', [$p['species']], false, 6),
        ];
        $product->set_attributes($attrs);
        $product->set_default_attributes(['pa_size' => sanitize_title($sizes[0]), 'pa_grind' => sanitize_title($grinds[0])]);
        $id = $product->save();

        foreach (['producer', 'region', 'altitude', 'varietals', 'harvest', 'score', 'recipe', 'story', 'components', 'province'] as $k) {
            if (isset($p[$k])) {
                update_post_meta($id, "_lereng_$k", $p[$k]);
            }
        }
        update_post_meta($id, '_lereng_notes', implode(', ', $p['notes']));
        update_post_meta($id, '_lereng_kind', $p['kind']);
        update_post_meta($id, '_lereng_subscribable', $p['subscribable'] ? 'yes' : 'no');
        if (!empty($p['preorder'])) {
            update_post_meta($id, '_lereng_preorder', $p['preorder']);
        }

        // Variations, matched by SKU so a rerun updates prices instead of duplicating.
        $existing = [];
        foreach ($product->get_children() as $childId) {
            $existing[get_post_meta($childId, '_sku', true)] = $childId;
        }
        foreach ($p['variations'] as $vi => $v) {
            $var = new WC_Product_Variation($existing[$v['sku']] ?? 0);
            $var->set_parent_id($id);
            $var->set_attributes(['pa_size' => sanitize_title($v['size']), 'pa_grind' => sanitize_title($v['grind'])]);
            $var->set_sku($v['sku']);
            $var->set_regular_price((string) $v['price']);
            $var->set_weight((string) $v['weight_g']);
            $var->set_menu_order($vi);
            $var->set_status('publish');
            if (isset($p['stock'])) {
                $var->set_manage_stock(true);
                $var->set_stock_quantity(intdiv($p['stock'], max(1, count($grinds))));
                $var->set_backorders(!empty($p['preorder']) ? 'notify' : 'no');
            } else {
                $var->set_manage_stock(false);
                $var->set_stock_status('instock');
            }
            $var->save();
        }
        WC_Product_Variable::sync($id);
    }

    private function simple(array $p, int $order): void
    {
        $id = $this->findBySlug($p['slug']);
        $product = new WC_Product_Simple($id ?? 0);
        $product->set_name($p['name']);
        $product->set_slug($p['slug']);
        $product->set_status('publish');
        $product->set_menu_order($order);
        $product->set_sku($p['sku']);
        $product->set_regular_price((string) $p['price']);
        $product->set_weight((string) $p['weight_g']);
        $product->set_description(wpautop($p['description']));
        $product->set_short_description($p['description']);
        $cats = [$this->catId($p['category'])];
        $parent = get_term($cats[0], 'product_cat')->parent;
        if ($parent) {
            $cats[] = $parent;
        }
        $product->set_category_ids($cats);
        if (isset($p['stock'])) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity($p['stock']);
        }
        $id = $product->save();
        update_post_meta($id, '_lereng_subscribable', !empty($p['subscribable']) ? 'yes' : 'no');
    }

    private function merch(array $p, int $order): void
    {
        $id = $this->findBySlug($p['slug']);
        $product = $id ? new WC_Product_Variable($id) : new WC_Product_Variable();
        $product->set_name($p['name']);
        $product->set_slug($p['slug']);
        $product->set_status('publish');
        $product->set_menu_order($order);
        $product->set_description(wpautop($p['description']));
        $product->set_short_description($p['description']);
        $product->set_category_ids([$this->catId($p['category'])]);
        $product->set_attributes([$this->attribute('size', $p['sizes'], true, 0)]);
        $id = $product->save();
        $existing = [];
        foreach ($product->get_children() as $childId) {
            $existing[get_post_meta($childId, '_sku', true)] = $childId;
        }
        foreach ($p['sizes'] as $i => $size) {
            $sku = $p['sku'] . '-' . $size;
            $var = new WC_Product_Variation($existing[$sku] ?? 0);
            $var->set_parent_id($id);
            $var->set_attributes(['pa_size' => sanitize_title($size)]);
            $var->set_sku($sku);
            $var->set_regular_price((string) $p['price']);
            $var->set_weight((string) $p['weight_g']);
            $var->set_manage_stock(true);
            $var->set_stock_quantity(10);
            $var->set_menu_order($i);
            $var->save();
        }
        WC_Product_Variable::sync($id);
    }
}
