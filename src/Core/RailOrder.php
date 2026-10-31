<?php
/**
 * The order of a product rail, as the Store Hub's Vetrina sets it: pinned
 * products first, excluded ones never, the rest in a fallback order. The Hub
 * app mirrors these rules for its previews, so they stay exactly as in
 * golden-hive-blocks (includes/hub-rails-core.php). Pure: tested by
 * tests/php/RailOrderTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class RailOrder
{
    /** Most product ids a rail keeps in pin or exclude. */
    public const MAX_IDS = 100;

    /** What a rail can show besides a category, brand or tag. */
    public const TYPES = ['recent', 'best_selling', 'sale', 'featured', 'top_rated'];

    /** Orderings after the pinned products: WooCommerce's own catalog orderings. */
    public const FALLBACKS = ['menu_order', 'date', 'popularity', 'price', 'price-desc', 'rating'];

    /**
     * Product ids from "12, 5,9" or an array: positive integers only, first
     * occurrence wins, capped.
     *
     * @return list<int>
     */
    public static function parse_ids(mixed $raw, int $max = self::MAX_IDS): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $value) {
            if (is_string($value)) {
                $value = trim($value);
                if (1 !== preg_match('/^\d+$/', $value)) {
                    continue;
                }
            } elseif (!is_int($value)) {
                continue;
            }
            $id = (int) $value;
            if ($id > 0 && !isset($out[$id])) {
                $out[$id] = $id;
                if (count($out) >= $max) {
                    break;
                }
            }
        }
        return array_values($out);
    }

    /**
     * The order a rail falls back to when it names none: what it does
     * untouched. Category and brand rails follow the shop's custom order;
     * best sellers and top rated keep their metric; the rest, newest first.
     */
    public static function default_fallback(array $atts): string
    {
        $type = (string) ($atts['type'] ?? 'recent');
        if ('best_selling' === $type) {
            return 'popularity';
        }
        if ('top_rated' === $type) {
            return 'rating';
        }
        if (!empty($atts['category']) || !empty($atts['brand'])) {
            return 'menu_order';
        }
        return 'date';
    }

    /** A fallback name, or the rail's default when it's missing or unknown. */
    public static function fallback(mixed $raw, array $atts): string
    {
        $value = is_string($raw) ? strtolower(trim($raw)) : '';
        return in_array($value, self::FALLBACKS, true) ? $value : self::default_fallback($atts);
    }

    /** Whether the rail uses the Vetrina's ordering at all. */
    public static function has_ordering(array $atts): bool
    {
        return !empty($atts['pin']) || !empty($atts['exclude']) || !empty($atts['fallback']);
    }

    /**
     * THE ordering rule: pinned products first, in pin order, but only those
     * actually visible; then every other visible product in fallback order;
     * excluded products never.
     *
     * @param list<int> $visible Visible products of the rail, in fallback order.
     * @param list<int> $pin
     * @param list<int> $exclude
     * @return list<int>
     */
    public static function order(array $visible, array $pin, array $exclude): array
    {
        $visible_set = array_fill_keys(array_map('intval', $visible), true);
        $excluded    = array_fill_keys(array_map('intval', $exclude), true);
        $out         = [];
        $seen        = [];
        foreach ($pin as $id) {
            $id = (int) $id;
            if (isset($visible_set[$id]) && !isset($excluded[$id]) && !isset($seen[$id])) {
                $out[]     = $id;
                $seen[$id] = true;
            }
        }
        foreach ($visible as $id) {
            $id = (int) $id;
            if (!isset($excluded[$id]) && !isset($seen[$id])) {
                $out[]     = $id;
                $seen[$id] = true;
            }
        }
        return $out;
    }
}
