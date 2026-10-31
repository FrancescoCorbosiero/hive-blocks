<?php

// The size buttons of a product page, from WooCommerce's variation data.

use Hive\Core\Swatches;

$hive_variation = static fn(string $size, float $price, float $regular, bool $in = true, array $extra = []): array => $extra + [
    'attributes'            => ['attribute_pa_taglia' => $size],
    'display_price'         => $price,
    'display_regular_price' => $regular,
    'is_in_stock'           => $in,
    'is_purchasable'        => true,
    'variation_is_visible'  => true,
];
$hive_options = [['value' => '40', 'label' => '40'], ['value' => '41', 'label' => '41'], ['value' => '42', 'label' => '42'], ['value' => '43', 'label' => '43']];
$hive_rows    = Swatches::rows(
    'attribute_pa_taglia',
    $hive_options,
    [$hive_variation('40', 150, 180), $hive_variation('41', 160, 160, false), $hive_variation('42', 180, 180)],
    true
);

check(array_column($hive_rows, 'state'), ['in', 'out', 'in', 'none'], 'in stock, sold out (still a choice), and a size no variation has');
check([$hive_rows[0]['price'], $hive_rows[0]['regular'], $hive_rows[0]['sale']], [150.0, 180.0, true], 'a size on sale');
check([$hive_rows[1]['price'], $hive_rows[1]['sale']], [160.0, false], 'a sold-out size keeps its price');
check([$hive_rows[3]['price'], $hive_rows[3]['sale']], [null, false], 'no variation, no price');
check(array_column($hive_rows, 'label'), ['40', '41', '42', '43'], 'the options keep their order');

$hive_unpriced = Swatches::rows('attribute_pa_taglia', $hive_options, [$hive_variation('40', 150, 180)], false);
check([$hive_unpriced[0]['price'], $hive_unpriced[0]['sale'], $hive_unpriced[0]['state']], [null, false, 'in'], 'no prices when another attribute varies too');

// "Any size" variations count for every size.
$hive_any = Swatches::rows('attribute_pa_taglia', $hive_options, [$hive_variation('', 99, 99)], true);
check(array_column($hive_any, 'state'), ['in', 'in', 'in', 'in'], 'a variation for any size makes every size available');

// Two variations for one size (two colours, say): the cheapest one that can be bought.
$hive_two = Swatches::rows(
    'attribute_pa_taglia',
    [['value' => '40', 'label' => '40']],
    [$hive_variation('40', 120, 120, false), $hive_variation('40', 170, 200), $hive_variation('40', 140, 140)],
    true
);
check([$hive_two[0]['price'], $hive_two[0]['regular'], $hive_two[0]['sale']], [140.0, 140.0, false], 'the cheapest buyable variation sets the price');

check(Swatches::buyable($hive_variation('40', 1, 1, true, ['is_purchasable' => false])), false, 'not purchasable');
check(Swatches::buyable($hive_variation('40', 1, 1, true, ['variation_is_visible' => false])), false, 'not visible');
check(Swatches::buyable('x'), false, 'not a variation');
