<?php

use Hive\Core\Price;

check(Price::badge([[200, 160]]), '-20%', 'a simple product on sale');
check(Price::badge([[200, 200]]), '', 'not on sale');
check(Price::badge([]), '', 'no prices');
check(Price::badge([[200, 160], [100, 80]]), '-20%', 'every size cut by the same share');
check(Price::badge([[200, 160], [100, 70]]), 'Fino a -30%', 'different cuts: the deepest, as "up to"');
check(Price::badge([[200, 160], [100, 100]]), 'Fino a -20%', 'only some sizes on sale');
check(Price::badge([['199.90', '149.90']]), '-25%', 'prices as WooCommerce stores them (strings)');
check(Price::badge([[0, 0]]), '', 'an unpriced product');
