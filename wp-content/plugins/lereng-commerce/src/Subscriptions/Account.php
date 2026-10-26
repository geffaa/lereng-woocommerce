<?php

namespace Lereng\Subscriptions;

/**
 * My account → Subscriptions: the list, and a page per subscription where the customer can
 * skip, pause, resume, change coffee, size, grind, quantity or frequency, cancel, or restart.
 * Plain forms with nonces; each change is written to the timeline and confirmed by email.
 */
final class Account
{
    public const REASONS = [
        'too_much' => 'I have too much coffee',
        'price' => 'It costs too much',
        'taste' => "The coffee isn't for me",
        'moving' => "I'm moving or travelling",
        'other' => 'Something else',
    ];

    public function register(): void
    {
        add_action('init', static function (): void {
            add_rewrite_endpoint('subscriptions', EP_ROOT | EP_PAGES);
            add_rewrite_endpoint('view-subscription', EP_ROOT | EP_PAGES);
        });
        add_filter('woocommerce_get_query_vars', static fn(array $v): array => $v + ['subscriptions' => 'subscriptions', 'view-subscription' => 'view-subscription']);
        add_filter('woocommerce_account_menu_items', [$this, 'menu']);
        add_action('woocommerce_account_subscriptions_endpoint', [$this, 'list']);
        add_action('woocommerce_account_view-subscription_endpoint', [$this, 'view']);
        add_filter('woocommerce_endpoint_view-subscription_title', static fn() => __('Subscription', 'lereng'));
        add_filter('woocommerce_endpoint_subscriptions_title', static fn() => __('Subscriptions', 'lereng'));
        add_action('template_redirect', [$this, 'handle']);
    }

    public function menu(array $items): array
    {
        $out = [];
        foreach ($items as $k => $v) {
            $out[$k] = $v;
            if ($k === 'orders') {
                $out['subscriptions'] = __('Subscriptions', 'lereng');
            }
        }
        return $out;
    }

    private static function label(array $sub): string
    {
        $p = wc_get_product($sub['variation_id'] ?: $sub['product_id']);
        return $p ? wp_strip_all_tags($p->get_name()) : '#' . $sub['product_id'];
    }

    private static function status(array $sub): string
    {
        return match ($sub['status']) {
            'active' => __('Active', 'lereng'),
            'paused' => sprintf(__('Paused until %s', 'lereng'), wp_date('j M', strtotime($sub['paused_until']))),
            'cancelled' => __('Cancelled', 'lereng'),
            'on-hold' => __('On hold', 'lereng'),
            default => ucfirst($sub['status']),
        };
    }

    public function list(): void
    {
        $subs = Repository::forCustomer(get_current_user_id());
        if (!$subs) {
            echo '<p>' . esc_html__('No subscriptions yet.', 'lereng') . ' <a href="' . esc_url(home_url('/subscriptions/')) . '">' . esc_html__('How subscriptions work', 'lereng') . '</a></p>';
            return;
        }
        echo '<table class="shop_table subs-table"><thead><tr><th>' . esc_html__('Coffee', 'lereng') . '</th><th>' . esc_html__('Every', 'lereng') . '</th><th>' . esc_html__('Next delivery', 'lereng') . '</th><th>' . esc_html__('Status', 'lereng') . '</th><th></th></tr></thead><tbody>';
        foreach ($subs as $s) {
            printf('<tr><td>%s × %d</td><td>%s</td><td>%s</td><td>%s</td><td><a class="button" href="%s">%s</a></td></tr>', esc_html(self::label($s)), $s['quantity'], esc_html(Subscriptions::intervalLabel($s['interval_weeks'])), $s['status'] === 'active' ? esc_html(wp_date('D j M', strtotime($s['next_date']))) : '—', esc_html(self::status($s)), esc_url(wc_get_account_endpoint_url('view-subscription') . $s['id'] . '/'), esc_html__('Manage', 'lereng'));
        }
        echo '</tbody></table>';
    }

    private static function owned(int $id): ?array
    {
        $sub = Repository::find($id);
        return $sub && $sub['customer_id'] === get_current_user_id() ? $sub : null;
    }

