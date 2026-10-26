<?php

namespace Lereng\Payments;

/** Virtual account numbers and how to pay them, per bank. */
final class VirtualAccount
{
    /** bank => [name, company prefix, total length] (prefixes are sandbox-style, not real). */
    public const BANKS = [
        'bca' => ['BCA', '12345', 16],
        'mandiri' => ['Mandiri', '88908', 16],
        'bni' => ['BNI', '9881', 16],
        'bri' => ['BRI', '26215', 15],
    ];

    public static function number(string $bank, int $orderId, string $phone = ''): string
    {
        [, $prefix, $length] = self::BANKS[$bank] ?? self::BANKS['bca'];
        // Prefix, then the order number, then the customer's phone digits as filler: stable per order.
        $digits = preg_replace('/\D/', '', $phone) ?: '0';
        $core = str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
        $fill = $length - strlen($prefix) - strlen($core);
        return $prefix . substr(str_pad(strrev($digits), $fill, '0'), 0, $fill) . $core;
    }

    /** Grouped for reading: 1234 5678 9012 3456. */
    public static function format(string $number): string
    {
        return trim(chunk_split($number, 4, ' '));
    }

    /** @return array<string, list<string>> channel => steps */
    public static function instructions(string $bank, string $number): array
    {
        $name = self::BANKS[$bank][0] ?? 'Bank';
        return [
            'm-banking' => [
                sprintf(__('Open %s mobile banking and choose Transfer → Virtual Account.', 'lereng'), $name),
                sprintf(__('Enter %s.', 'lereng'), self::format($number)),
                __('Check the name says Lereng Coffee and the amount matches, then confirm.', 'lereng'),
            ],
            'atm' => [
                sprintf(__('At a %s ATM choose Other transactions → Transfer → Virtual Account.', 'lereng'), $name),
                sprintf(__('Enter %s and confirm the amount.', 'lereng'), self::format($number)),
            ],
        ];
    }
}
