<?php
/**
 * Opening hours as the store writes them in the Orari block: day labels in
 * Italian ("Lunedì"), times like "10.30 - 13.00 | 15.30 - 19.30" or
 * "Chiuso", closed dates as 24/12/2026 or 2026-12-24. The server side of
 * blocks/store-hours (the browser side is blocks/store-hours/hours.js).
 * Pure: tested by tests/php/OpeningHoursTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class OpeningHours
{
    /** A day's first three letters → its number as JavaScript's Date.getDay() counts (0 Sunday … 6 Saturday). */
    private const DAYS = ['dom' => 0, 'lun' => 1, 'mar' => 2, 'mer' => 3, 'gio' => 4, 'ven' => 5, 'sab' => 6];

    /** 'Lunedì' → 1 … 'Domenica' → 0; null when the label isn't a day. */
    public static function day_index(string $label): ?int
    {
        // Every Italian day name starts with three ASCII letters, so bytes are enough.
        return self::DAYS[strtolower(substr(ltrim($label), 0, 3))] ?? null;
    }

    /** "Chiuso" (or "chiusa", any case). */
    public static function is_closed(string $time): bool
    {
        return 1 === preg_match('/^\s*chius/iu', $time);
    }

    /**
     * The rows as the page prints them and the browser reads them:
     * [['day' => 'Lunedì', 'time' => '10.30 - 13.00'], …]. Rows without a
     * day are dropped.
     *
     * @return list<array{day: string, time: string}>
     */
    public static function rows(mixed $hours): array
    {
        $rows = [];
        foreach (is_array($hours) ? $hours : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $day = trim((string) ($row['day'] ?? ''));
            if ('' !== $day) {
                $rows[] = ['day' => $day, 'time' => trim((string) ($row['time'] ?? ''))];
            }
        }
        return $rows;
    }

    /**
     * "24/12/2026, 2026-12-31" → ['2026-12-24', '2026-12-31']: real dates
     * only, each once, in the order written. Separated by new lines, commas,
     * semicolons or spaces.
     *
     * @return list<string>
     */
    public static function closed_dates(string $text): array
    {
        $dates = [];
        foreach (preg_split('/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $raw) {
            if (1 === preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                $dates[] = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
            } elseif (1 === preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $raw, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                $dates[] = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }
        }
        return array_values(array_unique($dates));
    }
}
