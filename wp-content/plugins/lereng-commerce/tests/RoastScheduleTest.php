<?php

namespace Lereng\Tests;

use DateTimeImmutable;
use Lereng\Catalog\RoastSchedule;
use PHPUnit\Framework\TestCase;

final class RoastScheduleTest extends TestCase
{
    private static function at(string $local): DateTimeImmutable
    {
        return new DateTimeImmutable($local, new \DateTimeZone('Asia/Makassar'));
    }

    public function testMondayMorningMakesMondaysRoast(): void
    {
        $this->assertSame('2026-10-26', RoastSchedule::nextRoast(self::at('2026-10-26 09:59'))->format('Y-m-d'));
    }

    public function testAfterCutoffWaitsForThursday(): void
    {
        $this->assertSame('2026-10-29', RoastSchedule::nextRoast(self::at('2026-10-26 10:00'))->format('Y-m-d'));
    }

    public function testWeekendOrdersRoastOnMonday(): void
    {
        $this->assertSame('2026-11-02', RoastSchedule::nextRoast(self::at('2026-10-31 15:00'))->format('Y-m-d'));
    }

    public function testServerTimeInUtcIsReadAsBaliTime(): void
    {
        // 01:30 UTC on Monday is 09:30 in Bali: still before the cutoff.
        $utc = new DateTimeImmutable('2026-10-26 01:30', new \DateTimeZone('UTC'));
        $this->assertSame('2026-10-26', RoastSchedule::nextRoast($utc)->format('Y-m-d'));
    }

    public function testPreOrdersWaitForTheirFirstRoast(): void
    {
        $this->assertSame('2026-11-23', RoastSchedule::nextRoast(self::at('2026-10-26 08:00'), '2026-11-20')->format('Y-m-d'));
    }
}
