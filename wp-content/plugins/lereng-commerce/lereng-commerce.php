<?php
/**
 * Plugin Name:       Lereng Commerce
 * Description:       Store logic for Lereng Coffee Roasters: coffee product data, subscriptions, Indonesian shipping and payments, gift cards, bundles, wholesale.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Yodhimas Geffananda
 * Text Domain:       lereng
 * Domain Path:       /languages
 * WC requires at least: 9.0
 */

defined('ABSPATH') || exit;

define('LERENG_VERSION', '1.0.0');
define('LERENG_FILE', __FILE__);
define('LERENG_DIR', __DIR__);
define('LERENG_URL', plugin_dir_url(__FILE__));

// PSR-4 for Lereng\ → src/, so the plugin needs no Composer install on the server.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Lereng\\')) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 7)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

// Declare compatibility with order storage tables (HPOS) and the block cart/checkout.
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    if (!class_exists('WooCommerce')) {
        return;
    }
    load_plugin_textdomain('lereng', false, dirname(plugin_basename(__FILE__)) . '/languages');
    Lereng\Plugin::instance()->boot();
});

register_activation_hook(__FILE__, [Lereng\Install::class, 'activate']);
