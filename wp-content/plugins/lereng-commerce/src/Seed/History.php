<?php

namespace Lereng\Seed;

use DateTimeImmutable;
use DateTimeZone;
use Lereng\Catalog\RoastSchedule;
use Lereng\Fulfilment\Tracking;
use Lereng\Payments\Payments;
use Lereng\Payments\Qris;
use Lereng\Payments\VirtualAccount;
use Lereng\Shipping\RateTable;
use Lereng\Subscriptions\Repository as Subs;
use Lereng\Subscriptions\Schedule;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;

/**
 * Twelve months of trading, so the admin, analytics and My account look like a real shop:
 * customers across Indonesia, orders with a growth trend, a weekly rhythm and a December peak,
 * subscriptions with their renewals, wholesale cafés, gift cards, reviews and pending work.
 * Deterministic (seeded random) so every rebuild gives the same store. Emails and the store's
 * own status hooks are switched off while it runs; dates are written as they would have been.
 */
final class History
{
    private int $s;
    private array $variations = [];
    private array $popularity = [];
    private array $customers = [];
    private array $stats = ['customers' => 0, 'orders' => 0, 'subscriptions' => 0, 'renewals' => 0, 'reviews' => 0, 'gift_cards' => 0, 'wholesale' => 0];
    /** @var callable */
    private $log;
    private DateTimeZone $tz;
    private DateTimeImmutable $now;

    public function __construct(private array $catalog, private array $people, callable $log, int $seed = 2026)
    {
        $this->s = $seed;
        $this->log = $log;
        $this->tz = new DateTimeZone('Asia/Makassar');
        $this->now = new DateTimeImmutable('now', $this->tz);
    }

    /* ---------------- deterministic randomness ---------------- */

    private function rand(): float
    {
        $this->s = ($this->s * 1103515245 + 12345) & 0x7fffffff;
        return $this->s / 0x80000000;
    }

    private function int(int $min, int $max): int
    {
        return $min + (int) floor($this->rand() * ($max - $min + 1));
    }

    private function pick(array $items)
    {
        return $items[(int) floor($this->rand() * count($items))];
    }

    /** @param array<string|int, float|int> $weights */
    private function weighted(array $weights)
    {
        $total = array_sum($weights);
        $r = $this->rand() * $total;
        foreach ($weights as $k => $w) {
            if (($r -= $w) <= 0) {
                return $k;
            }
        }
        return array_key_last($weights);
    }

    private function say(string $m): void
    {
        ($this->log)("  $m");
    }

    /* ---------------- setup ---------------- */

    private function quiet(): void
    {
        if (!defined('LERENG_SEEDING')) {
            define('LERENG_SEEDING', true);
        }
        add_filter('woocommerce_mail_callback', static fn() => static fn() => true);
        add_filter('pre_wp_mail', static fn() => true, 1);
        foreach (['processing', 'completed', 'cancelled', 'refunded', 'shipped', 'roasting', 'on-hold', 'pending'] as $st) {
            remove_all_actions("woocommerce_order_status_$st");
        }
        remove_all_actions('woocommerce_order_status_changed');
        remove_all_actions('woocommerce_payment_complete');
        remove_all_actions('woocommerce_new_order');
        remove_all_actions('woocommerce_update_order');
        // Status transitions (pending_to_processing…) still reach the mailer; switch every email off.
        foreach (WC()->mailer()->get_emails() as $email) {
            add_filter("woocommerce_email_enabled_{$email->id}", '__return_false', 99);
        }
        wp_suspend_cache_addition(true);
        wp_defer_term_counting(true);
        wp_defer_comment_counting(true);
    }

    /** A year of orders in one PHP process: drop the runtime object cache now and then. */
    private function release(): void
    {
        global $wpdb;
        wp_cache_flush_runtime();
        $wpdb->queries = [];
        gc_collect_cycles();
    }

