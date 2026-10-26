<?php

namespace Lereng\Subscriptions;

/** Subscription rows and their event timeline. */
final class Repository
{
    private static function t(string $name = 'subscriptions'): string
    {
        global $wpdb;
        return $wpdb->prefix . 'lereng_' . $name;
    }

    public static function create(array $row): int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->insert(self::t(), $row + ['created_at' => $now, 'updated_at' => $now]);
        $id = (int) $wpdb->insert_id;
        self::event($id, 'created', $row['interval_weeks'] . ' weeks', $row['parent_order_id'] ?? null);
        return $id;
    }

    public static function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::t() . ' WHERE id = %d', $id), ARRAY_A);
        return $row ? self::hydrate($row) : null;
    }

    private static function hydrate(array $row): array
    {
        $row['address'] = $row['address'] ? json_decode($row['address'], true) : [];
        foreach (['id', 'customer_id', 'product_id', 'variation_id', 'quantity', 'interval_weeks', 'price', 'shipping_cost', 'parent_order_id', 'last_order_id', 'renewals', 'skipped'] as $k) {
            $row[$k] = (int) $row[$k];
        }
        return $row;
    }

    public static function update(int $id, array $fields, ?string $event = null, ?string $detail = null, ?int $orderId = null): void
    {
        global $wpdb;
        if (isset($fields['address']) && is_array($fields['address'])) {
            $fields['address'] = wp_json_encode($fields['address']);
        }
        $wpdb->update(self::t(), $fields + ['updated_at' => current_time('mysql', true)], ['id' => $id]);
        if ($event) {
            self::event($id, $event, $detail, $orderId);
        }
    }

    public static function event(int $id, string $type, ?string $detail = null, ?int $orderId = null, ?string $at = null): void
    {
        global $wpdb;
        $wpdb->insert(self::t('subscription_events'), ['subscription_id' => $id, 'type' => $type, 'detail' => $detail, 'order_id' => $orderId, 'created_at' => $at ?? current_time('mysql', true)]);
    }

    public static function events(int $id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t('subscription_events') . ' WHERE subscription_id = %d ORDER BY id DESC', $id), ARRAY_A);
    }

    public static function forCustomer(int $customerId): array
    {
        global $wpdb;
        return array_map([self::class, 'hydrate'], $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t() . ' WHERE customer_id = %d ORDER BY id DESC', $customerId), ARRAY_A));
    }

    /** Active subscriptions whose renewal should be created by $today. */
    public static function due(string $today): array
    {
        global $wpdb;
        $limit = (new \DateTimeImmutable($today))->modify('+' . Schedule::LEAD_DAYS . ' days')->format('Y-m-d');
        return array_map([self::class, 'hydrate'], $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t() . " WHERE status = 'active' AND next_date <= %s ORDER BY next_date LIMIT 200", $limit), ARRAY_A));
    }

    public static function pausedUntil(string $today): array
    {
        global $wpdb;
        return array_map([self::class, 'hydrate'], $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t() . " WHERE status = 'paused' AND paused_until <= %s", $today), ARRAY_A));
    }

    public static function forParentItem(int $orderId, int $productId, int $variationId): ?int
    {
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::t() . ' WHERE parent_order_id = %d AND product_id = %d AND variation_id = %d', $orderId, $productId, $variationId));
        return $id ? (int) $id : null;
    }

    public static function all(array $where = [], int $limit = 300): array
    {
        global $wpdb;
        $sql = 'SELECT * FROM ' . self::t();
        if (!empty($where['status'])) {
            $sql .= $wpdb->prepare(' WHERE status = %s', $where['status']);
        }
        return array_map([self::class, 'hydrate'], $wpdb->get_results($sql . ' ORDER BY id DESC LIMIT ' . (int) $limit, ARRAY_A));
    }

    /** status => count, and monthly recurring revenue of active ones. */
    public static function stats(): array
    {
        global $wpdb;
        $counts = [];
        foreach ($wpdb->get_results('SELECT status, COUNT(*) n FROM ' . self::t() . ' GROUP BY status', ARRAY_A) as $r) {
            $counts[$r['status']] = (int) $r['n'];
        }
        $mrr = 0;
        foreach ($wpdb->get_results('SELECT price, quantity, interval_weeks FROM ' . self::t() . " WHERE status = 'active'", ARRAY_A) as $r) {
            $mrr += Schedule::monthly((int) $r['price'], (int) $r['quantity'], (int) $r['interval_weeks']);
        }
        return ['counts' => $counts, 'mrr' => $mrr];
    }
}
