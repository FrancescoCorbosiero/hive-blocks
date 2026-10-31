<?php
/**
 * The buttons of a product page's attribute (usually the size), from
 * WooCommerce's own variation data (WC_Product_Variable::get_available_variations()):
 * one row per option, saying whether it can be bought and, when the
 * attribute is the only one that varies, its price and whether it's on sale.
 *
 * Pure logic: no WordPress calls. Tested in tests/php/SwatchesTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class Swatches
{
    /**
     * @param string $field   The variation field, e.g. attribute_pa_taglia.
     * @param list<array{value: string, label: string}> $options The attribute's options, in order.
     * @param list<array<string, mixed>> $variations WooCommerce's available variations.
     * @param bool $priced    Give each option its price (only when it is the one attribute that varies).
     * @return list<array{value: string, label: string, state: string, price: float|null, regular: float|null, sale: bool}>
     *   state: 'in' (can be bought), 'out' (sold out: still a choice, the page says so),
     *          'none' (no variation has it)
     */
    public static function rows(string $field, array $options, array $variations, bool $priced): array
    {
        $rows = [];
        foreach ($options as $option) {
            $value    = (string) $option['value'];
            $matching = array_values(
                array_filter(
                    $variations,
                    static function ($variation) use ($field, $value): bool {
                        $have = (string) ($variation['attributes'][$field] ?? '');
                        // An empty value is WooCommerce's "any".
                        return is_array($variation) && ('' === $have || $have === $value);
                    }
                )
            );
            $buyable = array_values(array_filter($matching, [self::class, 'buyable']));
            $state   = $buyable ? 'in' : ($matching ? 'out' : 'none');

            $price   = null;
            $regular = null;
            if ($priced && $matching) {
                $cheapest = self::cheapest($buyable ?: $matching);
                if (null !== $cheapest) {
                    $price   = (float) $cheapest['display_price'];
                    $regular = isset($cheapest['display_regular_price']) && is_numeric($cheapest['display_regular_price']) ? (float) $cheapest['display_regular_price'] : $price;
                }
            }

            $rows[] = [
                'value'   => $value,
                'label'   => (string) $option['label'],
                'state'   => $state,
                'price'   => $price,
                'regular' => $regular,
                'sale'    => null !== $price && null !== $regular && $price < $regular,
            ];
        }
        return $rows;
    }

    /** A variation someone can put in the cart. */
    public static function buyable(mixed $variation): bool
    {
        return is_array($variation)
            && !empty($variation['is_in_stock'])
            && ($variation['is_purchasable'] ?? true)
            && ($variation['variation_is_visible'] ?? true);
    }

    /** @param list<array<string, mixed>> $variations */
    private static function cheapest(array $variations): ?array
    {
        $best = null;
        foreach ($variations as $variation) {
            if (!isset($variation['display_price']) || !is_numeric($variation['display_price'])) {
                continue;
            }
            if (null === $best || (float) $variation['display_price'] < (float) $best['display_price']) {
                $best = $variation;
            }
        }
        return $best;
    }
}
