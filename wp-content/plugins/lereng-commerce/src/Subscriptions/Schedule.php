<?php

namespace Lereng\Subscriptions;

use DateTimeImmutable;
use Lereng\Catalog\RoastSchedule;

/**
 * Subscription date and money rules. Pure, so every rule is unit-tested.
 *  - deliveries land on roast days (Monday/Thursday), every 1, 2 or 4 weeks
 *  - a renewal order is created LEAD_DAYS before its roast day, so there's time to pay
 *  - a pause lasts at most MAX_PAUSE_DAYS and resumes on the first roast day after it ends
 */
final class Schedule
{
    public const INTERVALS = [1, 2, 4];
    public const LEAD_DAYS = 2;
    public const MAX_PAUSE_DAYS = 90;
    public const DISCOUNT = 10.0;

    /** First roast day on or after a date (date-only, Bali time). */
    public static function roastDayOnOrAfter(string $date): string
    {
        // nextRoast with a 00:00 timestamp treats the day itself as eligible.
        return RoastSchedule::nextRoast(new DateTimeImmutable($date . ' 00:00', new \DateTimeZone(RoastSchedule::TZ)))->format('Y-m-d');
    }

    /** The delivery after $from: interval weeks later, moved to the next roast day if needed. */
    public static function next(string $from, int $intervalWeeks): string
    {
        $weeks = in_array($intervalWeeks, self::INTERVALS, true) ? $intervalWeeks : 2;
        return self::roastDayOnOrAfter((new DateTimeImmutable($from))->modify("+{$weeks} weeks")->format('Y-m-d'));
    }

    /** True when the renewal order for a delivery on $nextDate should be created today. */
    public static function renewalDue(string $nextDate, string $today): bool
    {
        return (new DateTimeImmutable($nextDate))->modify('-' . self::LEAD_DAYS . ' days')->format('Y-m-d') <= $today;
    }

    /** Where a skipped delivery moves to. */
    public static function skip(string $nextDate, int $intervalWeeks): string
    {
        return self::next($nextDate, $intervalWeeks);
    }

    /** Validates a pause end date and returns the delivery date it resumes on, or null if not allowed. */
    public static function resumeDate(string $until, string $today): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) || $until <= $today) {
            return null;
        }
        $max = (new DateTimeImmutable($today))->modify('+' . self::MAX_PAUSE_DAYS . ' days')->format('Y-m-d');
        if ($until > $max) {
            return null;
        }
        return self::roastDayOnOrAfter($until);
    }

    /** Price per unit for a subscription: shelf price less the discount, rounded to Rp 1.000. */
    public static function price(int $shelfPrice, float $discountPct = self::DISCOUNT): int
    {
        return (int) (round($shelfPrice * (1 - $discountPct / 100) / 1000) * 1000);
    }

    /** Monthly recurring revenue of one subscription (average month = 52/12 weeks). */
    public static function monthly(int $unitPrice, int $qty, int $intervalWeeks): int
    {
        return (int) round($unitPrice * $qty * (52 / 12) / max(1, $intervalWeeks));
    }
}
