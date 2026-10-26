<?php

namespace Lereng\Tests;

use Lereng\Subscriptions\Schedule;
use PHPUnit\Framework\TestCase;

final class ScheduleTest extends TestCase
{
    public function testDeliveriesLandOnRoastDays(): void
    {
        $this->assertSame('2026-10-29', Schedule::roastDayOnOrAfter('2026-10-27')); // Tue → Thu
        $this->assertSame('2026-10-26', Schedule::roastDayOnOrAfter('2026-10-26')); // Monday stays
        $this->assertSame('2026-11-02', Schedule::roastDayOnOrAfter('2026-10-30')); // Fri → Mon
    }

    public function testNextDeliveryIsIntervalWeeksLaterOnARoastDay(): void
    {
        $this->assertSame('2026-11-09', Schedule::next('2026-10-26', 2)); // Monday + 2 weeks
        $this->assertSame('2026-11-05', Schedule::next('2026-10-29', 1)); // Thursday + 1 week
        $this->assertSame('2026-11-23', Schedule::next('2026-10-26', 4));
        $this->assertSame('2026-11-09', Schedule::next('2026-10-26', 3)); // unsupported interval → 2
    }

    public function testRenewalIsCreatedTwoDaysBeforeRoastDay(): void
    {
        $this->assertFalse(Schedule::renewalDue('2026-11-09', '2026-11-06'));
        $this->assertTrue(Schedule::renewalDue('2026-11-09', '2026-11-07'));
        $this->assertTrue(Schedule::renewalDue('2026-11-09', '2026-11-12')); // overdue still renews
    }

    public function testPausesAreBoundedAndResumeOnARoastDay(): void
    {
        $this->assertSame('2026-12-03', Schedule::resumeDate('2026-12-01', '2026-10-27'));
        $this->assertNull(Schedule::resumeDate('2026-10-27', '2026-10-27')); // must be in the future
        $this->assertNull(Schedule::resumeDate('2027-02-01', '2026-10-27')); // more than 90 days
        $this->assertNull(Schedule::resumeDate('next week', '2026-10-27'));
    }

    public function testSubscriptionPriceAndMonthlyValue(): void
    {
        $this->assertSame(140000, Schedule::price(155000)); // 139.500 → 140.000
        $this->assertSame(113000, Schedule::price(125000));
        // 140k every 2 weeks ≈ 303k a month
        $this->assertSame(303333, Schedule::monthly(140000, 1, 2));
        $this->assertSame(606667, Schedule::monthly(140000, 1, 1));
    }
}
