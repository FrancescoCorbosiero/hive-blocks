<?php

// Category and brand pages follow their homepage rail (golden-hive-blocks' ghb_hub_archive_rail_map).

use Hive\Core\ArchivePins;

check(
    ArchivePins::map([
        ['category' => 'saldi', 'pin' => '12,5', 'exclude' => '4'],
        ['category' => 'saldi', 'pin' => '99'],
        ['brand' => 'nike', 'exclude' => '7'],
        ['category' => 'a,b', 'pin' => '1'],
        ['category' => 'sneakers'],
        ['type' => 'recent', 'pin' => '3'],
    ]),
    [
        'category:saldi' => ['pin' => [12, 5], 'exclude' => [4]],
        'brand:nike'     => ['pin' => [], 'exclude' => [7]],
    ],
    'one-term rails with pins or exclusions; the first rail for a term wins'
);
check(ArchivePins::map([['category' => 'x', 'brand' => 'y', 'pin' => '1']]), ['category:x' => ['pin' => [1], 'exclude' => []]], 'a category rail with a brand too counts as the category');
check(ArchivePins::map([]), [], 'no rails');
