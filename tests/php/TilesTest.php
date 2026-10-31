<?php

// Slider categorie's own tiles: a picture, a name, a link each.

use Hive\Core\Tiles;

check(
    Tiles::from([
        ['name' => ' Asics ', 'image' => 'https://a.test/asics.jpeg', 'url' => '/marchio/asics'],
        ['name' => 'Nocta', 'imageId' => '42', 'url' => '/marchio/nike/nike-nocta/'],
        ['name' => '<b>59FIFTY</b>', 'url' => ''],
        ['name' => '', 'image' => '', 'url' => '/vuoto'],
        'x',
    ]),
    [
        ['name' => 'Asics', 'url' => '/marchio/asics', 'image' => 'https://a.test/asics.jpeg', 'imageId' => 0],
        ['name' => 'Nocta', 'url' => '/marchio/nike/nike-nocta/', 'image' => '', 'imageId' => 42],
        ['name' => '59FIFTY', 'url' => '', 'image' => '', 'imageId' => 0],
    ],
    'tiles: trimmed, tags out, a picture by URL or id, nothing to show left out'
);
check(Tiles::from(null), [], 'no tiles');
check(count(Tiles::from(array_fill(0, 30, ['name' => 'x']))), Tiles::MAX, 'at most MAX tiles');
