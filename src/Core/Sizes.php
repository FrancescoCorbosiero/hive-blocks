<?php
/**
 * Sizes, as this store writes them: how a size label becomes a comparable
 * key, which variation stands for a size when several do, which sizes answer
 * the shop's size filter and what the card says about them, and the order
 * sizes are shown in. Pure: tested by tests/php/SizesTest.php. The WordPress
 * side is Hive\Shop\Sizes.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class Sizes
{
    /**
     * A size label as a key that ignores how it was typed. The store carries
     * the same physical size under a space/slash spelling ("36 2/3") and a
     * dash one ("36-2-3"), plus exact duplicates ("36" twice): stripping
     * everything but letters and digits, lower case, makes them one key
     * ("3623", "36").
     */
    public static function key(string $label): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $label));
    }

    /**
     * One variation per physical size. When several variations carry the
     * same size, the most expensive one stands for it (the real listing; the
     * cheap ones are stray import duplicates); on a price tie, one in stock.
     * The first-seen position of each size is kept.
     *
     * @param list<array{variation_id: int, label: string, price: float, in_stock: bool}> $entries
     * @return list<array> The winning entries, as given.
     */
    public static function dedupe(array $entries): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            $key = self::key((string) ($entry['label'] ?? ''));
            if ('' === $key) {
                continue;
            }
            $current = $groups[$key] ?? null;
            if (
                null === $current
                || $entry['price'] > $current['price']
                || ($entry['price'] === $current['price'] && $entry['in_stock'] && !$current['in_stock'])
            ) {
                $groups[$key] = $entry;
            }
        }
        return array_values($groups);
    }

    /**
     * The in-stock sizes the size filter asked for, in the product's order.
     *
     * @param list<array{label: string, in_stock: bool}> $rows Size rows (Hive\Shop\Sizes::rows()).
     * @param list<string> $keys Keys of the filtered sizes.
     */
    public static function pick(array $rows, array $keys): array
    {
        if (!$keys) {
            return [];
        }
        $wanted = array_flip(array_map([self::class, 'key'], $keys));
        return array_values(
            array_filter(
                $rows,
                static fn(array $row): bool => !empty($row['in_stock']) && isset($wanted[self::key((string) ($row['label'] ?? ''))])
            )
        );
    }

    /**
     * The card's line for the matched sizes: one size names its own price
     * (in resell each size has its own), several are listed. '' for none.
     */
    public static function line(array $matches): string
    {
        if (!$matches) {
            return '';
        }
        if (1 === count($matches)) {
            $row = $matches[0];
            return 'Taglia ' . $row['label'] . (empty($row['price_text']) ? '' : ' · ' . $row['price_text']);
        }
        return 'Taglie ' . implode(', ', array_column($matches, 'label')) . ' disponibili';
    }

    /**
     * Where a size sorts: shoe sizes by number ("36 2/3" and "36-2-3" are
     * 36.67, "42,5" is 42.5, an EU/US/UK prefix is ignored), then letter
     * sizes XXS … XXXL, then anything else.
     */
    public static function sort_value(string $label): float
    {
        $l = strtolower(trim((string) preg_replace('/^(eu|us|uk)\s*/i', '', trim($label))));
        if (1 === preg_match('/^(\d+(?:[.,]\d+)?)(?:[ -](\d)[\/-](\d))?$/', $l, $m)) {
            $n = (float) str_replace(',', '.', $m[1]);
            if (!empty($m[2]) && (int) $m[3] > 0) {
                $n += (int) $m[2] / (int) $m[3];
            }
            return $n;
        }
        $letters = ['xxs' => 1, 'xs' => 2, 's' => 3, 'm' => 4, 'l' => 5, 'xl' => 6, 'xxl' => 7, '2xl' => 7, 'xxxl' => 8, '3xl' => 8];
        return isset($letters[$l]) ? 1000 + $letters[$l] : 2000;
    }

    /**
     * Items in size order (stable: equal sizes keep their order).
     *
     * @param list<array> $items
     * @param string      $field The label field to sort by.
     */
    public static function sort(array $items, string $field = 'label'): array
    {
        $indexed = [];
        foreach (array_values($items) as $i => $item) {
            $indexed[] = [self::sort_value((string) ($item[$field] ?? '')), $i, $item];
        }
        usort($indexed, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        return array_column($indexed, 2);
    }

    /**
     * The store's size terms grouped by physical size, in size order: each
     * option keeps every spelling's slug (a query for "36 2/3" also finds the
     * "36-2-3" pairs) and shows the spelling people read ("36 2/3").
     *
     * @param list<array{slug: string, name: string, count: int}> $terms
     * @return list<array{key: string, label: string, slugs: list<string>, count: int}>
     */
    public static function group_options(array $terms): array
    {
        $groups = [];
        foreach ($terms as $term) {
            $name = (string) ($term['name'] ?? '');
            $key  = self::key($name);
            if ('' === $key) {
                continue;
            }
            $groups[$key] ??= ['key' => $key, 'label' => $name, 'slugs' => [], 'count' => 0];
            $groups[$key]['slugs'][] = (string) ($term['slug'] ?? '');
            $groups[$key]['count']  += (int) ($term['count'] ?? 0);
            if (substr_count($name, '-') < substr_count($groups[$key]['label'], '-')) {
                $groups[$key]['label'] = $name;
            }
        }
        $options = array_values($groups);
        usort(
            $options,
            static fn(array $a, array $b): int => [self::sort_value($a['label']), $a['label']] <=> [self::sort_value($b['label']), $b['label']]
        );
        return $options;
    }
}
