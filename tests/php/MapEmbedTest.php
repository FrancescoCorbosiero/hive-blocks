<?php

// A map pasted in a block: only Google Maps' embed addresses come out, as https.

use Hive\Core\MapEmbed;

$hive_pb = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2819.1!2d9.6884327!3d45.0482437!5e0!3m2!1sit!2sit!4v1';

check(
    MapEmbed::src('<iframe src="' . str_replace('&', '&amp;', $hive_pb) . '" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>'),
    $hive_pb,
    'the code Google Maps gives: its src, entities decoded'
);
check(MapEmbed::src("  $hive_pb\n"), $hive_pb, 'just the URL, with spaces around it');
check(MapEmbed::src("<iframe width='600' src='$hive_pb'></iframe>"), $hive_pb, 'single quotes');
check(MapEmbed::src('<IFRAME SRC=' . $hive_pb . '></IFRAME>'), $hive_pb, 'no quotes, upper case');
check(MapEmbed::src('<iframe data-src="https://evil.example/x" src="' . $hive_pb . '"></iframe>'), $hive_pb, 'data-src is not src');
check(
    MapEmbed::src('<iframe src="https://maps.google.it/maps?q=Piacenza&amp;output=embed"></iframe>'),
    'https://maps.google.it/maps?q=Piacenza&output=embed',
    'the old maps.google.it form with output=embed'
);
check(
    MapEmbed::src('https://www.google.com/maps/embed/v1/place?key=K&q=Piacenza'),
    'https://www.google.com/maps/embed/v1/place?key=K&q=Piacenza',
    'the Maps Embed API'
);
check(MapEmbed::src('//www.google.com/maps/embed?pb=1'), 'https://www.google.com/maps/embed?pb=1', 'protocol-relative → https');
check(MapEmbed::src('http://www.google.co.uk/maps/embed?pb=1'), 'https://www.google.co.uk/maps/embed?pb=1', 'http → https, a two-part country domain');
check(MapEmbed::src('https://google.com/maps/embed?pb=1'), 'https://google.com/maps/embed?pb=1', 'without www.');

check(MapEmbed::src(''), null, 'nothing pasted');
check(MapEmbed::src('<iframe src="https://www.youtube.com/embed/xyz"></iframe>'), null, 'another site');
check(MapEmbed::src('https://www.google.com.evil.example/maps/embed?pb=1'), null, 'a look-alike host');
check(MapEmbed::src('https://evil.example/?https://www.google.com/maps/embed'), null, 'Google only inside the query');
check(MapEmbed::src('https://www.google.com/search?q=maps'), null, 'Google, but not a map');
check(MapEmbed::src('https://www.google.com/maps/place/Piacenza'), null, 'a map page Google won’t let a site frame');
check(MapEmbed::src('https://www.google.com/maps?q=Piacenza'), null, '/maps without output=embed');
check(MapEmbed::src('javascript:alert(1)//www.google.com/maps/embed'), null, 'not a web address');
check(MapEmbed::src('https://user:pass@www.google.com/maps/embed?pb=1'), null, 'credentials in the URL');
check(MapEmbed::src('https://www.google.com:8443/maps/embed?pb=1'), null, 'a port');
check(MapEmbed::src('https://www.google.com/maps/embed?pb=1" onload="alert(1)'), null, 'quotes in the URL');
check(MapEmbed::src('<iframe src="https://www.google.com/maps/embed?pb=&quot;x"></iframe>'), null, 'an encoded quote in the src');
check(MapEmbed::src('<iframe width="600"></iframe>'), null, 'an iframe without src');
check(MapEmbed::src('<div>no map</div>'), null, 'no iframe, not a URL');

check(
    MapEmbed::search_url(' Stradone Farnese 3 - 29121 - Piacenza (PC) '),
    'https://www.google.com/maps/search/?api=1&query=Stradone%20Farnese%203%20-%2029121%20-%20Piacenza%20%28PC%29',
    'searching for the address'
);
check(
    MapEmbed::directions_url('Via Roma 1, Piacenza'),
    'https://www.google.com/maps/dir/?api=1&destination=Via%20Roma%201%2C%20Piacenza',
    'the way there'
);
