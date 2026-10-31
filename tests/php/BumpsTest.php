<?php

// Which order bump the checkout offers.

use Hive\Core\Bumps;

$hive_bumps = [
    1 => ['product' => 50, 'cats' => [7]],  // socks, for carts with sneakers (category 7)
    2 => ['product' => 51],                 // a cleaner, for any cart
    3 => ['product' => 52, 'cats' => [9]],
];

check(Bumps::pick($hive_bumps, [10], [7], [50, 51, 52]), 1, 'sneakers in the cart: the socks');
check(Bumps::pick($hive_bumps, [10, 50], [7], [50, 51, 52]), 2, 'the socks are in the cart already: the next one');
check(Bumps::pick($hive_bumps, [10], [8], [50, 51, 52]), 2, 'no sneakers: the bump for any cart');
check(Bumps::pick($hive_bumps, [10], [7], [51, 52]), 2, 'the socks are sold out');
check(Bumps::pick($hive_bumps, [10, 51], [8], [50, 51, 52]), null, 'nothing fits');
check(Bumps::pick($hive_bumps, [], [], [50, 51, 52]), null, 'an empty cart');
check(Bumps::pick([4 => ['product' => 0]], [10], [], [0]), null, 'a bump without a product');
