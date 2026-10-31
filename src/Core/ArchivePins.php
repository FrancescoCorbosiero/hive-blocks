<?php
/**
 * A category or brand page follows its homepage rail: the products the
 * Store Hub pinned on the homepage's "Sneakers" rail come first on the
 * Sneakers page too, and the ones it excluded aren't there. The same rule as
 * golden-hive-blocks (ghb_hub_archive_rail_map). Pure: tested by
 * tests/php/ArchivePinsTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class ArchivePins
{
    /**
     * The homepage rails' pins and exclusions, for the pages of their one
     * category or brand: 'category:{slug}' or 'brand:{slug}' →
     * ['pin' => ids, 'exclude' => ids]. Rails with neither, or with several
     * terms, don't count; the first rail for a term wins.
     *
     * @param list<array> $rails Rail attributes, in page order.
     * @return array<string, array{pin: list<int>, exclude: list<int>}>
     */
    public static function map(array $rails): array
    {
        $map = [];
        foreach ($rails as $atts) {
            $pin     = RailOrder::parse_ids($atts['pin'] ?? '');
            $exclude = RailOrder::parse_ids($atts['exclude'] ?? '');
            if (!$pin && !$exclude) {
                continue;
            }
            $category = trim((string) ($atts['category'] ?? ''));
            $brand    = trim((string) ($atts['brand'] ?? ''));
            if ('' !== $category) {
                $key = str_contains($category, ',') ? null : 'category:' . $category;
            } else {
                $key = '' === $brand || str_contains($brand, ',') ? null : 'brand:' . $brand;
            }
            if (null !== $key && !isset($map[$key])) {
                $map[$key] = ['pin' => $pin, 'exclude' => $exclude];
            }
        }
        return $map;
    }
}
