<?php

namespace Lereng\Payments;

use WC_Order;

/** Bank virtual account: a one-off account number for this order, paid by transfer. */
final class VaGateway extends SandboxGateway
{
    public function __construct()
    {
        $this->id = 'lereng_va';
        $this->method_title = __('Virtual account', 'lereng');
        $this->method_description = __('Bank transfer to a one-off virtual account (BCA, Mandiri, BNI, BRI). Sandbox, modelled on Midtrans.', 'lereng');
        parent::__construct();
        $this->has_fields = true;
    }

    protected function defaultTitle(): string
    {
        return __('Bank transfer · virtual account', 'lereng');
    }

    protected function defaultDescription(): string
    {
        return __('Transfer from mobile banking or an ATM. Your account number is valid for 24 hours and confirms automatically.', 'lereng');
    }

    protected function defaultExpiryMinutes(): int
    {
        return 24 * 60;
    }

    /** Classic checkout: bank choice. (The block checkout renders its own, see assets/js/payment-blocks.js.) */
    public function payment_fields(): void
    {
        echo '<p>' . esc_html($this->description) . '</p><fieldset class="lereng-banks">';
        foreach (VirtualAccount::BANKS as $id => [$name]) {
            printf('<label><input type="radio" name="lereng_bank" value="%s" %s> %s</label> ', esc_attr($id), checked($id, 'bca', false), esc_html($name));
        }
        echo '</fieldset>';
    }

    protected function details(WC_Order $order): array
    {
        $bank = sanitize_key(wp_unslash($_POST['lereng_bank'] ?? 'bca')); // phpcs:ignore WordPress.Security.NonceVerification -- checkout nonce checked by WooCommerce.
        $bank = isset(VirtualAccount::BANKS[$bank]) ? $bank : 'bca';
        return ['bank' => $bank, 'va' => VirtualAccount::number($bank, $order->get_id(), $order->get_billing_phone())];
    }
}