    private function loadCatalog(): void
    {
        foreach ($this->catalog['products'] as $p) {
            $post = get_page_by_path($p['slug'], OBJECT, 'product');
            if (!$post) {
                continue;
            }
            $product = wc_get_product($post->ID);
            $vars = [];
            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $cid) {
                    $v = wc_get_product($cid);
                    $vars[] = ['id' => $cid, 'size' => $v->get_attribute('pa_size'), 'grind' => $v->get_attribute('pa_grind'), 'price' => (int) $v->get_regular_price(), 'attrs' => $v->get_attributes(), 'weight' => (float) $v->get_weight()];
                }
            } else {
                $vars[] = ['id' => $post->ID, 'size' => '', 'grind' => '', 'price' => (int) $product->get_regular_price(), 'attrs' => [], 'weight' => (float) $product->get_weight()];
            }
            $this->variations[$p['slug']] = ['product' => $post->ID, 'name' => $product->get_name(), 'type' => $p['type'], 'kind' => $p['kind'] ?? $p['category'] ?? '', 'vars' => $vars, 'score' => $p['score'] ?? 84, 'notes' => $p['notes'] ?? []];
            // How often it sells: house blends and famous origins most, micro-lots rarely.
            $w = match (true) {
                in_array($p['slug'], ['lereng-house-espresso', 'kopi-susu-blend', 'pagi-filter-blend'], true) => 14,
                in_array($p['slug'], ['gayo-wine-natural', 'bali-kintamani-natural', 'java-puntang-honey', 'gayo-washed', 'bali-kintamani-washed', 'toraja-sapan-washed', 'flores-bajawa-washed'], true) => 8,
                ($p['kind'] ?? '') === 'microlot' => 1.2,
                ($p['type'] ?? '') === 'coffee' => 3.5,
                ($p['category'] ?? '') === 'drip-bags' => 4,
                in_array($p['category'] ?? '', ['brewers', 'filters', 'kettles-scales', 'grinders'], true) => 1.1,
                ($p['category'] ?? '') === 'merch' => 0.8,
                ($p['category'] ?? '') === 'cold-brew' => 1.5,
                default => 0,
            };
            if ($w > 0) {
                $this->popularity[$p['slug']] = $w;
            }
        }
    }

    /* ---------------- customers ---------------- */

    private function person(string $mix): array
    {
        $origin = $this->weighted(json_decode('{}', true) ?: []) ?? '';
        unset($origin);
        $group = $this->weighted($this->mixWeights($mix));
        return [$this->pick($this->people['first'][$group]), $this->pick($this->people['last'][$group])];
    }

    private function mixWeights($mix): array
    {
        return is_array($mix) ? $mix : ['java' => 1];
    }

    private function customers(int $count): void
    {
        $cityWeights = [];
        foreach ($this->people['cities'] as $i => $c) {
            $cityWeights[$i] = $c[2];
        }
        $start = $this->now->modify('-365 days');
        for ($i = 0; $i < $count; $i++) {
            $city = $this->people['cities'][$this->weighted($cityWeights)];
            [$first, $last] = (function () use ($city) {
                $group = $this->weighted($city[4]);
                return [$this->pick($this->people['first'][$group]), $this->pick($this->people['last'][$group])];
            })();
            $email = strtolower(preg_replace('/[^a-z]/i', '', $first) . '.' . preg_replace('/[^a-z]/i', '', $last) . $i) . '@example.com';
            $address = [
                'first_name' => $first, 'last_name' => $last, 'address_1' => $this->pick($this->people['streets']) . ' No. ' . $this->int(1, 180),
                'city' => $city[0], 'state' => $city[1], 'postcode' => $city[3], 'country' => 'ID', 'phone' => '08' . $this->int(11, 13) . $this->int(1000000, 9999999),
            ];
            $joined = $start->modify('+' . (int) (365 * ($this->rand() ** 0.8)) . ' days')->setTime($this->int(7, 22), $this->int(0, 59));
            $account = $this->rand() < 0.82;
            $userId = 0;
            if ($account) {
                $existing = get_user_by('email', $email);
                $userId = $existing ? $existing->ID : wc_create_new_customer($email, '', wp_generate_password(16), ['first_name' => $first, 'last_name' => $last]);
                if (is_wp_error($userId)) {
                    $userId = 0;
                } else {
                    foreach ($address as $k => $v) {
                        update_user_meta($userId, "billing_$k", $v);
                        update_user_meta($userId, "shipping_$k", $v);
                    }
                    update_user_meta($userId, 'billing_email', $email);
                    wp_update_user(['ID' => $userId, 'user_registered' => $joined->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'display_name' => "$first $last"]);
                }
            }
            // Loyalty follows a long tail: a few customers place many of the orders.
            $this->customers[] = ['id' => $userId, 'email' => $email, 'address' => $address, 'joined' => $joined, 'weight' => 0.4 + 6 * ($this->rand() ** 4), 'orders' => 0, 'bali' => $address['state'] === 'BA'];
            $this->stats['customers']++;
        }
    }

    /* ---------------- orders ---------------- */

    private function variation(string $slug, ?string $size = null, ?string $grind = null): array
    {
        $vars = $this->variations[$slug]['vars'];
        if ($size || $grind) {
            foreach ($vars as $v) {
                if ((!$size || $v['size'] === $size) && (!$grind || $v['grind'] === $grind)) {
                    return $v;
                }
            }
        }
        if (count($vars) > 1 && $vars[0]['size']) {
            $size ??= $this->weighted(['200g' => 62, '500g' => 25, '1kg' => 13, '100g' => 0, '250g' => 0]);
            $grind ??= $this->weighted(['Whole bean' => 46, 'Pour over' => 24, 'Espresso' => 18, 'French press' => 7, 'Moka pot' => 5]);
            $match = array_values(array_filter($vars, static fn($v) => $v['size'] === $size && $v['grind'] === $grind));
            if (!$match) {
                $match = array_values(array_filter($vars, static fn($v) => $v['grind'] === $grind)) ?: $vars;
            }
            return $match[0];
        }
        return $vars[0];
    }

    /**
     * @param list<array{slug:string,var:array,qty:int,price?:int,meta?:array}> $lines
     */
    private function order(array $customer, DateTimeImmutable $at, array $lines, array $opts = []): WC_Order
    {
        $order = new WC_Order();
        $order->set_created_via($opts['via'] ?? 'checkout');
        $order->update_meta_data('_lereng_seeded', 1);
        $order->set_currency('IDR');
        $order->set_prices_include_tax(true);
        if ($customer['id']) {
            $order->set_customer_id($customer['id']);
        }
        $billing = $customer['address'] + ['email' => $customer['email']];
        $order->set_address($billing, 'billing');
        $order->set_address($customer['address'], 'shipping');
        $grams = 0;
        $subtotal = 0;
        foreach ($lines as $l) {
            $item = new WC_Order_Item_Product();
            $item->set_product_id($this->variations[$l['slug']]['product']);
            if ($l['var']['id'] !== $this->variations[$l['slug']]['product']) {
                $item->set_variation_id($l['var']['id']);
                $item->set_variation(array_combine(array_map(static fn($k) => "attribute_$k", array_keys($l['var']['attrs'])), $l['var']['attrs']));
            }
            $name = $this->variations[$l['slug']]['name'] . ($l['var']['size'] ? ' - ' . $l['var']['size'] . ($l['var']['grind'] ? ', ' . $l['var']['grind'] : '') : '');
            $item->set_name($name);
            $item->set_quantity($l['qty']);
            $unit = $l['price'] ?? $l['var']['price'];
            $item->set_subtotal((string) ($unit * $l['qty'] / 1.11));
            $item->set_total((string) ($unit * $l['qty'] / 1.11));
            foreach ($l['meta'] ?? [] as $k => $v) {
                $item->add_meta_data($k, $v);
            }
            $order->add_item($item);
            $grams += (int) ($l['var']['weight'] * $l['qty']);
            $subtotal += $unit * $l['qty'];
        }

        // Delivery: what this customer would have picked at checkout.
        if (empty($opts['virtual'])) {
            $a = $customer['address'];
            $pickup = $customer['bali'] && $this->rand() < 0.08;
            $ship = new WC_Order_Item_Shipping();
            if ($pickup) {
                $ship->set_method_title('Pick up at the roastery (Lereng Roastery, Kerobokan)');
                $ship->set_method_id('pickup_location');
                $ship->set_total('0');
            } else {
                $rates = RateTable::quote($a['state'], $a['city'], $grams, $subtotal);
                $r = $rates[min(count($rates) - 1, (int) $this->weighted([0 => 62, 1 => 22, 2 => 9, 3 => 4, 4 => 3]))] ?? $rates[0];
                if (!empty($opts['ship'])) {
                    $r = $opts['ship'];
                }
                $ship->set_method_title(sprintf('%s · %s', $r['label'], RateTable::eta($r['days'])));
                $ship->set_method_id('lereng_courier');
                $ship->add_meta_data('service', $r['id']);
                $ship->set_total((string) ($r['cost'] / 1.11));
            }
            $order->add_item($ship);
        }

        if (!empty($opts['coupon'])) {
            $order->save(); // coupon discounts look items up by ID
            $order->apply_coupon($opts['coupon']);
        }
        $order->calculate_totals(true);

        // Payment.
        $method = $opts['payment'] ?? $this->weighted(['lereng_qris' => 55, 'lereng_va' => 45]);
        $titles = ['lereng_qris' => 'QRIS · GoPay, OVO, DANA, ShopeePay, any bank app', 'lereng_va' => 'Bank transfer · virtual account', 'bacs' => 'Invoice · bank transfer within 14 days'];
        $order->set_payment_method($method);
        $order->set_payment_method_title($titles[$method]);
        $order->set_date_created($at->getTimestamp());
        $order->save(); // id for payment details
        if ($method !== 'bacs') {
            $bank = $this->weighted(['bca' => 50, 'mandiri' => 25, 'bni' => 15, 'bri' => 10]);
            $txn = ['order_id' => Payments::txnOrderId($order), 'transaction_id' => wp_generate_uuid4(), 'method' => $method, 'amount' => (int) $order->get_total(), 'created' => $at->getTimestamp(), 'expires' => $at->getTimestamp() + ($method === 'lereng_va' ? 86400 : 900)];
            $txn += $method === 'lereng_va' ? ['bank' => $bank, 'va' => VirtualAccount::number($bank, $order->get_id(), $customer['address']['phone'])] : ['qr' => Qris::payload((int) $order->get_total(), Payments::txnOrderId($order))];
            $order->update_meta_data('_lereng_payment', $txn);
            $order->set_transaction_id($txn['transaction_id']);
        }

        // Where it is now.
        $roast = RoastSchedule::nextRoast($at, $opts['preorder'] ?? null);
        $hasCoffee = (bool) array_filter($lines, fn($l) => in_array($this->variations[$l['slug']]['type'], ['coffee', 'simple', 'variable-merch'], true) && !str_starts_with($l['slug'], 'lereng-gift'));
        if ($hasCoffee) {
            $order->update_meta_data('_lereng_roast_date', $roast->format('Y-m-d'));
        }
        $paidAt = $at->modify('+' . $this->int(2, 40) . ' minutes');
        $status = $opts['status'] ?? $this->statusFor($at, $roast);
        if ($status === 'cancelled') {
            $order->add_order_note('The payment window closed without payment.', 0, false);
        } elseif ($status !== 'pending') {
            if ($method === 'bacs') {
                $paidAt = $at->modify('+' . $this->int(3, 12) . ' days');
            }
            if ($paidAt <= $this->now || $method !== 'bacs') {
                $order->set_date_paid(min($paidAt, $this->now)->getTimestamp());
            }
            if (in_array($status, ['shipped', 'completed', 'refunded'], true) && empty($opts['virtual']) && ($ship = $order->get_items('shipping')) && reset($ship)->get_method_id() === 'lereng_courier') {
                $shipped = $roast->setTime(14, $this->int(0, 59));
                $courier = Tracking::courierFor($order->get_shipping_method());
                $order->update_meta_data('_lereng_courier', $courier);
                $order->update_meta_data('_lereng_tracking', Tracking::number($courier, $order->get_id()));
                $order->update_meta_data('_lereng_shipped_at', $shipped->getTimestamp());
            }
            if ($status === 'completed') {
                $order->set_date_completed(min($roast->modify('+' . $this->int(1, 4) . ' days'), $this->now)->getTimestamp());
            }
        }
        foreach ($opts['meta'] ?? [] as $k => $v) {
            $order->update_meta_data($k, $v);
        }
        $order->set_status($status);
        $order->save();
        $this->stats['orders']++;
        return $order;
    }

    /** Status an order placed at $at would have by now. */
    private function statusFor(DateTimeImmutable $at, DateTimeImmutable $roast): string
    {
        $age = ($this->now->getTimestamp() - $at->getTimestamp()) / 86400;
        if ($this->rand() < 0.04) {
            return 'cancelled'; // never paid
        }
        if ($age > 9) {
            return $this->rand() < 0.012 ? 'refunded' : 'completed';
        }
        $today = $this->now->format('Y-m-d');
        $r = $roast->format('Y-m-d');
        if ($r > $today) {
            return 'processing';
        }
        if ($r === $today) {
            return (int) $this->now->format('G') >= 10 ? 'roasting' : 'processing';
        }
        return $roast->modify('+3 days') < $this->now ? 'completed' : 'shipped';
    }

    private function basket(array $customer, bool $first, DateTimeImmutable $at): array
    {
        $lines = [];
        $n = $this->weighted([1 => 55, 2 => 30, 3 => 12, 4 => 3]);
        $micro = $at > $this->now->modify('-60 days');
        $seen = [];
        for ($i = 0; $i < $n; $i++) {
            $weights = $this->popularity;
            if (!$micro) {
                foreach ($weights as $slug => $w) {
                    if (($this->variations[$slug]['kind'] ?? '') === 'microlot') {
                        unset($weights[$slug]);
                    }
                }
            }
            if (!$customer['bali']) {
                unset($weights['cold-brew-concentrate-500'], $weights['cold-brew-concentrate-1l']);
            }
            $slug = $this->weighted($weights);
            if (isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $var = $this->variation($slug, ($this->variations[$slug]['kind'] ?? '') === 'microlot' ? '100g' : null);
            $lines[] = ['slug' => $slug, 'var' => $var, 'qty' => $this->weighted([1 => 80, 2 => 16, 3 => 4])];
        }
        return $lines;
    }

    private function orders(): void
    {
        $start = $this->now->modify('-365 days')->setTime(0, 0);
        $cWeights = [];
        $day = $start;
        $k = 0;
        while ($day < $this->now) {
            $t = ($day->getTimestamp() - $start->getTimestamp()) / (365 * 86400);
            $expected = 1.6 + 3.4 * $t;                                   // growth
            $expected *= [1 => 1.15, 2 => 1.05, 3 => 1.0, 4 => 1.05, 5 => 0.95, 6 => 0.8, 7 => 0.85][(int) $day->format('N')];
            $md = $day->format('m-d');
            if ($md >= '11-25' || $md <= '01-03') {
                $expected *= 1.45;                                          // gifts and holidays
            }
            if ($md >= '02-18' && $md <= '03-19') {
                $expected *= 0.85;                                          // Ramadan 2026: mornings shift
            }
            $count = (int) floor($expected + $this->rand());
            for ($i = 0; $i < $count; $i++) {
                $at = $day->setTime($this->weighted([7 => 6, 8 => 9, 9 => 10, 10 => 9, 11 => 8, 12 => 7, 13 => 6, 14 => 6, 15 => 6, 16 => 6, 17 => 6, 18 => 6, 19 => 8, 20 => 9, 21 => 8, 22 => 5]), $this->int(0, 59));
                if ($at > $this->now) {
                    continue;
                }
                $pool = array_filter($this->customers, static fn($c) => $c['joined'] <= $at);
                if (!$pool) {
                    continue;
                }
                $weights = [];
                foreach ($pool as $idx => $c) {
                    $weights[$idx] = $c['weight'];
                }
                $idx = $this->weighted($weights);
                $c = &$this->customers[$idx];
                $first = $c['orders'] === 0;
                $lines = $this->basket($c, $first, $at);
                $subtotal = array_sum(array_map(static fn($l) => $l['var']['price'] * $l['qty'], $lines));
                $coupon = null;
                if ($first && $this->rand() < 0.3) {
                    $coupon = 'welcome10';
                } elseif ($c['bali'] && $subtotal >= 400000 && $this->rand() < 0.15) {
                    $coupon = 'bali50';
                }
                $this->order($c, $at, $lines, ['coupon' => $coupon]);
                $c['orders']++;
                unset($c);
                if (++$k % 100 === 0) {
                    $this->say("$k orders…");
                    $this->release();
                    wp_cache_flush();
                }
            }
            $day = $day->modify('+1 day');
        }
    }

    /* ---------------- subscriptions ---------------- */

    private function subscriptions(int $count): void
    {
        $withAccounts = array_keys(array_filter($this->customers, static fn($c) => $c['id'] > 0));
        $subscribable = array_keys(array_filter($this->variations, static fn($v, $slug) => $v['type'] === 'coffee' && $v['kind'] !== 'microlot', ARRAY_FILTER_USE_BOTH));
        $reasons = ['too_much' => 35, 'price' => 20, 'taste' => 10, 'moving' => 20, 'other' => 15];
        for ($n = 0; $n < $count; $n++) {
            $c = $this->customers[$this->pick($withAccounts)];
            $startAt = max($c['joined'], $this->now->modify('-' . (int) (330 * $this->rand()) . ' days'))->setTime($this->int(7, 21), $this->int(0, 59));
            $slug = $this->rand() < 0.22 ? 'roasters-choice-subscription' : $this->pick($subscribable);
            $var = $this->variation($slug, $this->weighted(['200g' => 70, '500g' => 30]), $this->weighted(['Whole bean' => 50, 'Pour over' => 25, 'Espresso' => 25]));
            $interval = (int) $this->weighted([1 => 15, 2 => 60, 4 => 25]);
            $price = Schedule::price($var['price']);
            $parent = $this->order($c, $startAt, [['slug' => $slug, 'var' => $var, 'qty' => 1, 'price' => $price, 'meta' => ['_lereng_sub_interval' => $interval, 'Subscription' => \Lereng\Subscriptions\Subscriptions::intervalLabel($interval)]]], ['status' => $startAt < $this->now->modify('-9 days') ? 'completed' : 'processing']);
            $ship = $parent->get_items('shipping');
            $ship = $ship ? reset($ship) : null;
            $fate = $this->weighted(['active' => 68, 'paused' => 9, 'cancelled' => 23]);
            $endAt = $fate === 'cancelled' ? $startAt->modify('+' . $this->int(20, max(25, (int) (($this->now->getTimestamp() - $startAt->getTimestamp()) / 86400))) . ' days') : $this->now;
            if ($endAt > $this->now) {
                $endAt = $this->now;
            }
            $id = Subs::create([
                'customer_id' => $c['id'], 'status' => 'active', 'product_id' => $this->variations[$slug]['product'], 'variation_id' => $var['id'] !== $this->variations[$slug]['product'] ? $var['id'] : 0,
                'quantity' => 1, 'interval_weeks' => $interval, 'discount_pct' => Schedule::DISCOUNT, 'price' => $price,
                'shipping_method' => $ship ? $ship->get_method_id() . ':' . ($ship->get_meta('service') ?: '') . '|' . $ship->get_name() : null,
                'shipping_cost' => $ship ? (int) round((float) $ship->get_total() * 1.11) : 0, 'payment_method' => $parent->get_payment_method(),
                'address' => wp_json_encode(['shipping' => $parent->get_address('shipping'), 'billing' => $parent->get_address('billing'), 'bank' => ((array) $parent->get_meta('_lereng_payment'))['bank'] ?? 'bca']),
                'next_date' => null, 'parent_order_id' => $parent->get_id(),
            ]);
            global $wpdb;
            $wpdb->update($wpdb->prefix . 'lereng_subscriptions', ['created_at' => $startAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')], ['id' => $id]);
            $wpdb->update($wpdb->prefix . 'lereng_subscription_events', ['created_at' => $startAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')], ['subscription_id' => $id]);
            $this->stats['subscriptions']++;

            // Renewals until today (or until it ended), with the odd skip.
            $deliver = Schedule::next(RoastSchedule::nextRoast($startAt)->format('Y-m-d'), $interval);
            $renewals = 0;
            $skipped = 0;
            $last = null;
            while (true) {
                $createdAt = (new DateTimeImmutable($deliver . ' 08:00', $this->tz))->modify('-' . Schedule::LEAD_DAYS . ' days');
                if ($createdAt > $endAt) {
                    break;
                }
                $roll = $this->rand();
                if ($roll < 0.07) {
                    Subs::event($id, 'skipped', wp_date('j M', strtotime($deliver)) . ' (customer)', null, $createdAt->modify('-3 days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
                    $skipped++;
                } else {
                    $unpaid = $roll > 0.95;
                    $o = $this->order($c, $createdAt, [['slug' => $slug, 'var' => $var, 'qty' => 1, 'price' => $price, 'meta' => ['Subscription' => sprintf('#%d, %s', $id, \Lereng\Subscriptions\Subscriptions::intervalLabel($interval))]]], [
                        'via' => 'lereng_subscription', 'status' => $unpaid ? 'cancelled' : null,
                        'ship' => $ship ? ['id' => (string) $ship->get_meta('service'), 'label' => explode(' · ', $ship->get_name())[0], 'cost' => (int) round((float) $ship->get_total() * 1.11), 'days' => [1, 3]] : null,
                        'meta' => ['_lereng_subscription_id' => $id, '_lereng_renewal_for' => $deliver],
                    ]);
                    $o->update_meta_data('_lereng_roast_date', $deliver);
                    $o->save();
                    $utc = static fn(DateTimeImmutable $d) => $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                    Subs::event($id, 'renewal_created', wp_date('j M', strtotime($deliver)), $o->get_id(), $utc($createdAt));
                    if ($unpaid) {
                        Subs::event($id, 'renewal_unpaid', 'Not paid by roast day, delivery skipped', $o->get_id(), $utc(new DateTimeImmutable($deliver . ' 10:00', $this->tz)));
                        $skipped++;
                    } elseif ($o->get_date_paid()) {
                        Subs::event($id, 'renewal_paid', wp_strip_all_tags(wc_price((float) $o->get_total())), $o->get_id(), $utc($createdAt->modify('+' . $this->int(1, 30) . ' hours')));
                        $renewals++;
                    }
                    $last = $o->get_id();
                    $this->stats['renewals']++;
                }
                $deliver = Schedule::next($deliver, $interval);
            }
            $fields = ['renewals' => $renewals, 'skipped' => $skipped, 'last_order_id' => $last, 'next_date' => $deliver];
            if ($fate === 'cancelled') {
                $reason = $this->weighted($reasons);
                $fields += ['status' => 'cancelled', 'cancel_reason' => $reason];
                Subs::event($id, 'cancelled', \Lereng\Subscriptions\Account::REASONS[$reason] . ' (customer)', null, $endAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
            } elseif ($fate === 'paused') {
                $until = $this->now->modify('+' . $this->int(5, 60) . ' days')->format('Y-m-d');
                $fields += ['status' => 'paused', 'paused_until' => $until];
                Subs::event($id, 'paused', 'until ' . wp_date('j M', strtotime($until)) . ' (customer)', null, $this->now->modify('-' . $this->int(1, 20) . ' days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
            }
            Subs::update($id, $fields);
        }
    }

    /* ---------------- wholesale, gift cards, reviews ---------------- */

    private function wholesale(): void
    {
        $cafes = [['Kopi Senja Canggu', 'Canggu'], ['Warung Pagi Ubud', 'Ubud'], ['Ruang Seduh Denpasar', 'Denpasar'], ['Villa Kayu Seminyak', 'Seminyak'], ['Kantor Kreatif Sanur', 'Sanur'], ['Teras Kopi Jimbaran', 'Jimbaran']];
        foreach ($cafes as $i => [$name, $city]) {
            $email = 'orders@' . sanitize_title($name) . '.example';
            $userId = get_user_by('email', $email)?->ID ?: wc_create_new_customer($email, '', wp_generate_password(16), ['first_name' => explode(' ', $name)[0]]);
            if (is_wp_error($userId)) {
                continue;
            }
            (new \WP_User($userId))->set_role('wholesale_customer');
            $address = ['first_name' => 'Purchasing', 'last_name' => $name, 'company' => $name, 'address_1' => $this->pick($this->people['streets']) . ' No. ' . $this->int(1, 99), 'city' => $city, 'state' => 'BA', 'postcode' => '80361', 'country' => 'ID', 'phone' => '0812' . $this->int(1000000, 9999999)];
            foreach ($address as $k => $v) {
                update_user_meta($userId, "billing_$k", $v);
                update_user_meta($userId, "shipping_$k", $v);
            }
            $c = ['id' => $userId, 'email' => $email, 'address' => $address, 'bali' => true];
            $since = $this->now->modify('-' . $this->int(60, 330) . ' days');
            $house = $this->pick(['lereng-house-espresso', 'kopi-susu-blend', 'lereng-house-espresso']);
            for ($at = $since; $at < $this->now; $at = $at->modify('+' . $this->pick([7, 7, 14]) . ' days')) {
                $kg = $this->int(5, 15);
                $pct = \Lereng\Wholesale\Wholesale::discount($kg);
                $var = $this->variation($house, '1kg', 'Espresso');
                $lines = [['slug' => $house, 'var' => $var, 'qty' => $kg, 'price' => \Lereng\Bundles\Pricing::round($var['price'] * (1 - $pct / 100))]];
                if ($this->rand() < 0.4) {
                    $so = $this->pick(['gayo-washed', 'bali-kintamani-natural', 'java-puntang-honey', 'flores-bajawa-washed']);
                    $sv = $this->variation($so, '1kg', 'Pour over');
                    $lines[] = ['slug' => $so, 'var' => $sv, 'qty' => 2, 'price' => \Lereng\Bundles\Pricing::round($sv['price'] * (1 - $pct / 100))];
                }
                $at2 = $at->setTime(9, $this->int(0, 59));
                $this->order($c, $at2, $lines, ['payment' => 'bacs', 'status' => $at2 < $this->now->modify('-14 days') ? 'completed' : ($this->rand() < 0.5 ? 'on-hold' : 'processing')]);
                $this->stats['wholesale']++;
            }
        }
        foreach ([['Kopi Kecil Kuta', 'Kuta', '5–10 kg'], ['Bukit Yoga Retreat', 'Uluwatu', '10–25 kg'], ['Studio Tenun Gianyar', 'Gianyar', '5–10 kg']] as [$name, $city, $vol]) {
            if (get_page_by_title($name, OBJECT, 'lereng_wholesale')) {
                continue;
            }
            $id = wp_insert_post(['post_type' => 'lereng_wholesale', 'post_status' => 'private', 'post_title' => $name]);
            foreach (['contact' => 'Owner', 'type' => 'Café', 'city' => $city, 'volume' => $vol, 'status' => 'pending', 'email' => 'hello@' . sanitize_title($name) . '.example'] as $k => $v) {
                update_post_meta($id, "_$k", $v);
            }
        }
    }

    private function giftCards(int $count): void
    {
        global $wpdb;
        $amounts = [100000 => 35, 250000 => 40, 500000 => 20, 1000000 => 5];
        $with = array_values(array_filter($this->customers, static fn($c) => $c['id'] > 0));
        for ($i = 0; $i < $count; $i++) {
            $buyer = $this->pick($with);
            $to = $this->pick($this->customers);
            $amount = (int) $this->weighted($amounts);
            $at = $this->now->modify('-' . $this->int(3, 360) . ' days')->setTime($this->int(8, 22), $this->int(0, 59));
            $var = $this->variation('lereng-gift-card');
            foreach ($this->variations['lereng-gift-card']['vars'] as $v) {
                if ($v['price'] === $amount) {
                    $var = $v;
                }
            }
            $order = $this->order($buyer, $at, [['slug' => 'lereng-gift-card', 'var' => $var, 'qty' => 1, 'meta' => ['To' => $to['email']]]], ['virtual' => true, 'status' => 'completed']);
            $card = \Lereng\GiftCards\Repository::create(['initial_amount' => $amount, 'order_id' => $order->get_id(), 'purchaser_email' => $buyer['email'], 'recipient_name' => $to['address']['first_name'], 'recipient_email' => $to['email'], 'message' => $this->pick(['Selamat ulang tahun!', 'Thank you for everything.', 'Happy birthday, enjoy the coffee!', 'Untuk pagi-pagi yang lebih enak.', 'Congrats on the new place!'])]);
            $utc = $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $wpdb->update($wpdb->prefix . 'lereng_gift_cards', ['created_at' => $utc, 'delivered_at' => $utc], ['id' => $card['id']]);
            $wpdb->update($wpdb->prefix . 'lereng_gift_card_ledger', ['created_at' => $utc], ['card_id' => $card['id']]);
            if ($this->rand() < 0.7) {
                $spend = min($amount, $this->int(60, 140) * 1000);
                \Lereng\GiftCards\Repository::move((int) $card['id'], -$spend, null, 'Order (history)');
            }
            $this->stats['gift_cards']++;
        }
    }

    private function reviews(): void
    {
        $orders = wc_get_orders(['limit' => -1, 'status' => ['completed'], 'return' => 'objects', 'created_via' => 'checkout']);
        $done = [];
        foreach ($orders as $order) {
            if ($this->rand() > 0.33) {
                continue;
            }
            foreach ($order->get_items() as $item) {
                $pid = $item->get_product_id();
                $slug = get_post_field('post_name', $pid);
                $info = $this->variations[$slug] ?? null;
                if (!$info || $info['type'] !== 'coffee' || isset($done[$order->get_id() . ':' . $pid])) {
                    continue;
                }
                $done[$order->get_id() . ':' . $pid] = true;
                $quality = ($info['score'] - 82) / 7; // 0 … 1
                $rating = (int) $this->weighted([5 => 50 + 30 * $quality, 4 => 32, 3 => 10 - 4 * $quality, 2 => 3, 1 => 1]);
                $notes = $info['notes'] ?: ['chocolate', 'caramel'];
                $vars = ['{origin}' => explode(' ', $info['name'])[0], '{adj}' => $this->pick(['bright', 'bold', 'fruity', 'roasty']), '{adj2}' => $this->pick(['brighter', 'darker', 'sweeter']), '{method}' => $this->pick($this->people['methods']), '{note1}' => strtolower($this->pick($notes)), '{note2}' => strtolower(end($notes)), '{days}' => (string) $this->int(1, 3), '{city}' => $order->get_shipping_city()];
                $text = $this->rand() < 0.18
                    ? strtr($this->pick($this->people['review_id']), $vars)
                    : strtr($this->pick($this->people['review_open'][max(2, $rating)] ?? $this->people['review_open'][4]) . ' ' . $this->pick($this->people['review_body']), $vars);
                $at = ($order->get_date_completed() ?: $order->get_date_created())->getTimestamp() + $this->int(2, 12) * 86400;
                if ($at > $this->now->getTimestamp()) {
                    continue;
                }
                $id = wp_insert_comment([
                    'comment_post_ID' => $pid, 'comment_author' => $order->get_billing_first_name() . ' ' . mb_substr($order->get_billing_last_name(), 0, 1) . '.', 'comment_author_email' => $order->get_billing_email(),
                    'comment_content' => ucfirst($text), 'comment_type' => 'review', 'comment_approved' => 1, 'user_id' => $order->get_customer_id(),
                    'comment_date' => wp_date('Y-m-d H:i:s', $at), 'comment_date_gmt' => gmdate('Y-m-d H:i:s', $at),
                ]);
                update_comment_meta($id, 'rating', $rating);
                update_comment_meta($id, 'verified', 1);
                $this->stats['reviews']++;
            }
        }
        foreach (array_unique(array_map(static fn($v) => $v['product'], $this->variations)) as $pid) {
            \WC_Comments::clear_transients($pid);
            $p = wc_get_product($pid);
            $p->set_rating_counts(\WC_Comments::get_rating_counts_for_product($p));
            $p->set_average_rating(\WC_Comments::get_average_rating_for_product($p));
            $p->set_review_count(\WC_Comments::get_review_count_for_product($p));
            $p->save();
        }
    }

    private function pending(): void
    {
        global $wpdb;
        $grinder = get_page_by_path('electric-grinder-flat', OBJECT, 'product');
        foreach (array_slice($this->customers, 0, 9) as $c) {
            $wpdb->query($wpdb->prepare('INSERT IGNORE INTO ' . $wpdb->prefix . 'lereng_restock (product_id, variation_id, email, created_at) VALUES (%d, 0, %s, %s)', $grinder->ID, $c['email'], current_time('mysql', true)));
        }
    }

    /* ---------------- run ---------------- */

    public function run(bool $force = false): void
    {
        if (get_option('lereng_history_done') && !$force) {
            $this->say('history already generated (pass "history force" to redo)');
            return;
        }
        ini_set('memory_limit', '1024M');
        $this->quiet();
        if ($force) {
            foreach (wc_get_orders(['limit' => -1, 'return' => 'ids', 'meta_key' => '_lereng_seeded', 'meta_value' => 1]) as $id) {
                wc_get_order($id)?->delete(true);
            }
        }
        $this->loadCatalog();
        $this->customers(320);
        $this->say("{$this->stats['customers']} customers");
        $this->orders();
        $this->subscriptions(115);
        $this->wholesale();
        $this->giftCards(40);
        wp_defer_comment_counting(false);
        $this->reviews();
        $this->pending();
        wp_defer_term_counting(false);
        update_option('lereng_history_done', time());
        $this->say(implode(', ', array_map(static fn($k, $v) => "$v $k", array_keys($this->stats), $this->stats)));
    }
}
