<?php

namespace Lereng\Account;

use WP_REST_Request;
use WP_REST_Response;

/**
 * "Email me when it's back" for sold-out products and variations (micro-lots sell out). When
 * stock comes back, everyone waiting gets one email, oldest first; addresses are kept only until
 * then.
 */
final class Restock
{
    public function register(): void
    {
        add_action('init', fn() => register_block_type('lereng/restock', ['api_version' => 3, 'uses_context' => ['postId'], 'render_callback' => [$this, 'form']]));
        add_action('rest_api_init', fn() => register_rest_route('lereng/v1', '/restock', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => [$this, 'subscribe']]));
        add_action('woocommerce_product_set_stock_status', [$this, 'back'], 10, 3);
        add_action('woocommerce_variation_set_stock_status', [$this, 'back'], 10, 3);
        add_action('admin_menu', fn() => add_submenu_page('woocommerce', __('Restock alerts', 'lereng'), __('Restock alerts', 'lereng'), 'manage_woocommerce', 'lereng-restock', [$this, 'admin']));
    }

    private static function t(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'lereng_restock';
    }

    public function form(array $attrs = [], string $content = '', $block = null): string
    {
        $product = wc_get_product($block->context['postId'] ?? get_the_ID());
        if (!$product || $product->is_type('external')) {
            return '';
        }
        wp_enqueue_script('lereng-restock', LERENG_URL . 'assets/js/restock.js', ['jquery'], LERENG_VERSION, true);
        $hidden = $product->is_type('variable') || $product->is_in_stock() ? 'hidden' : '';
        return sprintf('<form class="restock" data-restock data-product="%d" %s><p>%s</p><div><input type="email" name="email" required placeholder="%s"><button type="submit">%s</button></div><p class="restock__msg" role="status"></p></form>',
            $product->get_id(), $hidden, esc_html__("Sold out. We'll email you once when it's back.", 'lereng'), esc_attr__('Your email', 'lereng'), esc_html__('Notify me', 'lereng'));
    }

    public function subscribe(WP_REST_Request $r): WP_REST_Response
    {
        global $wpdb;
        $email = sanitize_email((string) $r['email']);
        $pid = (int) $r['product_id'];
        $vid = (int) $r['variation_id'];
        $product = wc_get_product($vid ?: $pid);
        if (!is_email($email) || !$product) {
            return new WP_REST_Response(['message' => __('Please enter a valid email.', 'lereng')], 422);
        }
        if ($product->is_in_stock()) {
            return new WP_REST_Response(['message' => __("Good news: it's in stock right now.", 'lereng')], 200);
        }
        $wpdb->query($wpdb->prepare('INSERT INTO ' . self::t() . ' (product_id, variation_id, email, created_at) VALUES (%d, %d, %s, %s) ON DUPLICATE KEY UPDATE notified_at = NULL', $pid, $vid, $email, current_time('mysql', true)));
        return new WP_REST_Response(['message' => sprintf(__("Done. We'll email %s once when it's back.", 'lereng'), $email)], 201);
    }

    public function back(int $id, string $status, $product = null): void
    {
        global $wpdb;
        if ($status !== 'instock') {
            return;
        }
        $product = $product ?: wc_get_product($id);
        $pid = $product->get_parent_id() ?: $product->get_id();
        $vid = $product->get_parent_id() ? $product->get_id() : 0;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t() . ' WHERE product_id = %d AND variation_id = %d AND notified_at IS NULL ORDER BY id LIMIT 200', $pid, $vid), ARRAY_A);
        foreach ($rows as $row) {
            $subject = sprintf(__('%s is back', 'lereng'), wp_strip_all_tags($product->get_name()));
            $body = '<p>' . esc_html(sprintf(__('%s is back in stock. Micro-lots go quickly, so we wrote straight away.', 'lereng'), wp_strip_all_tags($product->get_name()))) . '</p><p><a href="' . esc_url($product->get_permalink()) . '">' . esc_html__('See it in the shop', 'lereng') . '</a></p>';
            WC()->mailer()->send($row['email'], $subject, WC()->mailer()->wrap_message($subject, $body));
            $wpdb->update(self::t(), ['notified_at' => current_time('mysql', true)], ['id' => $row['id']]);
        }
    }

    public function admin(): void
    {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT product_id, variation_id, SUM(notified_at IS NULL) waiting, COUNT(*) total FROM ' . self::t() . ' GROUP BY product_id, variation_id ORDER BY waiting DESC', ARRAY_A);
        echo '<div class="wrap"><h1>' . esc_html__('Restock alerts', 'lereng') . '</h1><table class="widefat striped"><thead><tr><th>Product</th><th>Waiting</th><th>Notified</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $p = wc_get_product((int) ($r['variation_id'] ?: $r['product_id']));
            printf('<tr><td>%s</td><td>%d</td><td>%d</td></tr>', esc_html($p ? wp_strip_all_tags($p->get_name()) : '#' . $r['product_id']), (int) $r['waiting'], (int) $r['total'] - (int) $r['waiting']);
        }
        echo '</tbody></table></div>';
    }
}
