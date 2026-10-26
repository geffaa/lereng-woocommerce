<?php

namespace Lereng\Admin;

use DateTimeImmutable;
use DateTimeZone;
use Lereng\Catalog\RoastSchedule;
use Lereng\Subscriptions\Repository;

/**
 * "Roastery today" on the WordPress dashboard: what the team needs at 8 a.m. before opening
 * WooCommerce. Each number links to the screen where it gets dealt with.
 */
final class Dashboard
{
    public function register(): void
    {
        add_action('wp_dashboard_setup', static function (): void {
            if (current_user_can('edit_shop_orders')) {
                wp_add_dashboard_widget('lereng_today', __('Roastery today', 'lereng'), [self::class, 'render']);
            }
        });
    }

    /** @return array<string, mixed> */
    public static function numbers(): array
    {
        global $wpdb;
        $orders = $wpdb->prefix . 'wc_orders';
        $now = new DateTimeImmutable('now', new DateTimeZone(RoastSchedule::TZ));
        $roast = RoastSchedule::nextRoast($now)->format('Y-m-d');
        $count = static fn(string $status) => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $orders WHERE type = 'shop_order' AND status = %s", $status));
        $forRoast = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $orders o JOIN {$wpdb->prefix}wc_orders_meta m ON m.order_id = o.id AND m.meta_key = '_lereng_roast_date' WHERE o.status = 'wc-processing' AND m.meta_value = %s",
            $roast
        ));
        $paid = "'wc-processing','wc-roasting','wc-shipped','wc-completed'";
        $sales = static fn(int $from, int $to) => (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_amount), 0) FROM $orders WHERE type = 'shop_order' AND status IN ($paid) AND date_created_gmt >= %s AND date_created_gmt < %s",
            gmdate('Y-m-d H:i:s', time() - $from * DAY_IN_SECONDS),
            gmdate('Y-m-d H:i:s', time() - $to * DAY_IN_SECONDS)
        ));
        $subs = Repository::stats();
        return [
            'roast_date' => $roast,
            'for_roast' => $forRoast,
            'roasting' => $count('wc-roasting'),
            'pending' => $count('wc-pending'),
            'sales_30' => $sales(30, 0),
            'sales_prev' => $sales(60, 30),
            'subs_active' => $subs['counts']['active'] ?? 0,
            'mrr' => $subs['mrr'],
            'applications' => count(get_posts(['post_type' => 'lereng_wholesale', 'post_status' => 'private', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_status', 'meta_value' => 'pending'])),
            'restock' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lereng_restock WHERE notified_at IS NULL"),
        ];
    }

    public static function render(): void
    {
        $n = self::numbers();
        $change = $n['sales_prev'] > 0 ? ($n['sales_30'] - $n['sales_prev']) / $n['sales_prev'] * 100 : 0;
        $rows = [
            [sprintf(__('To roast on %s', 'lereng'), wp_date('l j M', strtotime($n['roast_date']))), sprintf(_n('%d order', '%d orders', $n['for_roast'], 'lereng'), $n['for_roast']), admin_url('admin.php?page=lereng-roast-list')],
            [__('Roasted, waiting to ship', 'lereng'), sprintf(_n('%d order', '%d orders', $n['roasting'], 'lereng'), $n['roasting']), admin_url('admin.php?page=wc-orders&status=wc-roasting')],
            [__('Waiting for payment', 'lereng'), sprintf(_n('%d order', '%d orders', $n['pending'], 'lereng'), $n['pending']), admin_url('admin.php?page=wc-orders&status=wc-pending')],
            [__('Sales, last 30 days', 'lereng'), wp_strip_all_tags(wc_price($n['sales_30'])) . sprintf(' (%s%.0f%%)', $change >= 0 ? '+' : '', $change), admin_url('admin.php?page=wc-admin&period=last_month')],
            [__('Active subscriptions', 'lereng'), sprintf(__('%1$d · %2$s a month', 'lereng'), $n['subs_active'], wp_strip_all_tags(wc_price($n['mrr']))), admin_url('admin.php?page=lereng-subscriptions')],
            [__('Wholesale applications', 'lereng'), (string) $n['applications'], admin_url('admin.php?page=lereng-wholesale')],
            [__('Waiting for a restock', 'lereng'), sprintf(_n('%d customer', '%d customers', $n['restock'], 'lereng'), $n['restock']), admin_url('admin.php?page=lereng-restock')],
        ];
        echo '<table class="widefat striped lereng-today"><tbody>';
        foreach ($rows as [$label, $value, $url]) {
            printf('<tr><th scope="row">%s</th><td style="text-align:right"><a href="%s"><b>%s</b></a></td></tr>', esc_html($label), esc_url($url), esc_html($value));
        }
        echo '</tbody></table>';
    }
}
