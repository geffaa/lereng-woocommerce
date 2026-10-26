<?php

namespace Lereng\Fulfilment;

use WC_Email;

/** "Your coffee is on its way": sent when an order moves to Shipped. Editable in WooCommerce → Settings → Emails. */
class ShippedEmail extends WC_Email
{
    public function __construct()
    {
        $this->id = 'lereng_shipped';
        $this->customer_email = true;
        $this->title = __('Order shipped', 'lereng');
        $this->description = __('Sent to the customer when an order is marked Shipped, with the courier and tracking number.', 'lereng');
        $this->placeholders = ['{order_number}' => ''];
        add_action('woocommerce_order_status_shipped_notification', [$this, 'trigger'], 10, 2);
        parent::__construct();
    }

    public function get_default_subject(): string
    {
        return __('Your Lereng coffee is on its way (order #{order_number})', 'lereng');
    }

    public function get_default_heading(): string
    {
        return __('Your coffee is on its way', 'lereng');
    }

    public function trigger($orderId, $order = null): void
    {
        $this->setup_locale();
        $order = $order ?: wc_get_order($orderId);
        if ($order && !$order->get_meta('_lereng_shipped_mail')) {
            $this->object = $order;
            $this->recipient = $order->get_billing_email();
            $this->placeholders['{order_number}'] = $order->get_order_number();
            if ($this->is_enabled() && $this->get_recipient()) {
                $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
                $order->update_meta_data('_lereng_shipped_mail', time());
                $order->save();
            }
        }
        $this->restore_locale();
    }

    public function get_content_html(): string
    {
        $order = $this->object;
        $courier = $order->get_meta('_lereng_courier');
        $number = $order->get_meta('_lereng_tracking');
        ob_start();
        do_action('woocommerce_email_header', $this->get_heading(), $this);
        echo '<p>' . esc_html(sprintf(__('Hi %s,', 'lereng'), $order->get_billing_first_name())) . '</p>';
        echo '<p>' . esc_html(sprintf(__('Order #%1$s left the roastery today with %2$s.', 'lereng'), $order->get_order_number(), Tracking::COURIERS[$courier][0] ?? $courier)) . '</p>';
        echo '<p style="font:600 20px monospace;letter-spacing:1px">' . esc_html($number) . '</p>';
        echo '<p><a href="' . esc_url(home_url('/track-order/')) . '">' . esc_html__('Follow your parcel', 'lereng') . '</a></p>';
        echo '<p>' . esc_html__('Rest the beans for 3 to 5 days after roasting for the best cup; they peak around two weeks.', 'lereng') . '</p>';
        do_action('woocommerce_email_order_details', $order, false, false, $this);
        do_action('woocommerce_email_footer', $this);
        return (string) ob_get_clean();
    }

    public function get_content_plain(): string
    {
        $order = $this->object;
        return sprintf("Order #%s left the roastery with %s.\nTracking number: %s\nFollow it: %s\n", $order->get_order_number(), $order->get_meta('_lereng_courier'), $order->get_meta('_lereng_tracking'), home_url('/track-order/'));
    }
}
