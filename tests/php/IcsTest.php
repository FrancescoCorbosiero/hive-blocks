<?php

// A drop's reminder: the .ics file and the Google Calendar link.

use Hive\Core\Ics;

$hive_event = [
    'uid'   => 'drop-42@resellpiacenza.shop',
    'ts'    => 1792828800, // 2026-10-24 08:00:00 UTC (10:00 in Italy)
    'title' => 'Drop: Jordan 4 "Bred", Reimagined; 2026',
    'url'   => 'https://resellpiacenza.shop/prodotto/jordan-4/',
];
$hive_ics = Ics::calendar($hive_event, 1792000000);

check(str_starts_with($hive_ics, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"), true, 'a calendar, CRLF line endings');
check(str_contains($hive_ics, "DTSTART:20261024T080000Z\r\n"), true, 'starts at the release, in UTC');
check(str_contains($hive_ics, "DTEND:20261024T083000Z\r\n"), true, 'lasts 30 minutes');
check(str_contains($hive_ics, 'SUMMARY:Drop: Jordan 4 "Bred"\, Reimagined\; 2026'), true, 'commas and semicolons escaped');
check(str_contains($hive_ics, "TRIGGER:-PT15M\r\n"), true, 'a reminder 15 minutes before');
check(str_ends_with($hive_ics, "END:VCALENDAR\r\n"), true, 'ends the calendar');
check(str_contains(Ics::calendar($hive_event + ['alarm' => 0], 0), 'VALARM'), false, 'no alarm when asked for none');
check(Ics::text("a\nb;c,d\\"), 'a\nb\;c\,d\\\\', 'newlines, semicolons, commas and backslashes escaped');

$hive_long = Ics::fold('SUMMARY:' . str_repeat('è', 60));
$hive_short_parts = true;
foreach (explode("\r\n", $hive_long) as $hive_part) {
    $hive_short_parts = $hive_short_parts && strlen($hive_part) <= 75 && false !== mb_check_encoding($hive_part, 'UTF-8');
}
check($hive_short_parts && str_contains($hive_long, "\r\n "), true, 'long lines folded under 75 octets, never inside a character');

$hive_google = Ics::google_url($hive_event);
check(str_starts_with($hive_google, 'https://calendar.google.com/calendar/render?action=TEMPLATE'), true, 'a Google Calendar template link');
check(str_contains($hive_google, 'dates=20261024T080000Z%2F20261024T083000Z'), true, 'with the same times');
check(str_contains($hive_google, 'text=Drop%3A%20Jordan%204'), true, 'and the title');
