<?php
/**
 * A release date typed in a block (Conto alla rovescia del drop), as a UTC
 * timestamp: "2026-12-31T10:00" from the editor's date field, with seconds,
 * a space instead of the T, or just the day (midnight). Without an offset
 * it's the store's time; "Z" or "+01:00" say otherwise. Words like
 * "tomorrow" are refused: the page would change by itself from one day to
 * the next, and a cached page would disagree with a fresh one.
 *
 * Pure logic: no WordPress calls. Tested in tests/php/DropDateTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class DropDate
{
    private const PATTERN = '/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2}))?)?\s*(Z|[+-]\d{2}:?\d{2})?$/i';

    /** The timestamp, or 0 when the text isn't such a date. */
    public static function parse(string $raw, \DateTimeZone $store): int
    {
        if (1 !== preg_match(self::PATTERN, trim($raw), $m)) {
            return 0;
        }
        [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        $hour   = (int) ($m[4] ?? 0);
        $minute = (int) ($m[5] ?? 0);
        $second = (int) ($m[6] ?? 0);
        if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            return 0;
        }

        $zone = $store;
        $tz   = strtoupper((string) ($m[7] ?? ''));
        if ('' !== $tz) {
            $zone = new \DateTimeZone('Z' === $tz ? 'UTC' : (strlen($tz) === 5 ? substr($tz, 0, 3) . ':' . substr($tz, 3) : $tz));
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second), $zone);
        return $date ? $date->getTimestamp() : 0;
    }
}
