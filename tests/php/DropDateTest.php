<?php

// A release date typed in the drop countdown block.

use Hive\Core\DropDate;

$rome = new DateTimeZone('Europe/Rome');

check(DropDate::parse('2026-11-07T10:00', $rome), gmmktime(9, 0, 0, 11, 7, 2026), "the editor's field, in the store's time (CET)");
check(DropDate::parse('2026-07-07T10:00', $rome), gmmktime(8, 0, 0, 7, 7, 2026), 'summer time (CEST)');
check(DropDate::parse('2026-11-07T10:00:30', $rome), gmmktime(9, 0, 30, 11, 7, 2026), 'with seconds');
check(DropDate::parse(' 2026-11-07 10:00 ', $rome), gmmktime(9, 0, 0, 11, 7, 2026), 'a space instead of the T, spaces around');
check(DropDate::parse('2026-11-07', $rome), gmmktime(23, 0, 0, 11, 6, 2026), 'just the day: midnight in Italy');
check(DropDate::parse('2026-11-07T10:00:00Z', $rome), gmmktime(10, 0, 0, 11, 7, 2026), 'Z is UTC');
check(DropDate::parse('2026-11-07T10:00:00+02:00', $rome), gmmktime(8, 0, 0, 11, 7, 2026), 'an offset wins over the store');
check(DropDate::parse('2026-11-07T10:00+0200', $rome), gmmktime(8, 0, 0, 11, 7, 2026), 'an offset without the colon');

check(DropDate::parse('', $rome), 0, 'empty');
check(DropDate::parse('tomorrow', $rome), 0, 'no relative words');
check(DropDate::parse('+1 week', $rome), 0, 'no relative times');
check(DropDate::parse('07/11/2026 10:00', $rome), 0, 'not the ISO form');
check(DropDate::parse('2026-02-30T10:00', $rome), 0, 'a day that does not exist');
check(DropDate::parse('2026-11-07T24:00', $rome), 0, 'an hour that does not exist');
check(DropDate::parse('2026-11-07T10:60', $rome), 0, 'a minute that does not exist');
