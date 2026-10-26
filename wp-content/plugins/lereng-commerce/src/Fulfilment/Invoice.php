<?php

namespace Lereng\Fulfilment;

use WC_Order;

/**
 * Printable invoice at /invoice/<order key>/: seller and buyer, items, and the PPN breakdown
 * (tax base DPP and 11% PPN), with the buyer's NPWP when they asked for a tax invoice. Linked
 * from the thank-you page, My account and the admin order screen.
 */
final class Invoice
{
    public const SELLER = ['name' => 'PT Lereng Kopi Nusantara', 'address' => 'Jl. Raya Kerobokan No. 88, Badung, Bali 80361', 'npwp' => '01.234.567.8-901.000 (sample)'];

    public function register(): void
    {
        add_action('init', static fn() => add_rewrite_rule('^invoice/(wc_order_[A-Za-z0-9]+)/?$', 'index.php?lereng_invoice=$matches[1]', 'top'));
        add_filter('query_vars', static fn(array $v): array => [...$v, 'lereng_invoice']);
        add_action('template_redirect', [$this, 'render']);
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'accountAction'], 10, 2);
        add_action('woocommerce_order_details_after_order_table', [$this, 'link']);
        add_filter('woocommerce_admin_order_actions', [$this, 'adminAction'], 10, 2);
    }

    public static function url(WC_Order $order): string
    {
        return home_url('/invoice/' . $order->get_order_key() . '/');
    }

    public static function number(WC_Order $order): string
    {
        $d = $order->get_date_paid() ?: $order->get_date_created();
        return sprintf('INV/%s/%06d', $d ? $d->date('Y/m') : wp_date('Y/m'), $order->get_id());
    }

    public function accountAction(array $actions, WC_Order $order): array
    {
        if ($order->is_paid()) {
            $actions['invoice'] = ['url' => self::url($order), 'name' => __('Invoice', 'lereng')];
        }
        return $actions;
    }

    public function adminAction(array $actions, WC_Order $order): array
    {
        $actions['lereng_invoice'] = ['url' => self::url($order), 'name' => __('Invoice', 'lereng'), 'action' => 'view'];
        return $actions;
    }

    public function link(WC_Order $order): void
    {
        if ($order->is_paid()) {
            printf('<p class="invoice-link"><a href="%s" target="_blank" rel="noopener">%s</a></p>', esc_url(self::url($order)), esc_html__('Download the invoice (print to PDF)', 'lereng'));
        }
    }

    public function render(): void
    {
        $key = get_query_var('lereng_invoice');
        if (!$key) {
            return;
        }
        $order = wc_get_order(wc_get_order_id_by_order_key($key));
        if (!$order instanceof WC_Order) {
            status_header(404);
            exit('Invoice not found.');
        }
        $total = (float) $order->get_total();
        $tax = (float) $order->get_total_tax();
        $dpp = $total - $tax;
        $npwp = $order->get_meta('_wc_other/lereng/npwp') ?: $order->get_meta('_wc_billing/lereng/npwp');
        $wantsFaktur = $order->get_meta('_wc_other/lereng/tax-invoice') ?: $order->get_meta('_wc_billing/lereng/tax-invoice');
        $money = static fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html><head><meta charset="utf-8"><title><?php echo esc_html(self::number($order)); ?></title>
<style>
body{font:14px/1.5 -apple-system,system-ui,sans-serif;color:#141412;background:#e9e6df;margin:0}
.sheet{max-width:820px;margin:24px auto;background:#fff;padding:48px 56px}
.top{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #141412;padding-bottom:20px}
.brand{font-weight:800;letter-spacing:.2em;font-size:20px}
.mono{font-family:ui-monospace,Menlo,monospace;font-size:12px;letter-spacing:.04em;color:#6B675F}
h1{font-size:28px;margin:0 0 4px;letter-spacing:-.02em}
.cols{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:24px 0}
table{width:100%;border-collapse:collapse;margin-top:8px}th,td{text-align:left;padding:10px 6px;border-bottom:1px solid #DCD7CC}th{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#6B675F}
td.r,th.r{text-align:right}
.totals td{border:0;padding:6px}.totals tr:last-child td{font-weight:800;font-size:16px;border-top:2px solid #141412}
.note{margin-top:28px;font-size:12px;color:#6B675F}
.paid{display:inline-block;border:2px solid #2F7D4F;color:#2F7D4F;padding:2px 10px;font-weight:700;letter-spacing:.1em;font-size:12px}
@media print{body{background:#fff}.sheet{margin:0;padding:24px}.noprint{display:none}}
</style></head><body><div class="sheet">
<div class="top"><div><div class="brand">LERENG</div><div class="mono"><?php echo esc_html(self::SELLER['name']); ?><br><?php echo esc_html(self::SELLER['address']); ?><br>NPWP <?php echo esc_html(self::SELLER['npwp']); ?></div></div>
<div style="text-align:right"><h1><?php esc_html_e('Invoice', 'lereng'); ?></h1><div class="mono"><?php echo esc_html(self::number($order)); ?><br><?php echo esc_html(wc_format_datetime($order->get_date_paid() ?: $order->get_date_created(), 'j F Y')); ?></div>
<?php if ($order->is_paid()) : ?><p><span class="paid"><?php esc_html_e('PAID', 'lereng'); ?></span></p><?php endif; ?></div></div>
<div class="cols"><div><div class="mono"><?php esc_html_e('BILLED TO', 'lereng'); ?></div><?php echo wp_kses_post($order->get_formatted_billing_address() ?: $order->get_billing_email()); ?><?php if ($npwp) : ?><br>NPWP <?php echo esc_html($npwp); ?><?php endif; ?></div>
<div><div class="mono"><?php esc_html_e('ORDER', 'lereng'); ?></div>#<?php echo esc_html($order->get_order_number()); ?> · <?php echo esc_html($order->get_payment_method_title()); ?><br><?php echo esc_html($order->get_shipping_method()); ?></div></div>
<table><thead><tr><th><?php esc_html_e('Item', 'lereng'); ?></th><th class="r"><?php esc_html_e('Qty', 'lereng'); ?></th><th class="r"><?php esc_html_e('Price', 'lereng'); ?></th><th class="r"><?php esc_html_e('Amount', 'lereng'); ?></th></tr></thead><tbody>
<?php foreach ($order->get_items() as $item) : $line = (float) $item->get_total() + (float) $item->get_total_tax(); ?>
<tr><td><?php echo esc_html($item->get_name()); ?></td><td class="r"><?php echo (int) $item->get_quantity(); ?></td><td class="r"><?php echo esc_html($money($line / max(1, $item->get_quantity()))); ?></td><td class="r"><?php echo esc_html($money($line)); ?></td></tr>
<?php endforeach; ?>
<?php if ((float) $order->get_shipping_total() > 0) : ?><tr><td><?php echo esc_html($order->get_shipping_method()); ?></td><td class="r">1</td><td class="r"></td><td class="r"><?php echo esc_html($money((float) $order->get_shipping_total() + (float) $order->get_shipping_tax())); ?></td></tr><?php endif; ?>
<?php foreach ($order->get_coupons() as $c) : ?><tr><td><?php echo esc_html(sprintf(__('Discount %s', 'lereng'), strtoupper($c->get_code()))); ?></td><td></td><td></td><td class="r">−<?php echo esc_html($money((float) $c->get_discount() + (float) $c->get_discount_tax())); ?></td></tr><?php endforeach; ?>
</tbody></table>
<table class="totals" style="width:50%;margin-left:auto"><tr><td><?php esc_html_e('Tax base (DPP)', 'lereng'); ?></td><td class="r"><?php echo esc_html($money($dpp)); ?></td></tr><tr><td><?php esc_html_e('PPN 11%', 'lereng'); ?></td><td class="r"><?php echo esc_html($money($tax)); ?></td></tr><tr><td><?php esc_html_e('Total', 'lereng'); ?></td><td class="r"><?php echo esc_html($money($total)); ?></td></tr></table>
<p class="note"><?php echo $wantsFaktur ? esc_html__('A tax invoice (faktur pajak) will be issued to the NPWP above within the month.', 'lereng') : esc_html__('Prices include 11% PPN. Need a tax invoice (faktur pajak)? Reply to your order email with your NPWP.', 'lereng'); ?> <?php esc_html_e('This is a demo store; no real tax documents are issued.', 'lereng'); ?></p>
<p class="noprint"><button onclick="print()"><?php esc_html_e('Print or save as PDF', 'lereng'); ?></button></p>
</div></body></html><?php
        exit;
    }
}
