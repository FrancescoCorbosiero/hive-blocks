<?php
/**
 * Which size guide a product shows (Hive → Guide alle taglie):
 *
 *   1. the product's own choice (a guide, or none), on its edit screen
 *   2. otherwise the guides whose every condition matches the product: it is
 *      in one of the guide's categories (or below one), and it has one of
 *      the guide's brands. The most specific wins: more conditions first,
 *      then the deeper category, then the guides' order
 *   3. otherwise the default guide, if one is marked so
 *
 * A guide with no categories, no brands and not marked default is shown
 * only where a product picks it.
 *
 * Pure logic: no WordPress calls. Tested in tests/php/SizeGuidePickTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class SizeGuidePick
{
    /**
     * @param array<int, array{cats?: list<int>, brands?: list<int>, default?: bool}> $guides Published guides, in order.
     * @param array<int, int> $cat_depths The product's categories and their ancestors, term id => depth (0 at the top).
     * @param list<int> $brands The product's brand term ids.
     * @param string $choice The product's own choice: '' (automatic), 'none', or a guide id.
     */
    public static function pick(array $guides, array $cat_depths, array $brands, string $choice = ''): ?int
    {
        if ('none' === $choice) {
            return null;
        }
        if (ctype_digit($choice) && isset($guides[(int) $choice])) {
            return (int) $choice;
        }

        $best      = null;
        $best_rank = null;
        $fallback  = null;
        $position  = 0;
        foreach ($guides as $id => $rules) {
            $position++;
            $cats   = array_map('intval', $rules['cats'] ?? []);
            $guide_brands = array_map('intval', $rules['brands'] ?? []);
            if (!$cats && !$guide_brands) {
                if (!empty($rules['default']) && null === $fallback) {
                    $fallback = (int) $id;
                }
                continue;
            }

            $depth = -1;
            if ($cats) {
                foreach ($cats as $cat) {
                    if (isset($cat_depths[$cat])) {
                        $depth = max($depth, (int) $cat_depths[$cat]);
                    }
                }
                if ($depth < 0) {
                    continue;
                }
            }
            if ($guide_brands && !array_intersect($guide_brands, array_map('intval', $brands))) {
                continue;
            }

            // Higher is better; the earlier guide wins a tie.
            $rank = [($cats ? 1 : 0) + ($guide_brands ? 1 : 0), $depth, -$position];
            if (null === $best_rank || $rank > $best_rank) {
                $best      = (int) $id;
                $best_rank = $rank;
            }
        }
        return $best ?? $fallback;
    }
}
