<?php

// The brand marquee's arithmetic: copies enough to cover a wide screen, how long a lap takes.

use Hive\Core\Marquee;

check(Marquee::copies(0), 0, 'nothing to show: no copies');
check(Marquee::copies(1), 10, 'one brand fills the track ten times');
check(Marquee::copies(3), 4, 'three brands: four times (12 items)');
check(Marquee::copies(10), 1, 'ten brands are enough once');
check(Marquee::copies(25), 1, 'a long list once');
check(Marquee::copies(4, 6), 2, 'another minimum');

check(Marquee::duration(10, 50), 40, '10 items × 200px at 50px/s: 40 s');
check(Marquee::duration(12, 100), 24, 'faster: shorter');
check(Marquee::duration(10, 0), 2000, 'a speed under 1 counts as 1');
check(Marquee::duration(10, 500), 10, 'a speed over 200 counts as 200');
check(Marquee::duration(1, 200), 5, 'never under 5 s');
