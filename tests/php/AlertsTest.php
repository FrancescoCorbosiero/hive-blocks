<?php

// Who hears about an arrival: size alerts and waitlist, one email per person.

use Hive\Core\Alerts;

$hive_live = [
    10 => ['keys' => ['42', '43'], 't' => 100],
    11 => ['keys' => ['42'], 't' => 300],
    12 => ['keys' => ['*'], 't' => 200],
];
$hive_brands_of = [10 => ['nike'], 11 => ['adidas'], 12 => []];

check(Alerts::pick($hive_live, ['42'], [], $hive_brands_of, [], []), [11 => ['42'], 10 => ['42']], 'size 42, any brand, newest first');
check(Alerts::pick($hive_live, ['42', '43'], ['nike'], $hive_brands_of, [], []), [10 => ['42', '43']], 'only the chosen brands');
check(Alerts::pick($hive_live, ['42'], [], $hive_brands_of, [], ['11|42']), [10 => ['42']], 'never the same product and size twice');
check(Alerts::pick($hive_live, [], [], $hive_brands_of, ['10|43', '12|*'], []), [12 => ['*'], 10 => ['43']], 'the waitlist: that product in that size, or a product without sizes');
check(Alerts::pick($hive_live, ['42'], ['puma'], $hive_brands_of, ['10|43'], []), [10 => ['43']], 'a waitlist entry ignores the brand filter');
check(Alerts::pick($hive_live, [], [], $hive_brands_of, ['10|44'], []), [], 'a size that didn’t arrive');
check(Alerts::pick([], ['42'], [], [], [], []), [], 'nothing arrived');

check(Alerts::merge_sizes(['40', '41'], ['42']), ['42', '40', '41'], 'new sizes first');
check(Alerts::merge_sizes(['40', '41', '43'], ['42', '40']), ['42', '40', '41'], 'at most three, no repeats');
check(Alerts::merge_sizes([''], []), [], 'unset meta is never a size');

check(Alerts::waits(['10|42'], ['11|*', 'bad', '10|42']), ['10|42', '11|*'], 'entries added once; malformed ones dropped');
check(Alerts::waits(['10|42', '11|*'], [], ['10|42']), ['11|*'], 'entries told are removed');
check(count(Alerts::waits(array_map(static fn($i) => $i . '|42', range(1, 60)), [])), 50, 'the newest 50 kept');
check(Alerts::is_wait('0|42'), false, 'no product 0');

check(Alerts::labels(['42']), '42', 'one size');
check(Alerts::labels(['42', '43']), '42 e 43', 'two sizes');
check(Alerts::labels(['40', '42', '43']), '40, 42 e 43', 'three sizes');
check(Alerts::labels([]), '', 'none');
