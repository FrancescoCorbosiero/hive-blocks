<?php
/**
 * The shop's filters as URL parameters, read and rewritten. They are
 * WooCommerce's own layered-nav parameters, so every filtered page is a
 * plain URL that can be shared, cached and crawled:
 *
 *   filter_{facet}=40,42     values of a facet (filter_cat for categories)
 *   query_type_{facet}=or    several values in a facet: any of them
 *   min_price, max_price     the price range
 *   instock=1                only what's in stock
 *
 * Pure: tested by tests/php/FiltersTest.php. The WordPress side is
 * Hive\Shop\Filters.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class Filters
{
    /** Most values read from one parameter. */
    private const MAX_VALUES = 20;

    /**
     * The active filters.
     *
     * @param array<string, mixed>  $params     The query parameters (unslashed).
     * @param array<string, string> $taxonomies Facet key → taxonomy, e.g. ['taglia' => 'pa_taglia', 'cat' => 'product_cat'].
     * @return array{tax: array<string, array{taxonomy: string, terms: list<string>, operator: string}>, min_price: float|null, max_price: float|null, instock: bool}
     */
    public static function parse(array $params, array $taxonomies): array
    {
        $out = ['tax' => [], 'min_price' => null, 'max_price' => null, 'instock' => false];
        foreach ($taxonomies as $key => $taxonomy) {
            $terms = self::values($params['filter_' . $key] ?? '');
            if ($terms) {
                $or               = 'or' === strtolower(self::text($params['query_type_' . $key] ?? ''));
                $out['tax'][$key] = ['taxonomy' => $taxonomy, 'terms' => $terms, 'operator' => $or ? 'IN' : 'AND'];
            }
        }
        foreach (['min_price', 'max_price'] as $key) {
            $value = self::text($params[$key] ?? '');
            if ('' !== $value && is_numeric($value) && (float) $value >= 0) {
                $out[$key] = (float) $value;
            }
        }
        $out['instock'] = '1' === self::text($params['instock'] ?? '');
        return $out;
    }

    /** Whether any filter is on. */
    public static function active(array $filters): bool
    {
        return [] !== $filters['tax'] || null !== $filters['min_price'] || null !== $filters['max_price'] || $filters['instock'];
    }

    /** "40,42" → ['40', '42']: slugs, each once, at most 20. A stray array (filter_taglia[]=) is no filter. */
    public static function values(mixed $raw): array
    {
        $out = [];
        foreach (explode(',', self::text($raw)) as $value) {
            $slug = (string) preg_replace('/[^a-z0-9_%-]/', '', strtolower(trim($value)));
            if ('' !== $slug && !in_array($slug, $out, true)) {
                $out[] = $slug;
            }
        }
        return array_slice($out, 0, self::MAX_VALUES);
    }

    /**
     * The parameters with one value of a facet switched on or off. Several
     * values in a facet mean any of them; a new choice starts from page 1.
     */
    public static function toggle(array $params, string $key, string $value): array
    {
        $values = self::values($params['filter_' . $key] ?? '');
        $values = in_array($value, $values, true) ? array_values(array_diff($values, [$value])) : array_merge($values, [$value]);
        return self::with($params, $key, $values);
    }

    /** The parameters with a facet set to exactly these values (none: the facet off). */
    public static function with(array $params, string $key, array $values): array
    {
        unset($params['paged'], $params['product-page']);
        if ($values) {
            $params['filter_' . $key] = implode(',', $values);
            if (count($values) > 1) {
                $params['query_type_' . $key] = 'or';
            } else {
                unset($params['query_type_' . $key]);
            }
        } else {
            unset($params['filter_' . $key], $params['query_type_' . $key]);
        }
        return $params;
    }

    /** The parameters without one of: a facet ('taglia'), 'price' or 'instock'. */
    public static function without(array $params, string $key): array
    {
        unset($params['paged'], $params['product-page']);
        if ('price' === $key) {
            unset($params['min_price'], $params['max_price']);
        } elseif ('instock' === $key) {
            unset($params['instock']);
        } else {
            unset($params['filter_' . $key], $params['query_type_' . $key]);
        }
        return $params;
    }

    /** The parameters without any filter; the sort order stays. */
    public static function cleared(array $params): array
    {
        return isset($params['orderby']) ? ['orderby' => $params['orderby']] : [];
    }

    /** The parameters the catalog keeps from a URL: its filters and the sort order, nothing else. */
    public static function keep(array $params, array $keys): array
    {
        $allowed = ['orderby' => true, 'min_price' => true, 'max_price' => true, 'instock' => true];
        foreach ($keys as $key) {
            $allowed['filter_' . $key]     = true;
            $allowed['query_type_' . $key] = true;
        }
        $out = [];
        foreach ($params as $key => $value) {
            if (isset($allowed[$key]) && is_string($value) && '' !== $value) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /** "Mostrando 1–24 di 123 prodotti", "1 prodotto", "Nessun prodotto". */
    public static function count_label(int $found, int $per_page, int $page): string
    {
        if ($found < 1) {
            return 'Nessun prodotto';
        }
        if (1 === $found) {
            return '1 prodotto';
        }
        if ($per_page < 1 || $found <= $per_page) {
            return $found . ' prodotti';
        }
        $first = ($page - 1) * $per_page + 1;
        return sprintf('Mostrando %d–%d di %d prodotti', $first, min($found, $page * $per_page), $found);
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