    /** Variations a subscription can switch to: other subscribable coffees, same grind/size options. */
    private static function options(): array
    {
        $out = [];
        $ids = wc_get_products(['status' => 'publish', 'type' => 'variable', 'category' => ['single-origin', 'blends'], 'limit' => -1, 'return' => 'ids', 'orderby' => 'menu_order', 'order' => 'ASC']);
        foreach ($ids as $id) {
            if (get_post_meta($id, '_lereng_subscribable', true) === 'yes') {
                $out[$id] = get_the_title($id);
            }
        }
        $choice = get_page_by_path('roasters-choice-subscription', OBJECT, 'product');
        if ($choice) {
            $out = [$choice->ID => $choice->post_title] + $out;
        }
        return $out;
    }

    public function view($id): void
    {
        $sub = self::owned((int) $id);
        if (!$sub) {
            echo '<p>' . esc_html__('Subscription not found.', 'lereng') . '</p>';
            return;
        }
        $product = wc_get_product($sub['variation_id'] ?: $sub['product_id']);
        $size = $product ? $product->get_attribute('pa_size') : '';
        $grind = $product ? $product->get_attribute('pa_grind') : '';
        $form = static function (string $action, string $inner, string $class = '') use ($sub): string {
            return sprintf('<form method="post" class="sub-form %s">%s<input type="hidden" name="lereng_sub_action" value="%s"><input type="hidden" name="sub_id" value="%d">%s</form>', esc_attr($class), wp_nonce_field('lereng_sub_' . $sub['id'], '_lereng_nonce', true, false), esc_attr($action), $sub['id'], $inner);
        };
        $monthly = Schedule::monthly($sub['price'], $sub['quantity'], $sub['interval_weeks']);
        echo '<div class="sub-view" data-sub="' . (int) $sub['id'] . '">';
        printf('<header class="sub-view__head"><p class="kicker">%s</p><h2>%s × %d</h2><p>%s · %s · %s</p></header>',
            esc_html(sprintf(__('Subscription #%d · %s', 'lereng'), $sub['id'], self::status($sub))),
            esc_html(self::label($sub)), $sub['quantity'],
            esc_html(Subscriptions::intervalLabel($sub['interval_weeks'])),
            wp_kses_post(sprintf(__('%s a delivery', 'lereng'), wc_price($sub['price'] * $sub['quantity'] + $sub['shipping_cost']))),
            wp_kses_post(sprintf(__('about %s a month', 'lereng'), wc_price($monthly))));

        if ($sub['status'] === 'active') {
            printf('<p class="sub-view__next">%s <b>%s</b></p>', esc_html__('Next delivery, roasted on', 'lereng'), esc_html(wp_date('l j F', strtotime($sub['next_date']))));
            echo '<div class="sub-actions">';
            echo $form('skip', '<button type="submit">' . esc_html(sprintf(__('Skip it (next would be %s)', 'lereng'), wp_date('j M', strtotime(Schedule::skip($sub['next_date'], $sub['interval_weeks']))))) . '</button>'); // phpcs:ignore WordPress.Security.EscapeOutput
            echo $form('renew_now', '<button type="submit">' . esc_html__('Send it on the next roast day', 'lereng') . '</button>'); // phpcs:ignore
            echo $form('pause', '<label>' . esc_html__('Pause until', 'lereng') . ' <input type="date" name="until" required min="' . esc_attr(wp_date('Y-m-d', strtotime('+1 day'))) . '" max="' . esc_attr(wp_date('Y-m-d', strtotime('+' . Schedule::MAX_PAUSE_DAYS . ' days'))) . '"></label><button type="submit">' . esc_html__('Pause', 'lereng') . '</button>', 'is-inline'); // phpcs:ignore
            echo '</div>';
        } elseif ($sub['status'] === 'paused') {
            echo $form('resume', '<button type="submit">' . esc_html__('Resume now', 'lereng') . '</button>'); // phpcs:ignore
        } elseif ($sub['status'] === 'cancelled') {
            echo $form('restart', '<button type="submit">' . esc_html__('Restart this subscription', 'lereng') . '</button>'); // phpcs:ignore
        }

        if ($sub['status'] !== 'cancelled') {
            $opts = '';
            foreach (self::options() as $pid => $title) {
                $opts .= sprintf('<option value="%d" %s>%s</option>', $pid, selected($pid, $sub['product_id'], false), esc_html($title));
            }
            $sel = static fn(array $values, string $current): string => implode('', array_map(static fn($v) => sprintf('<option %s>%s</option>', selected($v, $current, false), esc_html($v)), $values));
            echo '<h3>' . esc_html__('Change', 'lereng') . '</h3>';
            echo $form('change', // phpcs:ignore WordPress.Security.EscapeOutput
                '<label>' . esc_html__('Coffee', 'lereng') . '<select name="product_id">' . $opts . '</select></label>'
                . '<label>' . esc_html__('Size', 'lereng') . '<select name="size">' . $sel(['200g', '500g', '1kg'], $size) . '</select></label>'
                . '<label>' . esc_html__('Grind', 'lereng') . '<select name="grind">' . $sel(['Whole bean', 'Espresso', 'Pour over', 'French press', 'Moka pot'], $grind) . '</select></label>'
                . '<label>' . esc_html__('Bags', 'lereng') . '<input type="number" name="quantity" min="1" max="10" value="' . (int) $sub['quantity'] . '"></label>'
                . '<label>' . esc_html__('Every', 'lereng') . '<select name="interval">' . implode('', array_map(static fn($w) => sprintf('<option value="%d" %s>%s</option>', $w, selected($w, $sub['interval_weeks'], false), esc_html(Subscriptions::intervalLabel($w))), Schedule::INTERVALS)) . '</select></label>'
                . '<button type="submit">' . esc_html__('Save changes', 'lereng') . '</button>', 'sub-change');
            $reasons = implode('', array_map(static fn($k, $v) => sprintf('<option value="%s">%s</option>', esc_attr($k), esc_html__($v, 'lereng')), array_keys(self::REASONS), self::REASONS));
            echo '<details class="sub-cancel"><summary>' . esc_html__('Cancel subscription', 'lereng') . '</summary>' . $form('cancel', '<label>' . esc_html__('Why are you cancelling?', 'lereng') . '<select name="reason">' . $reasons . '</select></label><button type="submit" class="is-danger">' . esc_html__('Cancel subscription', 'lereng') . '</button>') . '</details>'; // phpcs:ignore
        }

        echo '<h3>' . esc_html__('History', 'lereng') . '</h3><ol class="sub-timeline">';
        foreach (Repository::events($sub['id']) as $e) {
            $order = $e['order_id'] ? wc_get_order((int) $e['order_id']) : null;
            printf('<li><time>%s</time><b>%s</b> %s %s</li>', esc_html(wp_date('j M Y', strtotime($e['created_at'] . ' UTC'))), esc_html(self::eventLabel($e['type'])), esc_html((string) $e['detail']),
                $order ? '<a href="' . esc_url($order->get_view_order_url()) . '">#' . esc_html($order->get_order_number()) . '</a>' . ($order->has_status('pending') ? ' · <a href="' . esc_url(\Lereng\Payments\Payments::payUrl($order)) . '">' . esc_html__('pay', 'lereng') . '</a>' : '') : '');
        }
        echo '</ol></div>';
    }

