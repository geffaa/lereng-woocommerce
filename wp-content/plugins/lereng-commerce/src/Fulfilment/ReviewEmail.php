<?php

namespace Lereng\Fulfilment;

use WC_Email;

/** "How was it?": three days after delivery, a link to review each coffee in the order. */
class ReviewEmail extends WC_Email
{
    public function __construct()
    {
        $this->id = 'lereng_review_request';
        $this->customer_email = true;
        $this->title = __('Review request', 'lereng');
        $this->description = __('Sent three days after an order is delivered, asking for a review of each coffee.', 'lereng');
        add_action('lereng_review_request_notification', [$this, 'trigger']);
        parent::__construct();
    }

    public function get_default_subject(): string
    {
        return __('How is the coffee?', 'lereng');
    }

    public function get_default_heading(): string
    {
        return __('How is the coffee?', 'lereng');
    }

    public function trigger($orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order || $order->get_meta('_lereng_review_mail')) {
            return;
        }
        $this->object = $order;
        $this->recipient = $order->get_billing_email();
        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), []);
            $order->update_meta_data('_lereng_review_mail', time());
            $order->save();
        }
    }

    public function get_content_html(): string
    {
        $order = $this->object;
        ob_start();
        do_action('woocommerce_email_header', $this->get_heading(), $this);
        echo '<p>' . esc_html(sprintf(__('Hi %s, your beans should be at their best now. A two-line review helps other people choose, and tells our roasters what to buy again.', 'lereng'), $order->get_billing_first_name())) . '</p><ul>';
        foreach ($order->get_items() as $item) {
            $p = $item->get_product();
            if ($p) {
                $parent = wc_get_product($p->get_parent_id() ?: $p->get_id());
                echo '<li><a href="' . esc_url($parent->get_permalink() . '#reviews') . '">' . esc_html(sprintf(__('Review %s', 'lereng'), $parent->get_name())) . '</a></li>';
            }
        }
        echo '</ul>';
        do_action('woocommerce_email_footer', $this);
        return (string) ob_get_clean();
    }

    public function get_content_plain(): string
    {
        return __('How is the coffee? Leave a review on the product page.', 'lereng');
    }
}
