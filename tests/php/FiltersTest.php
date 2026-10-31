<?php

use Hive\Core\Filters;

$taxonomies = ['taglia' => 'pa_taglia', 'cat' => 'product_cat'];

$none = ['tax' => [], 'min_price' => null, 'max_price' => null, 'instock' => false];
check(Filters::parse([], $taxonomies), $none, 'no parameters, no filters');
check(Filters::active($none), false, 'nothing active');

$filters = Filters::parse(['filter_taglia' => '40,42', 'query_type_taglia' => 'or', 'filter_cat' => 'sneakers', 'min_price' => '50', 'max_price' => 'abc', 'instock' => '1'], $taxonomies);
check($filters['tax']['taglia'], ['taxonomy' => 'pa_taglia', 'terms' => ['40', '42'], 'operator' => 'IN'], 'sizes, any of them');
check($filters['tax']['cat']['operator'], 'AND', 'one value: and');
check([$filters['min_price'], $filters['max_price'], $filters['instock']], [50.0, null, true], 'a price floor, a broken ceiling ignored, in stock only');
check(Filters::active($filters), true, 'filters on');

check(Filters::values('42, 40,42,,X L'), ['42', '40', 'xl'], 'values: trimmed, lower case, each once');
check(Filters::values(['42']), [], 'an array is no filter');
check(count(Filters::values(implode(',', range(1, 50)))), 20, 'at most 20 values');

// Rewriting the URL.
check(Filters::toggle([], 'taglia', '42'), ['filter_taglia' => '42'], 'a first value');
check(Filters::toggle(['filter_taglia' => '42'], 'taglia', '40'), ['filter_taglia' => '42,40', 'query_type_taglia' => 'or'], 'a second value: any of them');
check(Filters::toggle(['filter_taglia' => '42,40', 'query_type_taglia' => 'or'], 'taglia', '42'), ['filter_taglia' => '40'], 'back to one value');
check(Filters::toggle(['filter_taglia' => '42', 'paged' => '3', 'orderby' => 'price'], 'taglia', '42'), ['orderby' => 'price'], 'the last value off; page 1; the sort stays');
check(Filters::without(['min_price' => '1', 'max_price' => '9', 'instock' => '1'], 'price'), ['instock' => '1'], 'price off');
check(Filters::without(['filter_cat' => 'a', 'query_type_cat' => 'or', 'instock' => '1'], 'cat'), ['instock' => '1'], 'a facet off');
check(Filters::cleared(['filter_cat' => 'a', 'orderby' => 'date', 'instock' => '1']), ['orderby' => 'date'], 'everything off but the sort');
check(
    Filters::keep(['filter_taglia' => '42', 'orderby' => 'date', 'utm_source' => 'ig', 'add-to-cart' => '12', 'filter_other' => 'x', 'instock' => ['1']], ['taglia']),
    ['filter_taglia' => '42', 'orderby' => 'date'],
    'only the filters and the sort are carried in links'
);

check(Filters::count_label(0, 24, 1), 'Nessun prodotto', 'no products');
check(Filters::count_label(1, 24, 1), '1 prodotto', 'one');
check(Filters::count_label(12, 24, 1), '12 prodotti', 'one page');
check(Filters::count_label(123, 24, 2), 'Mostrando 25–48 di 123 prodotti', 'a page of several');
check(Filters::count_label(123, 24, 6), 'Mostrando 121–123 di 123 prodotti', 'the last page');
