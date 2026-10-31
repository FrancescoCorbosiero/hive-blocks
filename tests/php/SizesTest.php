<?php

use Hive\Core\Sizes;

check(Sizes::key('36 2/3'), '3623', 'the slash spelling collapses');
check(Sizes::key('36-2-3'), '3623', 'the dash spelling collapses to the same key');
check(Sizes::key('XL'), 'xl', 'letters are lower-cased');

// One variation per physical size.
$entry = static fn(int $id, string $label, float $price, bool $in_stock): array => [
    'variation_id' => $id,
    'label'        => $label,
    'price'        => $price,
    'in_stock'     => $in_stock,
];
check(
    array_column(Sizes::dedupe([$entry(1, '36 2/3', 150, true), $entry(2, '36-2-3', 210, true), $entry(3, '40', 180, true)]), 'variation_id'),
    [2, 3],
    'the most expensive variation stands for a size spelled twice, in first-seen position'
);
check(
    array_column(Sizes::dedupe([$entry(1, '42', 180, false), $entry(2, '42', 180, true)]), 'variation_id'),
    [2],
    'on a price tie the one in stock wins'
);
check(Sizes::dedupe([$entry(1, '', 10, true)]), [], 'a variation without a size is left out');

// The shop's size filter.
$rows = [
    ['variation_id' => 11, 'label' => '40', 'in_stock' => true, 'price_text' => '150 €'],
    ['variation_id' => 12, 'label' => '42', 'in_stock' => true, 'price_text' => '180 €'],
    ['variation_id' => 13, 'label' => '43', 'in_stock' => false, 'price_text' => '190 €'],
    ['variation_id' => 14, 'label' => '36 2/3', 'in_stock' => true, 'price_text' => '210 €'],
];
check(Sizes::pick($rows, []), [], 'no filter, no match');
check(array_column(Sizes::pick($rows, ['42']), 'variation_id'), [12], 'the filtered size');
check(Sizes::pick($rows, ['43']), [], 'a sold-out size never matches');
check(array_column(Sizes::pick($rows, ['36-2-3']), 'variation_id'), [14], 'a filter matches the other spelling');
check(array_column(Sizes::pick($rows, ['42', '40', '99']), 'variation_id'), [11, 12], 'several sizes keep the product order');

check(Sizes::line([]), '', 'nothing matched, no line');
check(Sizes::line([$rows[1]]), 'Taglia 42 · 180 €', 'one size names its price');
check(Sizes::line([['label' => '42', 'price_text' => '']]), 'Taglia 42', 'an unpriced size names just the size');
check(Sizes::line([$rows[0], $rows[1]]), 'Taglie 40, 42 disponibili', 'several sizes are listed');

// Order.
check(Sizes::sort_value('36 2/3') > Sizes::sort_value('36'), true, '36 2/3 after 36');
check(Sizes::sort_value('36-2-3'), Sizes::sort_value('36 2/3'), 'both spellings sort together');
check(Sizes::sort_value('42,5'), 42.5, 'a comma decimal');
check(Sizes::sort_value('EU 44'), 44.0, 'an EU prefix is ignored');
check(Sizes::sort_value('XL') > Sizes::sort_value('48'), true, 'letter sizes after shoe sizes');
check(Sizes::sort_value('M') < Sizes::sort_value('XL'), true, 'letter sizes in their own order');
check(
    array_column(Sizes::sort([['label' => '42'], ['label' => 'M'], ['label' => '36 2/3'], ['label' => '40']]), 'label'),
    ['36 2/3', '40', '42', 'M'],
    'items sorted by size'
);

$options = Sizes::group_options([
    ['slug' => '42', 'name' => '42', 'count' => 5],
    ['slug' => '36-2-3', 'name' => '36-2-3', 'count' => 1],
    ['slug' => '36-2-3-2', 'name' => '36 2/3', 'count' => 2],
    ['slug' => 'xl', 'name' => 'XL', 'count' => 3],
    ['slug' => '40-5', 'name' => '40.5', 'count' => 4],
    ['slug' => 'junk', 'name' => '', 'count' => 9],
]);
check(array_column($options, 'label'), ['36 2/3', '40.5', '42', 'XL'], 'grouped, in size order, the readable spelling');
check($options[0]['slugs'], ['36-2-3', '36-2-3-2'], 'a group keeps every spelling');
check($options[0]['count'], 3, 'and adds their products up');
