<?php
/**
 * One product in a WooCommerce product list: Hive's card (Hive\Modules\Cards
 * puts this template in place of the theme's content-product.php).
 *
 * @package Hive
 */

use Hive\Shop\Card;

defined('ABSPATH') || exit;

global $product;

if (!$product instanceof WC_Product || !$product->is_visible()) {
    return;
}

$hive_archive = is_shop() || is_product_taxonomy() || is_search();
$hive_card    = Card::render(
    $product,
    [
        'heading' => $hive_archive ? 'h2' : 'h3',
        'eager'   => $hive_archive && Card::count() < 4,
    ]
);

echo $hive_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Card::render() escapes what it prints.
