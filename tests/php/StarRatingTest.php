<?php

// The Trustpilot block's stars and numbers.

use Hive\Core\StarRating;

check(StarRating::stars(4.8), ['full', 'full', 'full', 'full', 'half'], '4.8: rounded down to the half star');
check(StarRating::stars(4.4), ['full', 'full', 'full', 'full', 'empty'], '4.4: four stars');
check(StarRating::stars('4.5'), ['full', 'full', 'full', 'full', 'half'], 'a string, exactly a half');
check(StarRating::stars(5), ['full', 'full', 'full', 'full', 'full'], 'five');
check(StarRating::stars(7), ['full', 'full', 'full', 'full', 'full'], 'above five: five');
check(StarRating::stars(-1), ['empty', 'empty', 'empty', 'empty', 'empty'], 'below zero: none');
check(StarRating::stars('molto'), ['empty', 'empty', 'empty', 'empty', 'empty'], 'not a number: none');

check(StarRating::score(4.8), '4,8', 'a decimal comma');
check(StarRating::score(5), '5,0', 'one decimal always');
check(StarRating::score(4.86), '4,9', 'rounded to one decimal');

check(StarRating::count(250), '250', 'a small count');
check(StarRating::count('1250'), '1.250', 'thousands with a dot');
check(StarRating::count(-3), '0', 'never negative');
check(StarRating::count('tante'), '0', 'not a number');
