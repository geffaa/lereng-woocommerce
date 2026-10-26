<?php

namespace Lereng\GiftCards;

/**
 * Gift card codes like LRG-7KQ3-MZ9P. No 0/O or 1/I/L, so they survive being read out over the
 * phone. Customers can type them with or without dashes, in any case.
 */
final class Code
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    public const PREFIX = 'LRG';

    public static function generate(?callable $random = null): string
    {
        $random ??= static fn(int $max): int => random_int(0, $max);
        $chars = '';
        for ($i = 0; $i < 8; $i++) {
            $chars .= self::ALPHABET[$random(strlen(self::ALPHABET) - 1)];
        }
        return self::PREFIX . '-' . substr($chars, 0, 4) . '-' . substr($chars, 4);
    }

    /** Canonical form, or null if it can't be a gift card code. */
    public static function normalize(string $input): ?string
    {
        $s = strtoupper(preg_replace('/[\s\-]+/', '', $input));
        if (!preg_match('/^' . self::PREFIX . '([' . self::ALPHABET . ']{8})$/', $s, $m)) {
            return null;
        }
        return self::PREFIX . '-' . substr($m[1], 0, 4) . '-' . substr($m[1], 4);
    }
}
