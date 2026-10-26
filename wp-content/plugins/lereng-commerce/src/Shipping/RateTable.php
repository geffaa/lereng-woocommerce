<?php

namespace Lereng\Shipping;

/**
 * Courier rates from the roastery in Bali to anywhere in Indonesia. Pure: no WordPress, so it
 * is unit-tested directly. Prices are per billable kilo (rounded up, minimum 1) by destination
 * region, close to what JNE and SiCepat charge from Denpasar. A real store would swap the
 * table for a rates API (RajaOngkir, Biteship) behind the same quote() call.
 */
final class RateTable
{
    /** WooCommerce province code → region. */
    public const REGIONS = [
        'BA' => 'bali',
        'JK' => 'java', 'JB' => 'java', 'BT' => 'java', 'JT' => 'java', 'YO' => 'java', 'JI' => 'java',
        'NB' => 'nusa', 'NT' => 'nusa',
        'AC' => 'sumatra', 'SU' => 'sumatra', 'SB' => 'sumatra', 'RI' => 'sumatra', 'KR' => 'sumatra', 'JA' => 'sumatra', 'SS' => 'sumatra', 'BB' => 'sumatra', 'BE' => 'sumatra', 'LA' => 'sumatra',
        'KB' => 'kalimantan', 'KT' => 'kalimantan', 'KI' => 'kalimantan', 'KS' => 'kalimantan', 'KU' => 'kalimantan',
        'SA' => 'sulawesi', 'ST' => 'sulawesi', 'SG' => 'sulawesi', 'SR' => 'sulawesi', 'SN' => 'sulawesi', 'GO' => 'sulawesi',
        'MA' => 'east', 'MU' => 'east', 'PA' => 'east', 'PB' => 'east',
    ];

    /** service => [label, [region => [price per kg, min days, max days]]] */
    public const SERVICES = [
        'jne_reg' => ['JNE REG', [
            'bali' => [10000, 1, 2], 'java' => [22000, 2, 3], 'nusa' => [28000, 2, 4], 'sumatra' => [38000, 3, 5],
            'kalimantan' => [42000, 3, 5], 'sulawesi' => [45000, 3, 6], 'east' => [85000, 5, 8],
        ]],
        'jne_yes' => ['JNE YES', [
            'bali' => [18000, 1, 1], 'java' => [38000, 1, 1], 'nusa' => [50000, 1, 2], 'sumatra' => [65000, 1, 2],
            'kalimantan' => [72000, 1, 2], 'sulawesi' => [75000, 1, 2],
        ]],
        'sicepat_reg' => ['SiCepat REG', [
            'bali' => [9000, 1, 2], 'java' => [20000, 2, 3], 'nusa' => [26000, 2, 4], 'sumatra' => [35000, 3, 5],
            'kalimantan' => [39000, 3, 6], 'sulawesi' => [41000, 3, 6], 'east' => [79000, 5, 9],
        ]],
        'sicepat_best' => ['SiCepat BEST', [
            'bali' => [16000, 1, 1], 'java' => [34000, 1, 1],
        ]],
    ];

    /** Same-day motorbike courier, only where it can reach from the roastery in a few hours. */
    public const SAME_DAY_CITIES = ['denpasar', 'badung', 'kuta', 'canggu', 'seminyak', 'kerobokan', 'ubud', 'gianyar', 'sanur', 'jimbaran', 'nusa dua', 'uluwatu', 'tabanan'];
    public const SAME_DAY_PRICE = 25000;
    public const SAME_DAY_MAX_KG = 10;

    /** Free regular delivery thresholds by region (subtotal incl. tax). */
    public const FREE_OVER = ['bali' => 300000, 'java' => 750000];

    public const PACKAGING_G = 150;

    public static function region(string $province): ?string
    {
        return self::REGIONS[strtoupper($province)] ?? null;
    }

    public static function billableKg(int $grams): int
    {
        return max(1, (int) ceil(($grams + self::PACKAGING_G) / 1000));
    }

    /**
     * @param int    $grams    total item weight
     * @param int    $subtotal cart subtotal in rupiah, for free-delivery thresholds
     * @param bool   $chilled  cart holds items that must stay cold (same-day only)
     * @return list<array{id:string,label:string,cost:int,days:array{int,int},free:bool}>
     */
    public static function quote(string $province, string $city, int $grams, int $subtotal, bool $chilled = false): array
    {
        $region = self::region($province);
        if (!$region) {
            return [];
        }
        $kg = self::billableKg($grams);
        $rates = [];

        $sameDay = $region === 'bali' && self::isSameDayCity($city) && $kg <= self::SAME_DAY_MAX_KG;
        if ($sameDay) {
            $rates[] = ['id' => 'gosend_sameday', 'label' => 'GoSend Same Day', 'cost' => self::SAME_DAY_PRICE, 'days' => [0, 0], 'free' => false];
        }
        if ($chilled) {
            return $rates; // cold brew can't sit in a courier hub overnight
        }

        foreach (self::SERVICES as $id => [$label, $table]) {
            if (!isset($table[$region])) {
                continue;
            }
            [$perKg, $min, $max] = $table[$region];
            $rates[] = ['id' => $id, 'label' => $label, 'cost' => $perKg * $kg, 'days' => [$min, $max], 'free' => false];
        }

        // Free delivery makes the cheapest regular service free; faster ones keep their price.
        if (isset(self::FREE_OVER[$region]) && $subtotal >= self::FREE_OVER[$region]) {
            $cheapest = null;
            foreach ($rates as $i => $r) {
                if (str_ends_with($r['id'], '_reg') && ($cheapest === null || $r['cost'] < $rates[$cheapest]['cost'])) {
                    $cheapest = $i;
                }
            }
            if ($cheapest !== null) {
                $rates[$cheapest]['cost'] = 0;
                $rates[$cheapest]['free'] = true;
            }
        }
        usort($rates, static fn($a, $b) => [$a['cost'], $a['days'][1]] <=> [$b['cost'], $b['days'][1]]);
        return $rates;
    }

    public static function isSameDayCity(string $city): bool
    {
        $city = strtolower(trim($city));
        foreach (self::SAME_DAY_CITIES as $c) {
            if ($city !== '' && str_contains($city, $c)) {
                return true;
            }
        }
        return false;
    }

    /** "1–2 days", "next day", "today". */
    public static function eta(array $days): string
    {
        [$min, $max] = $days;
        if ($max === 0) {
            return 'today';
        }
        if ($min === 1 && $max === 1) {
            return 'next day';
        }
        return $min === $max ? "{$min} days" : "{$min}–{$max} days";
    }
}