    public static function eventLabel(string $type): string
    {
        $labels = [
            'created' => __('Started', 'lereng'), 'renewal_created' => __('Renewal ordered', 'lereng'), 'renewal_paid' => __('Renewal paid', 'lereng'),
            'renewal_unpaid' => __('Renewal skipped (unpaid)', 'lereng'), 'skipped' => __('Skipped', 'lereng'), 'paused' => __('Paused', 'lereng'),
            'resumed' => __('Resumed', 'lereng'), 'changed' => __('Changed', 'lereng'), 'cancelled' => __('Cancelled', 'lereng'), 'restarted' => __('Restarted', 'lereng'), 'held' => __('On hold', 'lereng'),
        ];
        return $labels[$type] ?? $type;
    }

    public function handle(): void
    {
        if (empty($_POST['lereng_sub_action']) || !is_user_logged_in()) {
            return;
        }
        $id = (int) ($_POST['sub_id'] ?? 0);
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_lereng_nonce'] ?? '')), 'lereng_sub_' . $id) || !($sub = self::owned($id))) {
            wc_add_notice(__('That link has expired. Please try again.', 'lereng'), 'error');
            return;
        }
        $msg = Actions::apply($sub, sanitize_key($_POST['lereng_sub_action']), wp_unslash($_POST), 'customer');
        if (is_wp_error($msg)) {
            wc_add_notice($msg->get_error_message(), 'error');
        } else {
            wc_add_notice($msg, 'success');
            Emails::changed(Repository::find($id), $msg);
        }
        wp_safe_redirect(wc_get_account_endpoint_url('view-subscription') . $id . '/');
        exit;
    }
}
