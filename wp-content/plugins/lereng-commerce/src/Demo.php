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
        // Playground shows PHP notices on the page; visitors of the demo shouldn't see them.
        ini_set('display_errors', '0');
        add_action('shutdown', [$this, 'runQueue'], 100);
        add_action('wp_footer', [$this, 'badge']);
        self::limitDemoUser();
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
        // On Playground anyone may sign in as admin (the site lives in their browser); on a public
        // host the blueprint-free install defines LERENG_DEMO_LOGIN for a limited staff account.
        $login = defined('LERENG_DEMO_LOGIN') ? LERENG_DEMO_LOGIN : 'admin / password';
        printf(
            '<div class="demo-badge">%s · <a href="%s">%s</a> · <a href="%s">%s</a></div>',
            esc_html__('Demo store · payments are simulated', 'lereng'),
            esc_url(home_url('/demo-inbox/')),
            esc_html__('Demo inbox', 'lereng'),
            esc_url(admin_url('index.php')),
            esc_html(sprintf(__('Staff login (%s)', 'lereng'), $login))
        );
    }

    /**
     * The public staff account (user "demo", Shop Manager) can look at everything but not delete
     * data, manage users or change its own password, so one visitor can't spoil the demo for the next.
     */
    public static function limitDemoUser(): void
    {
        add_filter('map_meta_cap', static function (array $caps, string $cap, int $userId): array {
            $user = get_userdata($userId);
            if (!$user || $user->user_login !== 'demo') {
                return $caps;
            }
            if (str_starts_with($cap, 'delete_') || in_array($cap, ['edit_users', 'create_users', 'promote_users', 'remove_users', 'list_users', 'edit_user', 'manage_options', 'install_plugins', 'activate_plugins', 'edit_theme_options'], true)) {
                return ['do_not_allow'];
            }
            return $caps;
        }, 10, 3);
        add_filter('show_password_fields', static fn($show, $user = null) => $user && $user->user_login === 'demo' ? false : $show, 10, 2);
        add_filter('allow_password_reset', static fn($allow, $userId) => get_userdata($userId)?->user_login === 'demo' ? false : $allow, 10, 2);
        add_action('user_profile_update_errors', static function ($errors, $update, $user): void {
            if (!empty($user->ID) && get_userdata($user->ID)?->user_login === 'demo' && !empty($_POST['pass1'])) {
                $errors->add('demo', __('The demo account password stays the same for everyone.', 'lereng'));
            }
        }, 10, 3);
    }
}
