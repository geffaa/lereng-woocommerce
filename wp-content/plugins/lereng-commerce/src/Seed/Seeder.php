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
    public const STEPS = ['settings', 'taxonomies', 'products', 'shipping', 'payments', 'extras', 'coupons', 'pages', 'images'];

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
            'shipping' => $this->shipping(),
            'payments' => $this->payments(),
            'extras' => $this->extras(),
            'coupons' => $this->coupons(),
            'pages' => $this->pages(),
            'images' => $this->images(),
            'history' => (new History($this->catalog, json_decode(file_get_contents(WP_CONTENT_DIR . '/lereng-data/people.json'), true), $this->log))->run(),
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
            'woocommerce_checkout_phone_field' => 'required', // couriers call before delivering
            'woocommerce_checkout_company_field' => 'optional',
            'woocommerce_checkout_address_2_field' => 'optional',
            'woocommerce_enable_checkout_login_reminder' => 'yes',
            'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
            'woocommerce_enable_myaccount_registration' => 'yes',
            'woocommerce_registration_generate_password' => 'no', // customers choose their password when registering
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

    private function shipping(): void
    {
        // One zone for Indonesia with the courier method; prices come from RateTable.
        $zoneId = null;
        foreach (\WC_Shipping_Zones::get_zones() as $z) {
            if ($z['zone_name'] === 'Indonesia') {
                $zoneId = $z['id'];
            }
        }
        $zone = new \WC_Shipping_Zone($zoneId);
        $zone->set_zone_name('Indonesia');
        $zone->set_zone_order(1);
        $zone->set_locations([['code' => 'ID', 'type' => 'country']]);
        $zone->save();
        $has = array_filter($zone->get_shipping_methods(), static fn($m) => $m->id === 'lereng_courier');
        if (!$has) {
            $zone->add_shipping_method('lereng_courier');
        }

        // Pickup at the roastery (WooCommerce Local Pickup, shown as a choice in the block checkout).
        update_option('woocommerce_pickup_location_settings', ['enabled' => 'yes', 'title' => 'Pick up at the roastery', 'tax_status' => 'taxable', 'cost' => '']);
        update_option('pickup_location_pickup_locations', [[
            'name' => 'Lereng Roastery, Kerobokan',
            'address' => ['address_1' => 'Jl. Raya Kerobokan No. 88', 'city' => 'Badung', 'state' => 'BA', 'postcode' => '80361', 'country' => 'ID'],
            'details' => 'Tuesday to Sunday, 8:00–17:00. Ready two hours after you order (coffee on the next roast day).',
            'enabled' => true,
        ]]);

        // Cold brew must stay cold: a shipping class the courier method recognises.
        $term = term_exists('chilled-bali-only', 'product_shipping_class') ?: wp_insert_term('Chilled (Bali only)', 'product_shipping_class', ['slug' => 'chilled-bali-only', 'description' => 'Same-day delivery in south Bali or pickup only']);
        foreach ($this->catalog['products'] as $p) {
            if (($p['shipping_class'] ?? '') === 'chilled-bali-only' && ($id = $this->findBySlug($p['slug']))) {
                $prod = wc_get_product($id);
                $prod->set_shipping_class_id((int) $term['term_id']);
                $prod->save();
            }
        }
        // WooCommerce shows shipping costs only once an address is entered.
        update_option('woocommerce_shipping_cost_requires_address', 'yes');
        update_option('woocommerce_enable_shipping_calc', 'yes');
        $this->say('Indonesia zone with couriers, roastery pickup, chilled class for cold brew');
    }

    private function payments(): void
    {
        update_option('woocommerce_lereng_qris_settings', ['enabled' => 'yes', 'expiry' => '15']);
        update_option('woocommerce_lereng_va_settings', ['enabled' => 'yes', 'expiry' => '1440']);
        // Bank transfer by invoice stays available for wholesale accounts (see Wholesale module).
        update_option('woocommerce_bacs_settings', ['enabled' => 'yes', 'title' => 'Invoice · bank transfer within 14 days', 'description' => 'For wholesale accounts. We email an invoice; transfer to the account below within 14 days.', 'instructions' => 'Transfer to BCA 123 456 7890 (PT Lereng Kopi Nusantara) within 14 days, with your order number as the reference.']);
        update_option('woocommerce_bacs_accounts', [['account_name' => 'PT Lereng Kopi Nusantara', 'account_number' => '1234567890', 'bank_name' => 'BCA', 'sort_code' => '', 'iban' => '', 'bic' => 'CENAIDJA']]);
        // Branded emails.
        update_option('woocommerce_email_base_color', '#141412');
        update_option('woocommerce_email_background_color', '#F6F4EF');
        update_option('woocommerce_email_body_background_color', '#FFFFFF');
        update_option('woocommerce_email_text_color', '#141412');
        update_option('woocommerce_email_footer_text', 'Lereng Coffee Roasters · Jl. Raya Kerobokan No. 88, Badung, Bali · A portfolio demo store');
        update_option('woocommerce_email_from_name', 'Lereng Coffee Roasters');
        update_option('woocommerce_email_from_address', 'hello@lereng.example');
        update_option('woocommerce_cod_settings', ['enabled' => 'no']);
        update_option('woocommerce_gateway_order', ['lereng_qris' => 0, 'lereng_va' => 1, 'bacs' => 2]);
        update_option('lereng_payments_sandbox', 'yes');
        \Lereng\Payments\Payments::serverKey();
        // Sold out, to show restock alerts: the flat-burr grinder and the 250 g Gesha.
        if ($g = $this->findBySlug('electric-grinder-flat')) {
            $p = wc_get_product($g);
            $p->set_stock_quantity(0);
            $p->save();
        }
        flush_rewrite_rules();
        $this->say('QRIS and virtual account (sandbox), invoice transfer for wholesale, branded emails');
    }

    /** The gift card and the bundles: products that need the modules' extra data. */
    private function extras(): void
    {
        $by = [];
        foreach ($this->catalog['products'] as $p) {
            $by[$p['slug']] = $p;
        }

        // Gift card: variable product, one variation per amount, virtual and outside PPN.
        $g = $by['lereng-gift-card'];
        $id = $this->findBySlug($g['slug']);
        $product = $id ? new WC_Product_Variable($id) : new WC_Product_Variable();
        $product->set_name($g['name']);
        $product->set_slug($g['slug']);
        $product->set_status('publish');
        $product->set_description(wpautop($g['description']));
        $product->set_short_description($g['description']);
        $product->set_category_ids([$this->catId('gift-cards')]);
        $product->set_tax_status('none');
        $amounts = array_map(static fn($a) => 'Rp ' . number_format($a, 0, ',', '.'), $g['amounts']);
        $attr = new WC_Product_Attribute();
        $attr->set_name('Amount');
        $attr->set_options($amounts);
        $attr->set_variation(true);
        $attr->set_visible(true);
        $product->set_attributes([$attr]);
        $id = $product->save();
        update_post_meta($id, '_lereng_gift_card', 'yes');
        $existing = [];
        foreach ($product->get_children() as $c) {
            $existing[get_post_meta($c, '_sku', true)] = $c;
        }
        foreach ($g['amounts'] as $i => $a) {
            $sku = 'LR-GIFT-' . ($a / 1000) . 'K';
            $v = new WC_Product_Variation($existing[$sku] ?? 0);
            $v->set_parent_id($id);
            $v->set_attributes(['amount' => $amounts[$i]]);
            $v->set_sku($sku);
            $v->set_regular_price((string) $a);
            $v->set_virtual(true);
            $v->set_tax_status('none');
            $v->set_menu_order($i);
            $v->save();
        }
        WC_Product_Variable::sync($id);

        // Bundles.
        $varId = function (string $slug, string $size, string $grind): int {
            $pid = $this->findBySlug($slug);
            foreach (wc_get_product($pid)->get_children() as $c) {
                $v = wc_get_product($c);
                if ($v->get_attribute('pa_size') === $size && $v->get_attribute('pa_grind') === $grind) {
                    return $c;
                }
            }
            return $pid;
        };
        foreach (['discovery-box', 'brew-starter-kit', 'office-pack'] as $slug) {
            $b = $by[$slug];
            $id = $this->findBySlug($slug);
            $product = new WC_Product_Simple($id ?? 0);
            $product->set_name($b['name']);
            $product->set_slug($slug);
            $product->set_status('publish');
            $product->set_description(wpautop($b['description']));
            $product->set_short_description($b['description']);
            $product->set_category_ids([$this->catId('bundles')]);
            $product->set_sku('LR-BUNDLE-' . strtoupper($slug));
            if ($b['type'] === 'bundle-pick') {
                $prices = array_column(\Lereng\Bundles\Bundles::pickable(), 'price200');
                sort($prices);
                $product->set_regular_price((string) \Lereng\Bundles\Pricing::pick(array_slice($prices, 0, $b['pick']), $b['discount']));
                $product->set_weight((string) ($b['pick'] * 130));
                $id = $product->save();
                update_post_meta($id, '_lereng_bundle', 'pick');
                update_post_meta($id, '_lereng_pick_count', $b['pick']);
            } else {
                $items = [];
                $priced = [];
                $weight = 0;
                foreach ($b['items'] as [$compSlug, $qty]) {
                    $cid = isset($b['variation']) ? $varId($compSlug, $b['variation']['size'], $b['variation']['grind']) : $this->findBySlug($compSlug);
                    $comp = wc_get_product($cid);
                    if ($comp->is_type('variable')) {
                        $cid = $varId($compSlug, '200g', 'Whole bean');
                        $comp = wc_get_product($cid);
                    }
                    $items[] = [$cid, $qty];
                    $priced[] = [(int) $comp->get_price(), $qty];
                    $weight += (float) $comp->get_weight() * $qty;
                }
                $product->set_regular_price((string) \Lereng\Bundles\Pricing::fixed($priced, $b['discount']));
                $product->set_weight((string) $weight);
                $id = $product->save();
                update_post_meta($id, '_lereng_bundle', 'fixed');
                update_post_meta($id, '_lereng_bundle_items', $items);
            }
            update_post_meta($id, '_lereng_bundle_discount', $b['discount']);
        }
        // Roaster's Choice: subscription only. Shelf price set so the subscription price is a round 125k / 285k.
        $r = $by['roasters-choice-subscription'];
        $id = $this->findBySlug($r['slug']);
        $product = $id ? new WC_Product_Variable($id) : new WC_Product_Variable();
        $product->set_name($r['name']);
        $product->set_slug($r['slug']);
        $product->set_status('publish');
        $product->set_description(wpautop($r['description']));
        $product->set_short_description($r['description']);
        $product->set_category_ids([$this->catId('subscriptions'), $this->catId('coffee')]);
        $grinds = ['Whole bean', 'Espresso', 'Pour over', 'French press', 'Moka pot'];
        $product->set_attributes([$this->attribute('size', ['200g', '500g'], true, 0), $this->attribute('grind', $grinds, true, 1)]);
        $product->set_default_attributes(['pa_size' => '200g', 'pa_grind' => 'whole-bean']);
        $id = $product->save();
        update_post_meta($id, '_lereng_subscribable', 'only');
        update_post_meta($id, '_lereng_notes', "Roaster's pick, Tasting card, Brew recipe");
        $existing = [];
        foreach ($product->get_children() as $c) {
            $existing[get_post_meta($c, '_sku', true)] = $c;
        }
        foreach (['200g' => [139000, 240], '500g' => [317000, 540]] as $size => [$price, $grams]) {
            foreach ($grinds as $gi => $grind) {
                $sku = 'LR-RC-' . strtoupper($size) . '-' . strtoupper(substr($grind, 0, 2));
                $v = new WC_Product_Variation($existing[$sku] ?? 0);
                $v->set_parent_id($id);
                $v->set_attributes(['pa_size' => sanitize_title($size), 'pa_grind' => sanitize_title($grind)]);
                $v->set_sku($sku);
                $v->set_regular_price((string) $price);
                $v->set_weight((string) $grams);
                $v->set_menu_order($gi);
                $v->save();
            }
        }
        WC_Product_Variable::sync($id);
        wc_delete_product_transients();
        $this->say("gift card (4 amounts), Discovery Box, Brew Starter Kit, Office Pack, Roaster's Choice");
    }

    private function coupons(): void
    {
        $defs = [
            ['WELCOME10', 'percent', 10, ['first' => true, 'individual' => true, 'desc' => '10% off a first order']],
            ['BALI50', 'fixed_cart', 50000, ['min' => 400000, 'per_user' => 1, 'desc' => 'Rp 50.000 off orders over Rp 400.000']],
            ['FREEONGKIR', 'fixed_cart', 0, ['free_shipping' => true, 'min' => 250000, 'desc' => 'Free delivery over Rp 250.000']],
            ['GAYO20', 'percent', 20, ['products' => ['gayo-wine-natural', 'gayo-washed', 'gayo-wet-hulled'], 'expires' => '+30 days', 'desc' => '20% off Gayo coffees this month']],
            ['KOPIPAGI', 'percent', 15, ['expires' => '-3 days', 'desc' => 'Expired launch code (kept to show the message)']],
        ];
        foreach ($defs as [$code, $type, $amount, $o]) {
            $id = wc_get_coupon_id_by_code($code);
            $c = new \WC_Coupon($id ?: 0);
            $c->set_code($code);
            $c->set_discount_type($type);
            $c->set_amount($amount);
            $c->set_description($o['desc']);
            $c->set_individual_use(!empty($o['individual']));
            $c->set_free_shipping(!empty($o['free_shipping']));
            $c->set_minimum_amount($o['min'] ?? '');
            $c->set_usage_limit_per_user($o['per_user'] ?? 0);
            if (!empty($o['products'])) {
                $c->set_product_ids(array_filter(array_map(fn($s) => $this->findBySlug($s), $o['products'])));
            }
            $c->set_date_expires(isset($o['expires']) ? strtotime($o['expires']) : null);
            if (!empty($o['first'])) {
                $c->update_meta_data('_lereng_first_order', 'yes');
            }
            $c->save();
        }
        $this->say(count($defs) . ' coupons: WELCOME10, BALI50, FREEONGKIR, GAYO20, KOPIPAGI (expired)');
    }

    private function pages(): void
    {
        $pages = json_decode(file_get_contents(WP_CONTENT_DIR . '/lereng-data/pages.json'), true);
        foreach ($pages as $slug => $page) {
            $existing = get_page_by_path($slug);
            $args = ['post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $page['title'], 'post_content' => $page['content'], 'page_template' => $page['template'] ?? ''];
            $id = $existing ? wp_update_post(['ID' => $existing->ID] + $args) : wp_insert_post($args);
            if (!empty($page['option'])) {
                update_option($page['option'], $id);
            }
        }
        flush_rewrite_rules();
        $this->say(count($pages) . ' content pages');
    }

    /** Imports data/images into the media library and sets each product's image and gallery. */
    private function images(): void
    {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        // Product images are 1200x1500; three sizes are plenty and keep the demo export small.
        foreach (['medium_large', '1536x1536', '2048x2048'] as $size) {
            remove_image_size($size);
        }
        update_option('woocommerce_thumbnail_cropping', 'custom');
        update_option('woocommerce_thumbnail_cropping_custom_width', '4');
        update_option('woocommerce_thumbnail_cropping_custom_height', '5');
        update_option('woocommerce_thumbnail_image_width', '480');
        update_option('woocommerce_single_image_width', '900');

        $map = json_decode(file_get_contents(WP_CONTENT_DIR . '/lereng-data/images.json'), true);
        $dir = WP_CONTENT_DIR . '/lereng-data/images/';
        $ids = [];
        $attach = function (string $name, string $alt) use (&$ids, $dir): int {
            if (isset($ids[$name])) {
                return $ids[$name];
            }
            $existing = get_posts(['post_type' => 'attachment', 'name' => $name, 'posts_per_page' => 1, 'fields' => 'ids', 'post_status' => 'inherit']);
            if ($existing) {
                return $ids[$name] = (int) $existing[0];
            }
            $upload = wp_upload_bits("$name.jpg", null, file_get_contents($dir . "$name.jpg"));
            $id = wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_title' => $alt, 'post_name' => $name, 'post_status' => 'inherit'], $upload['file']);
            wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
            update_post_meta($id, '_wp_attachment_image_alt', $alt);
            return $ids[$name] = $id;
        };
        $count = 0;
        foreach ($map as $slug => $names) {
            $pid = $this->findBySlug($slug);
            if (!$pid) {
                continue;
            }
            $product = wc_get_product($pid);
            $title = $product->get_name();
            $images = [];
            foreach ($names as $i => $name) {
                $images[] = $attach($name, $i === 0 ? $title : $title . ' · ' . str_replace(['photo-', 'eq-', 'merch-', 'promo-', '-'], ['', '', '', '', ' '], $name));
            }
            $product->set_image_id($images[0]);
            $product->set_gallery_image_ids(array_slice($images, 1));
            $product->save();
            $count++;
        }
        $this->say(sprintf('%d images, %d products illustrated', count($ids), $count));
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
