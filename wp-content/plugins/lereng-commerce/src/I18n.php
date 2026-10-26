<?php

namespace Lereng;

use WC_Email;
use WC_Order;
use WP_Post;

/**
 * English and Bahasa Indonesia, chosen per visitor.
 *
 * - The header switch sets a cookie (?lang=id|en) and the storefront runs in id_ID: WordPress and
 *   WooCommerce strings come from their official language packs, ours from
 *   languages/lereng-id_ID.l10n.php (built by scripts/i18n-build.py).
 * - Content (pages, product copy, attribute terms, tasting notes) is stored next to the English
 *   at seed time: post meta _lereng_i18n, term meta _lereng_name_id, option lereng_i18n_id.
 *   A real store would use WPML or Polylang for this; the store's own needs are small enough to
 *   keep in one class.
 * - The orders remember the language they were placed in, so emails sent later from the admin
 *   (shipped, review request, renewals) reach the customer in their language.
 * The admin stays in each user's own profile language.
 */
final class I18n
{
    public const COOKIE = 'lereng_lang';
    public const META = '_lereng_i18n';
    public const OPTION = 'lereng_i18n_id';

    private static ?string $current = null;
    private static ?array $dict = null;
    private static int $emailSwitches = 0;

    public function register(): void
    {
        add_filter('determine_locale', [self::class, 'locale'], 20);
        add_filter('locale', [self::class, 'locale'], 20);
        add_action('change_locale', [self::class, 'reloadTextdomain']);
        add_action('init', [$this, 'remember'], 1);
        add_action('template_redirect', [$this, 'cleanUrl'], 1);

        // Content.
        add_filter('the_title', [$this, 'title'], 10, 2);
        add_filter('single_post_title', [$this, 'title'], 10, 2);
        add_filter('the_content', [$this, 'content'], 1);
        add_filter('woocommerce_product_get_description', [$this, 'productCopy'], 10, 2);
        add_filter('woocommerce_product_get_short_description', [$this, 'productShort'], 10, 2);
        add_filter('woocommerce_product_get_name', [$this, 'productName'], 10, 2);
        add_filter('woocommerce_product_variation_get_name', [$this, 'productName'], 10, 2);
        add_filter('woocommerce_product_title', [$this, 'productName'], 10, 2);
        add_filter('get_term', [$this, 'term'], 10, 2);
        add_filter('woocommerce_attribute_label', [$this, 'label'], 10, 1);
        add_filter('woocommerce_variation_option_name', [$this, 'label'], 10, 1);
        add_filter('render_block', [$this, 'templateText'], 10, 2);
        add_filter('woocommerce_attribute', [$this, 'attributeValues'], 10, 1);
        add_filter('woocommerce_order_item_display_meta_value', [$this, 'label'], 10, 1);
        add_filter('woocommerce_order_item_display_meta_key', [$this, 'label'], 10, 1);
        add_filter('single_term_title', [$this, 'label'], 10, 1);
        add_filter('woocommerce_get_item_data', static function (array $data): array {
            foreach ($data as &$d) {
                $d['value'] = self::text((string) ($d['value'] ?? ''));
                $d['key'] = self::text((string) ($d['key'] ?? ''));
            }
            return $data;
        });
        add_filter('document_title_parts', [$this, 'documentTitle']);

        // Orders keep the language they were placed in.
        add_action('woocommerce_checkout_create_order', [$this, 'stampOrder']);
        add_action('woocommerce_store_api_checkout_update_order_meta', [$this, 'stampOrder']);
        add_action('wp_login', static function (string $login, \WP_User $user): void {
            update_user_meta($user->ID, 'lereng_lang', self::current());
        }, 10, 2);
        add_filter('woocommerce_email_setup_locale', [$this, 'emailLocale']);
        add_action('woocommerce_email_sent', [$this, 'emailSent']);
        add_filter('woocommerce_email_recipient_cancelled_order', static fn($r) => $r); // admin emails stay in the site language
        foreach (['customer_processing_order', 'customer_completed_order', 'customer_on_hold_order', 'customer_refunded_order', 'customer_invoice', 'customer_note', 'lereng_shipped', 'lereng_review', 'customer_new_account', 'customer_reset_password'] as $id) {
            add_filter("woocommerce_email_recipient_$id", [$this, 'emailRecipient'], 1, 3);
        }
    }

