<?php

namespace Lereng\Account;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Footer newsletter sign-up. Addresses go to a list in WooCommerce → Newsletter (a real store
 * would sync them to Mailchimp or Klaviyo here), with a welcome email and a one-time code.
 */
final class Newsletter
{
    public const OPTION = 'lereng_newsletter';

    public function register(): void
    {
        add_action('rest_api_init', fn() => register_rest_route('lereng/v1', '/newsletter', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => [$this, 'subscribe']]));
        add_action('admin_menu', fn() => add_submenu_page('woocommerce', __('Newsletter', 'lereng'), __('Newsletter', 'lereng'), 'manage_woocommerce', 'lereng-newsletter', [$this, 'admin']));
    }

    public function subscribe(WP_REST_Request $r): WP_REST_Response
    {
        $email = sanitize_email((string) $r['email']);
        if (!is_email($email)) {
            return new WP_REST_Response(['message' => __('Please enter a valid email.', 'lereng')], 422);
        }
        $list = (array) get_option(self::OPTION, []);
        if (isset($list[$email])) {
            return new WP_REST_Response(['message' => __("You're already on the list. Next roast email soon.", 'lereng')], 200);
        }
        $list[$email] = time();
        update_option(self::OPTION, $list, false);
        $body = '<p>' . esc_html__("Thanks for signing up. We write when a new roast or micro-lot lands, about twice a month.", 'lereng') . '</p><p>' . esc_html__('Your first order: 10% off with', 'lereng') . ' <b>WELCOME10</b></p>';
        WC()->mailer()->send($email, __('Welcome to Lereng', 'lereng'), WC()->mailer()->wrap_message(__('Welcome to Lereng', 'lereng'), $body));
        return new WP_REST_Response(['message' => __("You're in. Check your inbox for 10% off your first order.", 'lereng')], 201);
    }

    public function admin(): void
    {
        $list = (array) get_option(self::OPTION, []);
        arsort($list);
        echo '<div class="wrap"><h1>' . esc_html__('Newsletter', 'lereng') . '</h1><p>' . esc_html(sprintf(_n('%d subscriber', '%d subscribers', count($list), 'lereng'), count($list))) . '</p><table class="widefat striped"><tbody>';
        foreach ($list as $email => $t) {
            printf('<tr><td>%s</td><td>%s</td></tr>', esc_html($email), esc_html(wp_date('j M Y', $t)));
        }
        echo '</tbody></table></div>';
    }
}
