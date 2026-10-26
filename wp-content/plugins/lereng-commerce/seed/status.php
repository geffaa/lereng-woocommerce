<?php
// Quick health check of the dev site.
lereng_out('wc=' . WC()->version . ' php=' . PHP_VERSION . ' theme=' . get_stylesheet());
lereng_out('lereng active=' . (is_plugin_active('lereng-commerce/lereng-commerce.php') ? 'yes' : 'no') . ' booted=' . (class_exists(\Lereng\Plugin::class) ? 'yes' : 'no'));
lereng_out('products=' . wp_count_posts('product')->publish . ' orders=' . count(wc_get_orders(['limit' => -1, 'return' => 'ids'])));
lereng_out('hpos=' . (\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'on' : 'off') . ' currency=' . get_woocommerce_currency());
global $wpdb;
lereng_out('variations=' . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product_variation'") . ' price(gayo-wine 200g)=' . wc_get_product(get_page_by_path('gayo-wine-natural', OBJECT, 'product')->ID)->get_price_html());
