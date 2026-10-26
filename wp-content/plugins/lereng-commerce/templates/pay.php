<?php
/**
 * Payment page: the QR code or virtual account number for an unpaid order, a countdown, and
 * (in sandbox mode) buttons that send the gateway's webhook. Variables: $order (WC_Order).
 */
defined('ABSPATH') || exit;

use Lereng\Payments\Payments;
use Lereng\Payments\VirtualAccount;

$txn = (array) $order->get_meta('_lereng_payment');
$isVa = ($txn['method'] ?? '') === 'lereng_va';
$bank = $txn['bank'] ?? 'bca';
$config = [
    'orderId' => $order->get_id(),
    'key' => $order->get_order_key(),
    'expires' => (int) ($txn['expires'] ?? time()),
    'statusUrl' => rest_url('lereng/v1/payments/' . $order->get_id()),
    'simulateUrl' => rest_url('lereng/v1/payments/' . $order->get_id() . '/simulate'),
    'qr' => $txn['qr'] ?? null,
];
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<?php wp_head(); ?>
</head>
<body <?php body_class('lereng-pay-page'); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
<?php echo do_blocks('<!-- wp:template-part {"slug":"checkout-header","tagName":"header"} /-->'); // phpcs:ignore WordPress.Security.EscapeOutput -- block markup ?>
<main class="pay" data-pay='<?php echo esc_attr(wp_json_encode($config)); ?>'>
	<section class="pay__card">
		<p class="pay__kicker"><?php printf(esc_html__('Order #%s', 'lereng'), esc_html($order->get_order_number())); ?></p>
		<h1 class="pay__title"><?php echo $isVa ? esc_html__('Transfer to your virtual account', 'lereng') : esc_html__('Scan to pay with QRIS', 'lereng'); ?></h1>
		<p class="pay__amount"><?php echo wp_kses_post(wc_price($order->get_total())); ?></p>
		<p class="pay__timer" data-timer><?php esc_html_e('Pay within', 'lereng'); ?> <b data-countdown>--:--</b></p>

		<?php if ($isVa) : ?>
			<div class="pay__va">
				<p class="pay__label"><?php echo esc_html(VirtualAccount::BANKS[$bank][0]); ?> <?php esc_html_e('virtual account number', 'lereng'); ?></p>
				<p class="pay__number"><span data-va><?php echo esc_html(VirtualAccount::format($txn['va'] ?? '')); ?></span>
					<button type="button" class="pay__copy" data-copy="<?php echo esc_attr($txn['va'] ?? ''); ?>"><?php esc_html_e('Copy', 'lereng'); ?></button></p>
				<?php foreach (VirtualAccount::instructions($bank, $txn['va'] ?? '') as $channel => $steps) : ?>
					<details class="pay__how" <?php echo $channel === 'm-banking' ? 'open' : ''; ?>>
						<summary><?php echo $channel === 'm-banking' ? esc_html__('Mobile banking', 'lereng') : esc_html__('ATM', 'lereng'); ?></summary>
						<ol><?php foreach ($steps as $s) { echo '<li>' . esc_html($s) . '</li>'; } ?></ol>
					</details>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="pay__qr" data-qr aria-label="<?php esc_attr_e('QRIS code', 'lereng'); ?>"></div>
			<p class="pay__hint"><?php esc_html_e('Open GoPay, OVO, DANA, ShopeePay or your bank app, choose Scan or QRIS, and point it at the code.', 'lereng'); ?></p>
		<?php endif; ?>

		<p class="pay__status" data-status role="status"><span class="pay__dot"></span><?php esc_html_e('Waiting for your payment. This page updates by itself.', 'lereng'); ?></p>

		<?php if (Payments::sandbox()) : ?>
			<div class="pay__sandbox">
				<p><b><?php esc_html_e('Sandbox', 'lereng'); ?></b> · <?php esc_html_e('No money moves. These buttons send the same signed notification the payment gateway would.', 'lereng'); ?></p>
				<button type="button" data-simulate="settlement"><?php esc_html_e('Simulate a successful payment', 'lereng'); ?></button>
				<button type="button" data-simulate="expire" class="is-ghost"><?php esc_html_e('Simulate expiry', 'lereng'); ?></button>
			</div>
		<?php endif; ?>
	</section>
	<aside class="pay__summary">
		<h2><?php esc_html_e('Your order', 'lereng'); ?></h2>
		<ul>
		<?php foreach ($order->get_items() as $item) : ?>
			<li><span><?php echo esc_html($item->get_quantity() . '× ' . $item->get_name()); ?></span><span><?php echo wp_kses_post(wc_price((float) $item->get_total() + (float) $item->get_total_tax())); ?></span></li>
		<?php endforeach; ?>
		<?php if ((float) $order->get_shipping_total() > 0 || $order->get_shipping_method()) : ?>
			<li><span><?php echo esc_html($order->get_shipping_method()); ?></span><span><?php echo wp_kses_post(wc_price((float) $order->get_shipping_total() + (float) $order->get_shipping_tax())); ?></span></li>
		<?php endif; ?>
		</ul>
		<p class="pay__total"><span><?php esc_html_e('Total', 'lereng'); ?></span><span><?php echo wp_kses_post(wc_price($order->get_total())); ?></span></p>
		<p class="pay__cancel"><a href="<?php echo esc_url($order->get_cancel_order_url(wc_get_cart_url())); ?>"><?php esc_html_e('Cancel this order', 'lereng'); ?></a></p>
	</aside>
</main>
</div>
<?php wp_footer(); ?>
</body>
</html>
