<?php

namespace Lereng\Payments;

/**
 * Payment notification signatures, in the scheme Midtrans uses:
 * sha512(order_id + status_code + gross_amount + server_key). The webhook rejects anything that
 * doesn't match, so a forged "settlement" can't mark an order paid.
 */
final class Signature
{
    public static function make(string $orderId, string $statusCode, string $grossAmount, string $serverKey): string
    {
        return hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
    }

    public static function verify(array $payload, string $serverKey): bool
    {
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $k) {
            if (!isset($payload[$k]) || !is_string($payload[$k])) {
                return false;
            }
        }
        return hash_equals(self::make($payload['order_id'], $payload['status_code'], $payload['gross_amount'], $serverKey), $payload['signature_key']);
    }

    /** Midtrans transaction_status → what the order should become. */
    public static function outcome(string $transactionStatus, string $fraudStatus = 'accept'): string
    {
        return match ($transactionStatus) {
            'settlement' => 'paid',
            'capture' => $fraudStatus === 'accept' ? 'paid' : 'review',
            'pending' => 'pending',
            'expire' => 'expired',
            'cancel', 'deny' => 'failed',
            'refund', 'partial_refund' => 'refunded',
            default => 'ignore',
        };
    }
}
