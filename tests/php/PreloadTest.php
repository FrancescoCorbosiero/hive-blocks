<?php

// Hive → Velocità: what <head> asks the browser to fetch early.

use Hive\Core\Preload;

check(Preload::lines("https://a.test/hero.jpg\n\n# a note\n  https://a.test/font.woff2  \r\n"), ['https://a.test/hero.jpg', 'https://a.test/font.woff2'], 'lines without blanks and comments');
check(count(Preload::lines(str_repeat("https://a.test/x.jpg\n", 40))), 30, 'at most 30');
check(Preload::entry('https://a.test/hero.webp?v=2'), ['https://a.test/hero.webp?v=2', 'image'], 'the type from the extension');
check(Preload::entry('https://a.test/font|font'), ['https://a.test/font', 'font'], 'a type given');
check(Preload::entry('https://a.test/page'), ['https://a.test/page', null], 'no type: nothing to preload');
check(Preload::entry('https://a.test/x.jpg|bogus'), ['https://a.test/x.jpg', 'image'], 'an unknown type falls back to the extension');
check(Preload::attributes('https://a.test/hero.jpg', 'image'), ['rel' => 'preload', 'as' => 'image', 'href' => 'https://a.test/hero.jpg', 'fetchpriority' => 'high'], 'an image, high priority');
check(Preload::attributes('https://a.test/f.woff2', 'font'), ['rel' => 'preload', 'as' => 'font', 'href' => 'https://a.test/f.woff2', 'type' => 'font/woff2', 'crossorigin' => ''], 'a font, crossorigin');
check(Preload::rules(['/drop/'], []), ['prerender' => [['source' => 'list', 'urls' => ['/drop/']]]], 'prerender a page');
check(Preload::rules([], []), [], 'no rules');
