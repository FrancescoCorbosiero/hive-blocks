<?php

use Hive\Core\OpeningHours;

check(OpeningHours::day_index('Lunedì'), 1, 'Monday');
check(OpeningHours::day_index('  domenica'), 0, 'Sunday, lower case, leading spaces');
check(OpeningHours::day_index('MER'), 3, 'abbreviated, upper case');
check(OpeningHours::day_index('Sabato e domenica'), 6, 'read from the first three letters');
check(OpeningHours::day_index('Festivi'), null, 'not a day');

check(OpeningHours::is_closed('Chiuso'), true, 'closed');
check(OpeningHours::is_closed(' chiusa per ferie'), true, 'closed, any case, any ending');
check(OpeningHours::is_closed('10.30 - 13.00'), false, 'open');

check(
    OpeningHours::rows([['day' => ' Lunedì ', 'time' => ' 9 - 13 '], ['day' => '', 'time' => '9 - 13'], 'junk', ['time' => 'x']]),
    [['day' => 'Lunedì', 'time' => '9 - 13']],
    'rows are trimmed; rows without a day are dropped'
);
check(OpeningHours::rows('nope'), [], 'not a list: no rows');
check(OpeningHours::rows([['day' => 'Domenica']]), [['day' => 'Domenica', 'time' => '']], 'a day without a time keeps an empty time');

check(
    OpeningHours::closed_dates("24/12/2026\n2026-12-31, 1/1/2027; 31/02/2026 2026-13-01 24/12/2026"),
    ['2026-12-24', '2026-12-31', '2027-01-01'],
    'both formats; impossible dates dropped; each date once'
);
check(OpeningHours::closed_dates(''), [], 'no closed dates');
