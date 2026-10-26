<?php

namespace Lereng\Bundles;

/** Bundle prices. Rupiah prices round to the nearest Rp 1.000, the way the shelf shows them. */
final class Pricing
{
    public static function round(float $rupiah): int
    {
        return (int) (round($rupiah / 1000) * 1000);
    }

    /** Discovery box: each pick is a 100 g bag (half the 200 g price), minus the bundle discount. */
    public static function pick(array $prices200g, float $discountPct): int
    {
        $sum = array_sum(array_map(static fn($p) => $p / 2, $prices200g));
        return self::round($sum * (1 - $discountPct / 100));
    }

    /** @param list<array{0:int,1:int}> $items [unit price, qty] */
    public static function fixed(array $items, float $discountPct): int
    {
        $sum = 0;
        foreach ($items as [$price, $qty]) {
            $sum += $price * $qty;
        }
        return self::round($sum * (1 - $discountPct / 100));
    }
}