    /* ---------------- language ---------------- */

    public static function current(): string
    {
        if (self::$current !== null) {
            return self::$current;
        }
        $q = isset($_GET['lang']) ? sanitize_key(wp_unslash($_GET['lang'])) : '';
        $c = isset($_COOKIE[self::COOKIE]) ? sanitize_key(wp_unslash($_COOKIE[self::COOKIE])) : '';
        return self::$current = in_array($q, ['en', 'id'], true) ? $q : (in_array($c, ['en', 'id'], true) ? $c : 'en');
    }

    public static function isId(): bool
    {
        return self::storefront() && self::current() === 'id';
    }

    /** The storefront: pages, the Store API and our own REST routes. Not wp-admin, not wc-admin. */
    public static function storefront(): bool
    {
        if (self::$emailSwitches > 0) {
            return true;
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $rest = str_contains($uri, '/wp-json/') ? substr($uri, strpos($uri, '/wp-json/') + 9) : (isset($_GET['rest_route']) ? ltrim((string) $_GET['rest_route'], '/') : null);
        if ($rest !== null) {
            return str_starts_with($rest, 'wc/store') || str_starts_with($rest, 'lereng/');
        }
        if (str_contains($uri, '/wp-admin/') && !str_contains($uri, 'admin-ajax.php')) {
            return false;
        }
        return !str_contains($uri, 'wp-login.php') || self::current() === 'id';
    }

    private static function storeApi(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return str_contains($uri, '/wp-json/wc/store') || str_starts_with((string) ($_GET['rest_route'] ?? ''), '/wc/store');
    }

    public static function locale(string $locale): string
    {
        if (self::$emailSwitches > 0) {
            return 'id_ID';
        }
        return self::isId() ? 'id_ID' : $locale;
    }

    public static function reloadTextdomain(): void
    {
        unload_textdomain('lereng', true);
        load_textdomain('lereng', LERENG_DIR . '/languages/lereng-' . determine_locale() . '.mo');
    }

    /** ?lang= sets the cookie, then the URL is cleaned up so links stay shareable. */
    public function remember(): void
    {
        if (!isset($_GET['lang']) || headers_sent()) {
            return;
        }
        $lang = self::current();
        setcookie(self::COOKIE, $lang, ['expires' => time() + YEAR_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'samesite' => 'Lax']);
        $_COOKIE[self::COOKIE] = $lang;
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'lereng_lang', $lang);
        }
    }

    public function cleanUrl(): void
    {
        if (isset($_GET['lang']) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            wp_safe_redirect(remove_query_arg('lang'), 302);
            exit;
        }
    }

    /** Strings the storefront scripts show, passed to them as window.lerengBase.i18n. */
    public static function jsStrings(): array
    {
        if (!self::isId()) {
            return [];
        }
        $all = [
            'Something went wrong.' => __('Something went wrong.', 'lereng'),
            'Something went wrong. Please try again.' => __('Something went wrong. Please try again.', 'lereng'),
            'Payment received. Taking you to your order…' => __('Payment received. Taking you to your order…', 'lereng'),
            'This payment window has closed and the order was cancelled. Your items are back in stock.' => __('This payment window has closed and the order was cancelled. Your items are back in stock.', 'lereng'),
            'The payment was declined.' => __('The payment was declined.', 'lereng'),
            'Copy' => __('Copy', 'lereng'),
            'Copied' => __('Copied', 'lereng'),
            '%1$s left of %2$s' => __('%1$s left of %2$s', 'lereng'),
        ];
        return $all;
    }

    /* ---------------- dictionary ---------------- */

    private static function dict(): array
    {
        return self::$dict ??= (array) get_option(self::OPTION, ['terms' => [], 'phrases' => []]) + ['terms' => [], 'phrases' => []];
    }

    /** A short label (tasting note, attribute term, region, recipe) in the current language. */
    public static function text(string $s): string
    {
        if (!self::isId() || $s === '') {
            return $s;
        }
        $d = self::dict();
        if (isset($d['terms'][$s])) {
            return $d['terms'][$s];
        }
        return self::phrases($s);
    }

    public static function phrases(string $s): string
    {
        foreach (self::dict()['phrases'] as [$en, $id]) {
            $s = str_replace($en, $id, $s);
        }
        return $s;
    }

    /** A product's or page's stored Indonesian field, or the English fallback. */
    public static function field(int $postId, string $key, string $fallback): string
    {
        if (!self::isId()) {
            return $fallback;
        }
        $t = get_post_meta($postId, self::META, true);
        return is_array($t) && !empty($t[$key]) ? $t[$key] : $fallback;
    }

    /* ---------------- content ---------------- */

    public function title($title, $post = null)
    {
        $post = $post instanceof WP_Post ? $post : get_post($post);
        if (!$post || !self::isId() || !in_array($post->post_type, ['page'], true)) {
            return $title;
        }
        return self::field($post->ID, 'title', (string) $title);
    }

    public function documentTitle(array $parts): array
    {
        if (self::isId() && is_page()) {
            $parts['title'] = self::field((int) get_queried_object_id(), 'title', $parts['title'] ?? '');
        }
        return $parts;
    }

    public function content(string $content): string
    {
        $post = get_post();
        if (!$post || $post->post_type !== 'page' || !self::isId()) {
            return $content;
        }
        // Only swap the page's own content, not some other block's the_content call.
        return trim($content) === trim($post->post_content) ? self::field($post->ID, 'content', $content) : $content;
    }

    public function productCopy($value, $product)
    {
        if (!self::isId()) {
            return $value;
        }
        $id = $product->get_parent_id() ?: $product->get_id();
        $t = get_post_meta($id, self::META, true);
        if (!is_array($t)) {
            return $value;
        }
        if (!empty($t['description'])) {
            return wpautop($t['description']);
        }
        return !empty($t['story']) ? wpautop($t['story']) : $value;
    }

    public function productShort($value, $product)
    {
        if (!self::isId()) {
            return $value;
        }
        $id = $product->get_parent_id() ?: $product->get_id();
        $t = get_post_meta($id, self::META, true);
        if (is_array($t) && !empty($t['description'])) {
            return $t['description'];
        }
        $notes = get_post_meta($id, '_lereng_notes', true);
        $roast = $product->get_attribute('pa_roast');
        if ($notes && $roast) {
            return implode(' · ', array_map([self::class, 'text'], array_map('trim', explode(',', $notes)))) . '. Roast ' . strtolower(self::text($roast)) . '.';
        }
        return $value;
    }

    public function productName($name, $product = null)
    {
        return self::isId() ? self::phrases((string) $name) : $name;
    }

    public function term($term, $taxonomy)
    {
        if (!is_object($term) || !self::isId()) {
            return $term;
        }
        if ($taxonomy === 'product_cat') {
            $id = get_term_meta($term->term_id, '_lereng_name_id', true);
            if ($id) {
                $term->name = $id;
            }
        } elseif (str_starts_with((string) $taxonomy, 'pa_') && self::storeApi()) {
            // Cart and checkout blocks read variation values from the terms. Elsewhere attribute
            // names stay English for the code that compares them, and are translated where shown.
            $term->name = self::text($term->name);
        }
        return $term;
    }

    /** The "Additional information" table: each linked or plain value. */
    public function attributeValues($html)
    {
        if (!self::isId()) {
            return $html;
        }
        $out = preg_replace_callback('/>([^<>]+)</', static fn($m) => '>' . esc_html(self::text(trim(html_entity_decode($m[1])))) . '<', '>' . $html . '<');
        return $out === null ? $html : substr($out, 1, -1);
    }

    public function label($label)
    {
        return self::text((string) $label);
    }

    /** Hard-coded labels in block templates (navigation, search, headings) use the gettext dictionary. */
    public function templateText(string $html, array $block): string
    {
        static $blocks = ['core/navigation-link', 'core/heading', 'core/paragraph', 'core/button', 'woocommerce/product-search', 'core/navigation'];
        $name = $block['blockName'] ?? '';
        if (!self::isId()) {
            return $html;
        }
        if (str_starts_with($name, 'woocommerce/product-filter') || $name === 'woocommerce/product-filters') {
            // Filter options and active-filter chips list attribute terms by name.
            return (string) preg_replace_callback('/>([^<>]+)</', static function ($m) {
                $s = trim(html_entity_decode($m[1], ENT_QUOTES));
                $t = self::text($s);
                return '>' . ($t === $s ? $m[1] : str_replace(trim($m[1]), esc_html($t), $m[1])) . '<';
            }, $html);
        }
        if (!in_array($name, $blocks, true)) {
            return $html;
        }
        $t = static function (string $s): string {
            $trim = trim(html_entity_decode($s, ENT_QUOTES));
            if ($trim === '') {
                return $s;
            }
            $tr = translate($trim, 'lereng');
            return $tr === $trim ? $s : str_replace(trim($s), esc_html($tr), $s);
        };
        $html = (string) preg_replace_callback('/>([^<>]+)</', static fn($m) => '>' . $t($m[1]) . '<', $html);
        return (string) preg_replace_callback('/\s(placeholder|aria-label|value)="([^"]+)"/', static fn($m) => ' ' . $m[1] . '="' . esc_attr(trim($t($m[2]))) . '"', $html);
    }

    /* ---------------- orders and emails ---------------- */

    public function stampOrder($order): void
    {
        if ($order instanceof WC_Order) {
            $order->update_meta_data('_lereng_lang', self::current());
        }
    }

    public static function orderLanguage($order): string
    {
        if ($order instanceof WC_Order) {
            $lang = (string) $order->get_meta('_lereng_lang');
            if ($lang === '' && $order->get_customer_id()) {
                $lang = (string) get_user_meta($order->get_customer_id(), 'lereng_lang', true);
            }
            return $lang === 'id' ? 'id' : 'en';
        }
        if ($order instanceof \WP_User) {
            return get_user_meta($order->ID, 'lereng_lang', true) === 'id' ? 'id' : 'en';
        }
        return 'en';
    }

    /** WooCommerce switches customer emails to the site language; we pick the customer's instead. */
    public function emailLocale(bool $switch): bool
    {
        return false;
    }

    public function emailRecipient($recipient, $object = null, $email = null)
    {
        $lang = self::orderLanguage($object);
        if ($lang === 'id' && self::$emailSwitches === 0) {
            self::$emailSwitches++;
            switch_to_locale('id_ID');
            if ($email instanceof WC_Email) {
                // Subject and heading were set up in the old language; ask again.
                $email->init_settings();
            }
        } elseif ($lang === 'en' && self::isId() && self::$emailSwitches === 0) {
            self::$emailSwitches = -1;
            self::$current = 'en';
            switch_to_locale('en_US');
        }
        return $recipient;
    }

    public function emailSent(): void
    {
        if (self::$emailSwitches !== 0) {
            restore_previous_locale();
            if (self::$emailSwitches === -1) {
                self::$current = null;
            }
            self::$emailSwitches = 0;
        }
    }
}

