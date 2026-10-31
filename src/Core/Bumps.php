<?php
/**
 * Which order bump the checkout offers (Hive\Shop\OrderBumps): the first,
 * in the bumps' order, whose product isn't in the cart already, can be
 * bought, and whose condition the cart meets: no categories (any cart), or
 * a product of one of its categories in the cart.
 *
 * Pure logic: no WordPress calls. Tested in tests/php/BumpsTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class Bumps
{
    /**
     * @param array<int, array{product: int, cats?: list<int>}> $bumps     Published bumps, in order: id => rules.
     * @param list<int>                                         $in_cart   Product ids in the cart (parents of variations too).
     * @param list<int>                                         $cart_cats The cart's products' categories (and their ancestors).
     * @param list<int>                                         $buyable   Bump products that can be bought now.
     */
    public static function pick(array $bumps, array $in_cart, array $cart_cats, array $buyable): ?int
    {
        if (!$in_cart) {
            return null;
        }
        foreach ($bumps as $id => $rules) {
            $product = (int) ($rules['product'] ?? 0);
            if (!$product || in_array($product, $in_cart, true) || !in_array($product, $buyable, true)) {
                continue;
            }
            $cats = array_map('intval', $rules['cats'] ?? []);
            if ($cats && !array_intersect($cats, $cart_cats)) {
                continue;
            }
            return (int) $id;
        }
        return null;
    }
}
