<?php
/**
 * A drop's reminder: the .ics file (Apple Calendar, Outlook, phones) and the
 * Google Calendar link, both at the release time in UTC, lasting 30 minutes,
 * with an alarm 15 minutes before.
 *
 * Pure logic: no WordPress calls. Tested in tests/php/IcsTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class Ics
{
    /**
     * @param array{uid: string, ts: int, title: string, url?: string, minutes?: int, alarm?: int} $event
     * @param int $stamp When the file is made (DTSTAMP).
     */
    public static function calendar(array $event, int $stamp): string
    {
        $ts      = (int) $event['ts'];
        $minutes = max(5, (int) ($event['minutes'] ?? 30));
        $alarm   = max(0, (int) ($event['alarm'] ?? 15));
        $url     = (string) ($event['url'] ?? '');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Hive Blocks//Drop//IT',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . self::text((string) $event['uid']),
            'DTSTAMP:' . gmdate('Ymd\THis\Z', $stamp),
            'DTSTART:' . gmdate('Ymd\THis\Z', $ts),
            'DTEND:' . gmdate('Ymd\THis\Z', $ts + $minutes * 60),
            'SUMMARY:' . self::text((string) $event['title']),
        ];
        if ('' !== $url) {
            $lines[] = 'DESCRIPTION:' . self::text($url);
            $lines[] = 'URL:' . $url;
        }
        if ($alarm > 0) {
            $lines[] = 'BEGIN:VALARM';
            $lines[] = 'ACTION:DISPLAY';
            $lines[] = 'DESCRIPTION:' . self::text((string) $event['title']);
            $lines[] = 'TRIGGER:-PT' . $alarm . 'M';
            $lines[] = 'END:VALARM';
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    /** The same event as a Google Calendar template link. */
    public static function google_url(array $event): string
    {
        $ts      = (int) $event['ts'];
        $minutes = max(5, (int) ($event['minutes'] ?? 30));
        return 'https://calendar.google.com/calendar/render?' . http_build_query(
            [
                'action'  => 'TEMPLATE',
                'text'    => (string) $event['title'],
                'dates'   => gmdate('Ymd\THis\Z', $ts) . '/' . gmdate('Ymd\THis\Z', $ts + $minutes * 60),
                'details' => (string) ($event['url'] ?? ''),
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );
    }

    /** Text as iCalendar wants it: backslashes, commas, semicolons and newlines escaped. */
    public static function text(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\r", "\n"], ['\\\\', '\;', '\\,', '\\n', '\\n', '\\n'], $text);
    }

    /** Lines over 75 octets folded (CRLF + space), never inside a character. */
    public static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out   = '';
        $chunk = '';
        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if (strlen($chunk . $char) > ('' === $out ? 75 : 74)) {
                $out  .= $chunk . "\r\n ";
                $chunk = '';
            }
            $chunk .= $char;
        }
        return $out . $chunk;
    }
}
