<?php

// The Store Hub's rail ordering: the same cases as golden-hive-blocks' tests/hub-rails-test.php.

use Hive\Core\RailOrder;

check(RailOrder::parse_ids('12, 5,abc,9,5,-3,0, 7 '), [12, 5, 9, 7], 'id lists keep positive integers, first occurrence');
check(count(RailOrder::parse_ids(range(1, 500))), RailOrder::MAX_IDS, 'id lists are capped');
check(RailOrder::parse_ids(null), [], 'no list, no ids');

check(
    RailOrder::order([10, 20, 30, 40, 50], [40, 99, 10], [20]),
    [40, 10, 30, 50],
    'visible pins first in pin order, then the rest in fallback order, exclusions never'
);
check(RailOrder::order([1, 2], [2, 2, 1], []), [2, 1], 'duplicate pins count once');
check(RailOrder::order([1, 2], [], [1, 2]), [], 'everything excluded is an empty rail');

check(RailOrder::default_fallback(['brand' => 'adidas']), 'menu_order', 'brand rails default to the custom order');
check(RailOrder::default_fallback(['type' => 'best_selling', 'category' => 'x']), 'popularity', 'bestseller rails keep their metric');
check(RailOrder::default_fallback(['type' => 'top_rated']), 'rating', 'top rated rails keep theirs');
check(RailOrder::default_fallback([]), 'date', 'a plain rail defaults to newest');

check(RailOrder::fallback(' Price-Desc ', []), 'price-desc', 'a known fallback, any case');
check(RailOrder::fallback('random', ['category' => 'saldi']), 'menu_order', 'an unknown one falls back to the default');

check(RailOrder::has_ordering(['pin' => '1']), true, 'pins mean Vetrina ordering');
check(RailOrder::has_ordering(['pin' => '', 'exclude' => '', 'fallback' => '']), false, 'an untouched rail');
