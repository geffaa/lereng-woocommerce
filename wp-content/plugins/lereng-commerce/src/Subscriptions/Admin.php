<?php

namespace Lereng\Subscriptions;

/** WooCommerce → Subscriptions: numbers, the list, and a page per subscription with its history. */
final class Admin
{
    public function register(): void
    {
        add_action('admin_menu', function (): void {
            add_submenu_page('woocommerce', __('Subscriptions', 'lereng'), __('Subscriptions', 'lereng'), 'manage_woocommerce', 'lereng-subscriptions', [$this, 'page']);
        });
        add_action('admin_post_lereng_sub_admin', [$this, 'handle']);
        add_action('admin_notices', static function (): void {
            if ($msg = get_transient('lereng_admin_notice')) {
                delete_transient('lereng_admin_notice');
                printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html(wp_strip_all_tags($msg)));
            }
        });
    }

    public function page(): void
    {
        $id = (int) ($_GET['sub'] ?? 0); // phpcs:ignore WordPress.Security.NonceVerification
        echo '<div class="wrap">';
        $id ? $this->detail($id) : $this->index();
        echo '</div>';
    }

    private function index(): void
    {
        $stats = Repository::stats();
        $status = sanitize_key($_GET['status'] ?? ''); // phpcs:ignore WordPress.Security.NonceVerification
        echo '<h1>' . esc_html__('Subscriptions', 'lereng') . '</h1>';
        echo '<div style="display:flex;gap:12px;margin:12px 0 18px;flex-wrap:wrap">';
        $card = static fn($label, $value) => printf('<div style="background:#fff;border:1px solid #dcdcde;padding:12px 16px;min-width:140px"><div style="color:#646970;font-size:12px">%s</div><div style="font-size:22px;font-weight:600">%s</div></div>', esc_html($label), wp_kses_post($value));
        $card(__('Active', 'lereng'), (string) ($stats['counts']['active'] ?? 0));
        $card(__('Paused', 'lereng'), (string) ($stats['counts']['paused'] ?? 0));
        $card(__('Cancelled', 'lereng'), (string) ($stats['counts']['cancelled'] ?? 0));
        $card(__('Monthly recurring revenue', 'lereng'), wc_price($stats['mrr']));
        echo '</div><ul class="subsubsub">';
        foreach (['' => __('All', 'lereng'), 'active' => __('Active', 'lereng'), 'paused' => __('Paused', 'lereng'), 'cancelled' => __('Cancelled', 'lereng')] as $k => $label) {
            printf('<li><a href="%s" %s>%s</a> | </li>', esc_url(admin_url('admin.php?page=lereng-subscriptions&status=' . $k)), $k === $status ? 'class="current"' : '', esc_html($label));
        }
        echo '</ul><table class="widefat striped"><thead><tr><th>#</th><th>' . esc_html__('Customer', 'lereng') . '</th><th>' . esc_html__('Coffee', 'lereng') . '</th><th>' . esc_html__('Every', 'lereng') . '</th><th>' . esc_html__('Next', 'lereng') . '</th><th>' . esc_html__('Renewals', 'lereng') . '</th><th>' . esc_html__('Status', 'lereng') . '</th><th>' . esc_html__('Per month', 'lereng') . '</th></tr></thead><tbody>';
        foreach (Repository::all($status ? ['status' => $status] : []) as $s) {
            $user = get_userdata($s['customer_id']);
            $p = wc_get_product($s['variation_id'] ?: $s['product_id']);
            printf('<tr><td><a href="%s">#%d</a></td><td>%s</td><td>%s × %d</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td><td>%s</td></tr>', esc_url(admin_url('admin.php?page=lereng-subscriptions&sub=' . $s['id'])), $s['id'], esc_html($user ? $user->display_name : '—'), esc_html($p ? wp_strip_all_tags($p->get_name()) : '—'), $s['quantity'], esc_html(Subscriptions::intervalLabel($s['interval_weeks'])), $s['status'] === 'active' ? esc_html(wp_date('j M', strtotime($s['next_date']))) : '—', $s['renewals'], esc_html($s['status']), wp_kses_post(wc_price(Schedule::monthly($s['price'], $s['quantity'], $s['interval_weeks']))));
        }
        echo '</tbody></table>';
    }

    private function detail(int $id): void
    {
        $s = Repository::find($id);
        if (!$s) {
            echo '<p>Not found.</p>';
            return;
        }
        $user = get_userdata($s['customer_id']);
        $p = wc_get_product($s['variation_id'] ?: $s['product_id']);
        printf('<h1>%s #%d <a class="page-title-action" href="%s">%s</a></h1>', esc_html__('Subscription', 'lereng'), $id, esc_url(admin_url('admin.php?page=lereng-subscriptions')), esc_html__('All subscriptions', 'lereng'));
        printf('<p><b>%s</b> × %d, %s, %s · %s · %s</p>', esc_html($p ? wp_strip_all_tags($p->get_name()) : '—'), $s['quantity'], esc_html(Subscriptions::intervalLabel($s['interval_weeks'])), wp_kses_post(wc_price($s['price'])), esc_html($user ? $user->display_name . ' <' . $user->user_email . '>' : '—'), esc_html($s['status']));
        foreach (['renew_now' => __('Create the next renewal now', 'lereng'), 'skip' => __('Skip next delivery', 'lereng'), 'resume' => __('Resume', 'lereng'), 'cancel' => __('Cancel', 'lereng')] as $action => $label) {
            printf('<form method="post" action="%s" style="display:inline-block;margin-right:6px"><input type="hidden" name="action" value="lereng_sub_admin"><input type="hidden" name="sub" value="%d"><input type="hidden" name="do" value="%s">%s<button class="button">%s</button></form>', esc_url(admin_url('admin-post.php')), $id, esc_attr($action), wp_nonce_field('lereng_sub_admin_' . $id, '_wpnonce', true, false), esc_html($label));
        }
        echo '<h2>' . esc_html__('History', 'lereng') . '</h2><table class="widefat striped"><tbody>';
        foreach (Repository::events($id) as $e) {
            printf('<tr><td style="width:160px">%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html(wp_date('j M Y H:i', strtotime($e['created_at'] . ' UTC'))), esc_html(Account::eventLabel($e['type'])), esc_html((string) $e['detail']), $e['order_id'] ? '<a href="' . esc_url(admin_url('admin.php?page=wc-orders&action=edit&id=' . (int) $e['order_id'])) . '">#' . (int) $e['order_id'] . '</a>' : '');
        }
        echo '</tbody></table>';
    }

    public function handle(): void
    {
        $id = (int) ($_POST['sub'] ?? 0);
        check_admin_referer('lereng_sub_admin_' . $id);
        if (!current_user_can('manage_woocommerce') || !($s = Repository::find($id))) {
            wp_die('Not allowed');
        }
        $msg = Actions::apply($s, sanitize_key($_POST['do'] ?? ''), ['reason' => 'other'], 'staff');
        set_transient('lereng_admin_notice', is_wp_error($msg) ? $msg->get_error_message() : $msg, 30);
        wp_safe_redirect(admin_url('admin.php?page=lereng-subscriptions&sub=' . $id));
        exit;
    }
}
