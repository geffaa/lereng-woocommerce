<?php

namespace Lereng\Catalog;

use DateTimeImmutable;
use DateTimeZone;

/**
 * When an order gets roasted. We roast on Monday and Thursday; orders in by 10:00 (Bali time)
 * on a roast day make that day's batch, later ones the next roast day. Coffee ships the day
 * it's roasted. Pure, so it's tested with fixed dates.
 */
final class RoastSchedule
{
    public const ROAST_DAYS = [1, 4]; // ISO weekday: Monday, Thursday
    public const CUTOFF_HOUR = 10;
    public const TZ = 'Asia/Makassar';

    public static function nextRoast(DateTimeImmutable $orderedAt, ?string $notBefore = null): DateTimeImmutable
    {
        $local = $orderedAt->setTimezone(new DateTimeZone(self::TZ));
        $day = $local->setTime(0, 0);
        if (!in_array((int) $local->format('N'), self::ROAST_DAYS, true) || (int) $local->format('G') >= self::CUTOFF_HOUR) {
            $day = $day->modify('+1 day');
        }
        while (!in_array((int) $day->format('N'), self::ROAST_DAYS, true)) {
            $day = $day->modify('+1 day');
        }
        if ($notBefore) {
            $floor = new DateTimeImmutable($notBefore, new DateTimeZone(self::TZ));
            while ($day < $floor || !in_array((int) $day->format('N'), self::ROAST_DAYS, true)) {
                $day = $day->modify('+1 day');
            }
        }
        return $day;
    }
}
