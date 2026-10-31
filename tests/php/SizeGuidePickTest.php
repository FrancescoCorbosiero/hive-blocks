<?php

// Which size guide a product shows.

use Hive\Core\SizeGuidePick;

// Categories: Scarpe (1) > Sneakers (2) > Running (3); Abbigliamento (4). Brands: Nike (10), Adidas (11).
$hive_guides = [
    100 => ['cats' => [1]],                       // Scarpe
    101 => ['cats' => [2]],                       // Sneakers
    102 => ['brands' => [10]],                    // Nike
    103 => ['cats' => [2], 'brands' => [11]],     // Adidas sneakers
    104 => ['cats' => [4]],                       // Abbigliamento
    105 => ['default' => true],                   // everything else
    106 => [],                                    // only where a product picks it
];
$hive_running = [3 => 2, 2 => 1, 1 => 0];        // a running shoe: Running and its ancestors

check(SizeGuidePick::pick($hive_guides, $hive_running, []), 101, 'the deepest category with a guide');
check(SizeGuidePick::pick($hive_guides, [1 => 0], []), 100, 'a top category');
check(SizeGuidePick::pick($hive_guides, $hive_running, [11]), 103, 'category and brand together beat either alone');
check(SizeGuidePick::pick($hive_guides, $hive_running, [10]), 101, 'one condition each: the category guide wins over the brand one');
check(SizeGuidePick::pick($hive_guides, [], [10]), 102, 'a brand guide');
check(SizeGuidePick::pick($hive_guides, [4 => 0], [10]), 104, 'a category guide over a brand guide');
check(SizeGuidePick::pick($hive_guides, [], [12]), 105, 'nothing matches: the default guide');
check(SizeGuidePick::pick([106 => []], [1 => 0], []), null, 'no default, no match');
check(SizeGuidePick::pick($hive_guides, $hive_running, [], '106'), 106, 'the product picked a guide');
check(SizeGuidePick::pick($hive_guides, $hive_running, [], 'none'), null, 'the product wants none');
check(SizeGuidePick::pick($hive_guides, $hive_running, [], '999'), 101, 'a picked guide that is gone: automatic');
check(SizeGuidePick::pick([200 => ['cats' => [2]], 201 => ['cats' => [2]]], $hive_running, []), 200, 'a tie: the earlier guide');
