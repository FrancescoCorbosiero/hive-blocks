<?php

use Hive\Core\Color;

check(Color::hex('#ABC'), '#aabbcc', 'three digits expand, lower case');
check(Color::hex(' #721124 '), '#721124', 'surrounding spaces are trimmed');
check(Color::hex('721124'), null, 'the # is required');
check(Color::hex('#72112'), null, 'five digits are not a colour');
check(Color::hex('red'), null, 'colour names are not hex');
check(Color::hex('#000;}body{display:none'), null, 'nothing after the six digits');

check(round(Color::contrast('#000000', '#ffffff'), 2), 21.0, 'black on white is 21:1');
check(round(Color::contrast('#777777', '#777777'), 2), 1.0, 'a colour on itself is 1:1');

check(Color::readable_on('#721124'), '#ffffff', 'white text on the store burgundy');
check(Color::readable_on('#f5d300'), '#0a0a0a', 'near-black text on yellow');
check(Color::readable_on('#ffffff'), '#0a0a0a', 'near-black text on white');
check(Color::readable_on('#0a0a0a'), '#ffffff', 'white text on near-black');
