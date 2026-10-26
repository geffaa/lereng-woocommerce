<?php

namespace Lereng\Payments;

/**
 * Dynamic QRIS payloads in the EMVCo merchant-presented format that Indonesian QRIS uses:
 * tag-length-value fields ending in a CRC-16/CCITT-FALSE checksum. The sandbox merchant ID
 * means no real wallet will pay it, but the structure is what a gateway returns.
 */
final class Qris
{
    public static function tlv(string $tag, string $value): string
    {
        return $tag . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
    }

    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    public static function payload(int $amount, string $billNumber, string $merchantName = 'LERENG COFFEE', string $city = 'BADUNG', string $merchantId = 'ID1026SANDBOX0001'): string
    {
        $account = self::tlv('00', 'ID.CO.QRIS.WWW') . self::tlv('01', '936009990000000001') . self::tlv('02', $merchantId) . self::tlv('03', 'UMI');
        $body = self::tlv('00', '01')            // payload format
            . self::tlv('01', '12')              // dynamic: one amount, one payment
            . self::tlv('26', $account)
            . self::tlv('52', '5814')            // MCC: fast food / coffee
            . self::tlv('53', '360')             // IDR
            . self::tlv('54', (string) $amount)
            . self::tlv('58', 'ID')
            . self::tlv('59', substr($merchantName, 0, 25))
            . self::tlv('60', substr($city, 0, 15))
            . self::tlv('62', self::tlv('01', $billNumber));
        $body .= '6304';
        return $body . self::crc16($body);
    }

    /** True if the payload's checksum matches its content. */
    public static function valid(string $payload): bool
    {
        return strlen($payload) > 8 && substr($payload, -8, 4) === '6304' && self::crc16(substr($payload, 0, -4)) === substr($payload, -4);
    }
}
