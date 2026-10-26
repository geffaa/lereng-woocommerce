<?php

namespace Lereng\GiftCards;

/**
 * Gift cards and their ledger. The balance column is a cached sum of the ledger, updated in the
 * same statement as each entry, with a guard so two orders can't spend the same rupiah twice.
 */
final class Repository
{
    private static function t(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'lereng_' . $name;
    }

    public static function create(array $card): array
    {
        global $wpdb;
        for ($i = 0; $i < 5; $i++) {
            $code = Code::generate();
            $row = $card + ['code' => $code, 'balance' => $card['initial_amount'], 'status' => 'active', 'created_at' => current_time('mysql', true)];
            if ($wpdb->insert(self::t('gift_cards'), $row)) {
                $id = (int) $wpdb->insert_id;
                $wpdb->insert(self::t('gift_card_ledger'), ['card_id' => $id, 'order_id' => $card['order_id'] ?? null, 'amount' => $card['initial_amount'], 'note' => 'Issued', 'created_at' => current_time('mysql', true)]);
                return self::find($code);
            }
        }
        throw new \RuntimeException('Could not issue a unique gift card code');
    }

    public static function find(string $code): ?array
    {
        global $wpdb;
        $code = Code::normalize($code);
        if (!$code) {
            return null;
        }
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::t('gift_cards') . ' WHERE code = %s', $code), ARRAY_A);
        return $row ?: null;
    }

    public static function forOrderItem(int $itemId): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t('gift_cards') . ' WHERE order_item_id = %d ORDER BY id', $itemId), ARRAY_A);
    }

    /** Cards a customer bought or received. */
    public static function forEmail(string $email): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t('gift_cards') . ' WHERE recipient_email = %s OR purchaser_email = %s ORDER BY id DESC', $email, $email), ARRAY_A);
    }

    public static function ledger(int $cardId): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t('gift_card_ledger') . ' WHERE card_id = %d ORDER BY id', $cardId), ARRAY_A);
    }

    /**
     * Moves money on a card: negative to spend, positive to give back. Spending never takes the
     * balance below zero; returns the amount actually moved.
     */
    public static function move(int $cardId, int $amount, ?int $orderId, string $note): int
    {
        global $wpdb;
        if ($amount < 0) {
            $ok = $wpdb->query($wpdb->prepare('UPDATE ' . self::t('gift_cards') . ' SET balance = balance - %d WHERE id = %d AND balance >= %d', -$amount, $cardId, -$amount));
            if (!$ok) {
                $left = (int) $wpdb->get_var($wpdb->prepare('SELECT balance FROM ' . self::t('gift_cards') . ' WHERE id = %d', $cardId));
                if ($left <= 0) {
                    return 0;
                }
                $amount = -$left;
                $wpdb->query($wpdb->prepare('UPDATE ' . self::t('gift_cards') . ' SET balance = balance - %d WHERE id = %d AND balance >= %d', $left, $cardId, $left));
            }
        } else {
            $wpdb->query($wpdb->prepare('UPDATE ' . self::t('gift_cards') . ' SET balance = balance + %d WHERE id = %d', $amount, $cardId));
        }
        $wpdb->insert(self::t('gift_card_ledger'), ['card_id' => $cardId, 'order_id' => $orderId, 'amount' => $amount, 'note' => $note, 'created_at' => current_time('mysql', true)]);
        return $amount;
    }

    public static function markDelivered(int $cardId): void
    {
        global $wpdb;
        $wpdb->update(self::t('gift_cards'), ['delivered_at' => current_time('mysql', true)], ['id' => $cardId]);
    }

    public static function all(int $limit = 200): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::t('gift_cards') . ' ORDER BY id DESC LIMIT %d', $limit), ARRAY_A);
    }
}
