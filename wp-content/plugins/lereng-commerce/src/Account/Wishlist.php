<?php

namespace Lereng\Account;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Wishlist: a heart on product cards and pages. Guests keep it in the browser; signing in merges
 * it into the account (user meta), where it shows under My account → Wishlist.
 */
final class Wishlist
{
    public const META = '_lereng_wishlist';

    public function register(): void
    {
        add_action('init', function (): void {
            register_block_type('lereng/wishlist', ['api_version' => 3, 'uses_context' => ['postId'], 'render_callback' => [$this, 'button']]);
            add_rewrite_endpoint('wishlist', EP_PAGES);
        });
        add_filter('woocommerce_get_query_vars', static fn(array $v): array => $v + ['wishlist' => 'wishlist']);
        add_filter('woocommerce_account_menu_items', static function (array $items): array {
            $logout = $items['customer-logout'] ?? null;
            unset($items['customer-logout']);
            $items['wishlist'] = __('Wishlist', 'lereng');
            if ($logout) {
                $items['customer-logout'] = $logout;
            }
            return $items;
        });
        add_action('woocommerce_account_wishlist_endpoint', [$this, 'page']);
        add_action('rest_api_init', fn() => register_rest_route('lereng/v1', '/wishlist', [
            ['methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => [$this, 'get']],
            ['methods' => 'POST', 'permission_callback' => 'is_user_logged_in', 'callback' => [$this, 'set']],
        ]));
        add_action('wp_enqueue_scripts', static function (): void {
            wp_enqueue_script('lereng-wishlist', LERENG_URL . 'assets/js/wishlist.js', [], LERENG_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
            wp_localize_script('lereng-wishlist', 'lerengWishlist', ['nonce' => wp_create_nonce('wp_rest'), 'loggedIn' => is_user_logged_in(), 'url' => rest_url('lereng/v1/wishlist')]);
        });
    }

    public function button(array $attrs, string $content, $block): string
    {
        $id = (int) ($block->context['postId'] ?? get_the_ID());
        return sprintf('<button type="button" class="wish" data-wish="%d" aria-pressed="false" aria-label="%s"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.4-9.2-9A5 5 0 0 1 12 5.8 5 5 0 0 1 21.2 11C19 15.6 12 20 12 20z" fill="none" stroke="currentColor" stroke-width="1.6"/></svg></button>', $id, esc_attr(sprintf(__('Save %s to wishlist', 'lereng'), get_the_title($id))));
    }

    public static function ids(int $userId): array
    {
        return array_values(array_map('intval', (array) get_user_meta($userId, self::META, true) ?: []));
    }

    public function get(): WP_REST_Response
    {
        return new WP_REST_Response(['ids' => is_user_logged_in() ? self::ids(get_current_user_id()) : []]);
    }

    /** Replaces the list, or merges when the browser sends what a guest saved. */
    public function set(WP_REST_Request $r): WP_REST_Response
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $r['ids']), static fn($id) => get_post_type($id) === 'product')));
        if ($r['merge']) {
            $ids = array_values(array_unique([...self::ids(get_current_user_id()), ...$ids]));
        }
        update_user_meta(get_current_user_id(), self::META, array_slice($ids, 0, 100));
        return new WP_REST_Response(['ids' => self::ids(get_current_user_id())]);
    }

    public function page(): void
    {
        $ids = self::ids(get_current_user_id());
        if (!$ids) {
            echo '<p>' . esc_html__('Nothing saved yet. Tap the heart on any coffee to keep it here.', 'lereng') . '</p>';
            return;
        }
        echo '<ul class="wish-list">';
        foreach ($ids as $id) {
            $p = wc_get_product($id);
            if (!$p) {
                continue;
            }
            printf('<li><a href="%s">%s<span>%s</span></a><span>%s</span><button type="button" class="wish is-on" data-wish="%d" aria-pressed="true">%s</button></li>', esc_url($p->get_permalink()), $p->get_image('woocommerce_gallery_thumbnail'), esc_html($p->get_name()), wp_kses_post($p->get_price_html()), $id, esc_html__('Remove', 'lereng'));
        }
        echo '</ul>';
    }
}
