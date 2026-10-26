<?php

namespace Lereng\Shipping;

use WP_Error;

/** Registers the courier method and explains when cold items can't reach an address. */
final class Shipping
{
    public function register(): void
    {
        add_filter('woocommerce_shipping_methods', static function (array $methods): array {
            $methods[CourierMethod::ID] = CourierMethod::class;
            return $methods;
        });
        add_action('woocommerce_store_api_cart_errors', [$this, 'chilledErrors'], 10, 2);
        add_action('woocommerce_check_cart_items', [$this, 'chilledNotice']);
        add_shortcode('lereng_shipping_table', [$this, 'table']);
    }

    /** Rates by region and service, rendered from the same table checkout uses. */
    public function table(): string
    {
        $regions = ['bali' => 'Bali', 'java' => 'Java', 'nusa' => 'Nusa Tenggara', 'sumatra' => 'Sumatra', 'kalimantan' => 'Kalimantan', 'sulawesi' => 'Sulawesi', 'east' => 'Maluku & Papua'];
        $html = '<figure class="wp-block-table is-style-stripes rate-table"><table><thead><tr><th>' . esc_html__('Per kg from Bali', 'lereng') . '</th>';
        foreach (RateTable::SERVICES as [$label]) {
            $html .= '<th>' . esc_html($label) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($regions as $key => $name) {
            $html .= '<tr><th scope="row">' . esc_html($name) . '</th>';
            foreach (RateTable::SERVICES as [, $table]) {
                $html .= isset($table[$key]) ? '<td>' . wp_kses_post(wc_price($table[$key][0])) . '<br><small>' . esc_html(RateTable::eta([$table[$key][1], $table[$key][2]])) . '</small></td>' : '<td>—</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table><figcaption>' . esc_html(sprintf(__('GoSend same day: %s flat in south Bali, up to 10 kg.', 'lereng'), wp_strip_all_tags(wc_price(RateTable::SAME_DAY_PRICE)))) . '</figcaption></figure>';
        return $html;
    }

    /** Message for carts with cold brew going somewhere a same-day courier can't reach. */
    public static function chilledProblem(): ?string
    {
        $cart = WC()->cart;
        if (!$cart) {
            return null;
        }
        $chilled = [];
        foreach ($cart->get_cart() as $item) {
            if ($item['data']->get_shipping_class() === CourierMethod::CHILLED_CLASS) {
                $chilled[] = $item['data']->get_name();
            }
        }
        $customer = WC()->customer;
        if (!$chilled || !$customer || !$customer->get_shipping_state()) {
            return null;
        }
        $state = $customer->get_shipping_state();
        $city = $customer->get_shipping_city();
        if ($state === 'BA' && RateTable::isSameDayCity($city)) {
            return null;
        }
        // Picking up at the roastery is always fine.
        $chosen = (array) (WC()->session ? WC()->session->get('chosen_shipping_methods') : []);
        foreach ($chosen as $m) {
            if (str_starts_with((string) $m, 'pickup_location')) {
                return null;
            }
        }
        return sprintf(
            /* translators: %s: product names */
            __('%s is delivered chilled by same-day courier in south Bali only. Remove it, or choose pickup at the roastery.', 'lereng'),
            implode(', ', array_unique($chilled))
        );
    }

    public function chilledErrors(WP_Error $errors, $cart): void
    {
        if ($msg = self::chilledProblem()) {
            $errors->add('lereng_chilled_destination', $msg);
        }
    }

    public function chilledNotice(): void
    {
        if ($msg = self::chilledProblem()) {
            wc_add_notice($msg, 'error');
        }
    }
}
