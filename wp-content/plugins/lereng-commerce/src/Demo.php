<?php

namespace Lereng;

/**
 * Only for the live demo on WordPress Playground (LERENG_DEMO is defined by the blueprint).
 *
 * Playground runs PHP in the visitor's browser, where WordPress can't make loopback requests, so
 * Action Scheduler never starts its own queue runner. Payment expiry, subscription renewals,
 * scheduled gift cards and review emails would never happen. Here the queue runs at the end of a
 * page view instead, at most every 30 seconds. A small badge says what the site is.
 */
final class Demo
{
    public function register(): void
    {
        if (!defined('LERENG_DEMO') || !LERENG_DEMO) {
            return;
        }
        add_action('shutdown', [$this, 'runQueue'], 100);
        add_action('wp_footer', [$this, 'badge']);
    }

    public function runQueue(): void
    {
        if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || get_transient('lereng_demo_queue') || !class_exists(\ActionScheduler::class)) {
            return;
        }
        set_transient('lereng_demo_queue', 1, 30);
        \ActionScheduler::runner()->run('Lereng demo');
    }

    public function badge(): void
    {
        printf(
            '<div class="demo-badge">%s · <a href="%s">%s</a> · <a href="%s">%s</a></div>',
            esc_html__('Live demo · WordPress running in your browser', 'lereng'),
            esc_url(home_url('/demo-inbox/')),
            esc_html__('Demo inbox', 'lereng'),
            esc_url(admin_url('admin.php?page=wc-admin')),
            esc_html__('Admin (admin / password)', 'lereng')
        );
    }
}
