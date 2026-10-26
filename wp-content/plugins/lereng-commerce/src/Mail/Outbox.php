<?php

namespace Lereng\Mail;

/**
 * Captures outgoing email instead of sending it, while the store runs as a demo or on a dev
 * machine (no mail server). Messages are kept in a private post type, listed under
 * WooCommerce → Outbox, and, in demo mode, readable at /demo-inbox/ so visitors can see the
 * order, payment and subscription emails the store sends. On a live store, turn it off and
 * WordPress mail goes out as usual.
 */
final class Outbox
{
    public const POST_TYPE = 'lereng_mail';
    public const KEEP = 300;

    public static function enabled(): bool
    {
        return get_option('lereng_outbox', 'yes') === 'yes';
    }

    public function register(): void
    {
        add_action('init', [$this, 'postType']);
        if (!self::enabled()) {
            return;
        }
        add_filter('pre_wp_mail', [$this, 'capture'], 10, 2);
        add_action('admin_menu', [$this, 'menu']);
        add_action('init', static fn() => add_rewrite_rule('^demo-inbox(?:/(\d+))?/?$', 'index.php?lereng_inbox=1&lereng_mail=$matches[1]', 'top'));
        add_filter('query_vars', static fn(array $v): array => [...$v, 'lereng_inbox', 'lereng_mail']);
        add_action('template_redirect', [$this, 'inbox']);
    }

    public function postType(): void
    {
        register_post_type(self::POST_TYPE, ['public' => false, 'show_ui' => false, 'label' => 'Outbox', 'supports' => ['title', 'editor']]);
    }

    /** @param null|bool $short */
    public function capture($short, array $atts)
    {
        $to = is_array($atts['to']) ? implode(', ', $atts['to']) : (string) $atts['to'];
        $headers = is_array($atts['headers']) ? implode("\n", $atts['headers']) : (string) $atts['headers'];
        $id = wp_insert_post(['post_type' => self::POST_TYPE, 'post_status' => 'private', 'post_title' => wp_strip_all_tags((string) $atts['subject']), 'post_content' => (string) $atts['message']], true);
        if (!is_wp_error($id)) {
            update_post_meta($id, '_to', $to);
            update_post_meta($id, '_html', str_contains(strtolower($headers), 'text/html') || str_contains((string) $atts['message'], '<html') ? 'yes' : 'no');
            $this->prune();
        }
        return true; // handled: WordPress doesn't try to send it
    }

    private function prune(): void
    {
        $old = get_posts(['post_type' => self::POST_TYPE, 'post_status' => 'private', 'fields' => 'ids', 'offset' => self::KEEP, 'posts_per_page' => 50, 'orderby' => 'ID', 'order' => 'DESC']);
        foreach ($old as $id) {
            wp_delete_post($id, true);
        }
    }

    public function menu(): void
    {
        add_submenu_page('woocommerce', __('Outbox', 'lereng'), __('Outbox', 'lereng'), 'manage_woocommerce', 'lereng-outbox', fn() => $this->render(true));
    }

    public function inbox(): void
    {
        if (!get_query_var('lereng_inbox')) {
            return;
        }
        if (get_option('lereng_demo_inbox', 'yes') !== 'yes') {
            return;
        }
        status_header(200);
        nocache_headers();
        $id = (int) get_query_var('lereng_mail');
        if ($id) {
            $post = get_post($id);
            if ($post && $post->post_type === self::POST_TYPE) {
                header('Content-Type: text/html; charset=utf-8');
                echo get_post_meta($id, '_html', true) === 'yes' ? $post->post_content : '<pre style="font:14px/1.6 monospace;padding:24px;white-space:pre-wrap">' . esc_html($post->post_content) . '</pre>'; // phpcs:ignore WordPress.Security.EscapeOutput -- the store's own email HTML
                exit;
            }
        }
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Demo inbox · Lereng</title><body style="margin:0;font:15px/1.5 system-ui,sans-serif;background:#F6F4EF;color:#141412">';
        $this->render(false);
        exit;
    }

    private function render(bool $admin): void
    {
        $mails = get_posts(['post_type' => self::POST_TYPE, 'post_status' => 'private', 'posts_per_page' => 60, 'orderby' => 'ID', 'order' => 'DESC']);
        $base = $admin ? admin_url('admin.php?page=lereng-outbox') : home_url('/demo-inbox/');
        echo $admin ? '<div class="wrap"><h1>' . esc_html__('Outbox', 'lereng') . '</h1>' : '<main style="max-width:980px;margin:0 auto;padding:32px 16px"><h1 style="margin:0 0 4px">Demo inbox</h1><p style="color:#6B675F;margin:0 0 20px">Every email the store sent, captured instead of delivered. Newest first.</p>';
        echo '<p>' . esc_html(sprintf(_n('%d message kept.', '%d messages kept.', count($mails), 'lereng'), count($mails))) . '</p><table class="widefat striped" style="width:100%;border-collapse:collapse;background:#fff"><thead><tr><th style="text-align:left;padding:8px">' . esc_html__('Sent', 'lereng') . '</th><th style="text-align:left;padding:8px">' . esc_html__('To', 'lereng') . '</th><th style="text-align:left;padding:8px">' . esc_html__('Subject', 'lereng') . '</th></tr></thead><tbody>';
        foreach ($mails as $m) {
            $view = home_url('/demo-inbox/' . $m->ID . '/');
            printf('<tr><td style="padding:8px;border-top:1px solid #eee;white-space:nowrap">%s</td><td style="padding:8px;border-top:1px solid #eee">%s</td><td style="padding:8px;border-top:1px solid #eee"><a href="%s" target="_blank" rel="noopener">%s</a></td></tr>',
                esc_html(get_the_date('j M H:i', $m)), esc_html(self::mask((string) get_post_meta($m->ID, '_to', true), $admin)), esc_url($view), esc_html($m->post_title));
        }
        echo '</tbody></table>' . ($admin ? '</div>' : '</main>');
        unset($base);
    }

    /** The public demo inbox hides most of each address. */
    private static function mask(string $to, bool $admin): string
    {
        return $admin ? $to : preg_replace_callback('/([^@\s,]{1,2})[^@\s,]*@/', static fn($m) => $m[1] . '•••@', $to);
    }
}
