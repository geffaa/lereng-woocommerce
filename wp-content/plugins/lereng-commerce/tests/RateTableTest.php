<?php

namespace Lereng\Tests;

use Lereng\Shipping\RateTable;
use PHPUnit\Framework\TestCase;

final class RateTableTest extends TestCase
{
    private static function ids(array $rates): array
    {
        return array_column($rates, 'id');
    }

    public function testBillableWeightRoundsUpAndIncludesPackaging(): void
    {
        $this->assertSame(1, RateTable::billableKg(240));   // one 200 g bag
        $this->assertSame(1, RateTable::billableKg(850));   // 850 + 150 = exactly 1 kg
        $this->assertSame(2, RateTable::billableKg(860));
        $this->assertSame(5, RateTable::billableKg(4300));
    }

    public function testBaliCanggu_getsSameDayAndCouriers_cheapestFirst(): void
    {
        $rates = RateTable::quote('BA', 'Canggu', 500, 150000);
        $this->assertSame('sicepat_reg', $rates[0]['id']);
        $this->assertContains('gosend_sameday', self::ids($rates));
        $this->assertSame(9000, $rates[0]['cost']);
    }

    public function testFreeDeliveryInBaliMakesOnlyTheCheapestRegularServiceFree(): void
    {
        $rates = RateTable::quote('BA', 'Denpasar', 500, 300000);
        $free = array_values(array_filter($rates, fn($r) => $r['free']));
        $this->assertCount(1, $free);
        $this->assertSame('sicepat_reg', $free[0]['id']);
        $this->assertSame(0, $free[0]['cost']);
        $gosend = array_values(array_filter($rates, fn($r) => $r['id'] === 'gosend_sameday'))[0];
        $this->assertSame(RateTable::SAME_DAY_PRICE, $gosend['cost']);
    }

    public function testJavaThresholdIsHigher(): void
    {
        $this->assertFalse(array_filter(RateTable::quote('JB', 'Bandung', 500, 500000), fn($r) => $r['free']) !== []);
        $this->assertNotEmpty(array_filter(RateTable::quote('JB', 'Bandung', 500, 750000), fn($r) => $r['free']));
    }

    public function testPricesScaleWithWeightAndDistance(): void
    {
        $papua = RateTable::quote('PA', 'Jayapura', 2900, 100000);
        $jne = array_values(array_filter($papua, fn($r) => $r['id'] === 'jne_reg'))[0];
        $this->assertSame(85000 * 4, $jne['cost']); // 2.9 kg + packaging = 4 kg billable
        $this->assertNotContains('jne_yes', self::ids($papua)); // no next-day service to Papua
        $this->assertNotContains('gosend_sameday', self::ids($papua));
    }

    public function testChilledItemsOnlyGoSameDay(): void
    {
        $this->assertSame(['gosend_sameday'], self::ids(RateTable::quote('BA', 'Ubud', 1100, 155000, true)));
        $this->assertSame([], RateTable::quote('BA', 'Singaraja', 1100, 155000, true));
        $this->assertSame([], RateTable::quote('JK', 'Jakarta Selatan', 1100, 155000, true));
    }

    public function testUnknownProvinceHasNoRates(): void
    {
        $this->assertSame([], RateTable::quote('XX', 'Nowhere', 500, 100000));
    }

    public function testEtaWording(): void
    {
        $this->assertSame('today', RateTable::eta([0, 0]));
        $this->assertSame('next day', RateTable::eta([1, 1]));
        $this->assertSame('2–3 days', RateTable::eta([2, 3]));
    }
}
