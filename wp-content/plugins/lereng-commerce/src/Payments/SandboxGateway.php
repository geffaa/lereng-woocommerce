<?php

namespace Lereng\Payments;

use WC_Order;
use WC_Payment_Gateway;

/**
 * Base for the QRIS and virtual account gateways. Modelled on how Midtrans and Xendit work:
 * the order is created as "pending payment", the customer is sent to a payment page with a QR
 * code or account number, and the gateway confirms by calling our webhook. In sandbox mode the
 * payment page has buttons that send that same signed webhook, so the whole flow can be tried.
 */
abstract class SandboxGateway extends WC_Payment_Gateway
{
    abstract protected function defaultTitle(): string;

    abstract protected function defaultDescription(): string;

    abstract protected function defaultExpiryMinutes(): int;

    /** Builds the method-specific part of the transaction (QR payload or VA number). */
    abstract protected function details(WC_Order $order): array;

    public function __construct()
    {
        $this->has_fields = false;
        $this->supports = ['products', 'refunds'];
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title', $this->defaultTitle());
        $this->description = $this->get_option('description', $this->defaultDescription());
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => ['title' => __('Enable', 'lereng'), 'type' => 'checkbox', 'label' => $this->method_title, 'default' => 'yes'],
            'title' => ['title' => __('Title', 'lereng'), 'type' => 'text', 'default' => $this->defaultTitle()],
            'description' => ['title' => __('Description', 'lereng'), 'type' => 'textarea', 'default' => $this->defaultDescription()],
            'expiry' => ['title' => __('Pay within (minutes)', 'lereng'), 'type' => 'number', 'default' => (string) $this->defaultExpiryMinutes()],
        ];
    }

    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        $this->startPayment($order);
        if (WC()->cart) {
            WC()->cart->empty_cart();
        }
        return ['result' => 'success', 'redirect' => Payments::payUrl($order)];
    }

    /**
     * Opens a payment for an order: transaction details, expiry, and the job that cancels it if
     * unpaid. Used at checkout and for subscription renewals (with a longer window).
     */
    public function startPayment(\WC_Order $order, ?int $expiresAt = null): array
    {
        $minutes = max(5, (int) $this->get_option('expiry', (string) $this->defaultExpiryMinutes()));
        $txn = [
            'order_id' => Payments::txnOrderId($order),
            'transaction_id' => wp_generate_uuid4(),
            'method' => $this->id,
            'amount' => (int) round((float) $order->get_total()),
            'created' => time(),
            'expires' => $expiresAt ?? time() + $minutes * 60,
        ] + $this->details($order);
        $order->update_meta_data('_lereng_payment', $txn);
        $order->set_payment_method($this);
        $order->set_transaction_id($txn['transaction_id']);
        $order->set_status('pending');
        $order->add_order_note(sprintf(__('Waiting for %1$s payment (expires %2$s).', 'lereng'), $this->method_title, wp_date('j M H:i', $txn['expires'])));
        $order->save();
        as_unschedule_all_actions('lereng_payment_expire', ['order_id' => $order->get_id()], 'lereng');
        as_schedule_single_action($txn['expires'] + 30, 'lereng_payment_expire', ['order_id' => $order->get_id()], 'lereng');
        return $txn;
    }

    public function process_refund($order_id, $amount = null, $reason = ''): bool
    {
        $order = wc_get_order($order_id);
        $order->add_order_note(sprintf(__('Refunded %1$s through %2$s (sandbox). %3$s', 'lereng'), wc_price((float) $amount), $this->title, $reason));
        return true;
    }
}
