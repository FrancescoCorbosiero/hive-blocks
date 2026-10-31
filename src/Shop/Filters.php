<?php
/**
 * The shop's filters, the WordPress side: which facets there are, the
 * collection being viewed, the main query's extras, and the counts next to
 * every option. The URL parameters are Core\Filters (WooCommerce's own
 * layered-nav ones), so WooCommerce applies filter_{attribute},
 * query_type_*, min_price and max_price itself; this adds:
 *
 *   filter_cat   categories inside the collection
 *   instock=1    only what's in stock
 *   sizes        a size matches only products with that size IN STOCK (a
 *                variation with it), not every product that lists it
 *
 * Ported from golden-hive-blocks' includes/filters.php, with the counts
 * cached per catalog generation (Store::generation()) instead of minutes.
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Core\Filters as Rules;

defined('ABSPATH') || exit;

final class Filters
{
    /** Above this many products in a collection the options show no counts (they'd cost too much). */
    private const COUNT_LIMIT = 4000;

    /** @var array<string, array>|null */
    private static ?array $facets = null;

    /**
     * The facets, in order, keyed by their URL name (filter_{key}):
     *
     *   price      the price range
     *   cat        categories (filter_cat)
     *   stock      only what's in stock
     *   {name}     every product attribute (pa_{name}); the size attribute
     *              first, matched on stock, shown as a grid of chips
     *
     * Filtered by hive_filter_facets.
     *
     * @return array<string, array{type: string, label: string, taxonomy?: string, variation?: bool, ui?: string}>
     */
    public static function facets(): array
    {
        if (null !== self::$facets) {
            return self::$facets;
        }
        $size       = Store::size_attribute();
        $attributes = [];
        foreach (function_exists('wc_get_attribute_taxonomies') ? wc_get_attribute_taxonomies() : [] as $attribute) {
            $taxonomy = 'pa_' . $attribute->attribute_name;
            $facet    = [
                'type'      => 'attribute',
                'label'     => (string) $attribute->attribute_label,
                'taxonomy'  => $taxonomy,
                'variation' => $taxonomy === $size,
                'ui'        => $taxonomy === $size ? 'chips' : 'list',
            ];
            if ($taxonomy === $size) {
                $attributes = [$attribute->attribute_name => $facet] + $attributes;
            } else {
                $attributes[$attribute->attribute_name] = $facet;
            }
        }
        $facets = [
            'price' => ['type' => 'price', 'label' => 'Prezzo'],
            'cat'   => ['type' => 'category', 'label' => 'Categoria', 'taxonomy' => 'product_cat', 'ui' => 'pills'],
            'stock' => ['type' => 'stock', 'label' => 'Disponibilità'],
        ] + $attributes;

        return self::$facets = (array) apply_filters('hive_filter_facets', $facets);
    }

    /** @return array<string, string> facet key → taxonomy, for the facets on a taxonomy. */
    public static function taxonomies(): array
    {
        $out = [];
        foreach (self::facets() as $key => $facet) {
            if (!empty($facet['taxonomy']) && taxonomy_exists($facet['taxonomy'])) {
                $out[$key] = $facet['taxonomy'];
            }
        }
        return $out;
    }

    /** This request's query parameters, unslashed (read-only display filters, like WooCommerce's own: no nonce). */
    public static function params(): array
    {
        $params = [];
        foreach (wp_unslash($_GET) as $key => $value) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (is_string($key) && is_string($value)) {
                $params[$key] = sanitize_text_field($value);
            }
        }
        return Rules::keep($params, array_keys(self::taxonomies()));
    }

    /** The active filters of this request. */
    public static function current(): array
    {
        return Rules::parse(self::params(), self::taxonomies());
    }

    /**
     * The collection being viewed: the queried term of a product taxonomy
     * archive (category, tag, brand, attribute), or null for the whole shop.
     *
     * @return array{taxonomy: string, term_id: int}|null
     */
    public static function scope(): ?array
    {
        if (!is_product_taxonomy()) {
            return null;
        }
        $term = get_queried_object();
        return $term instanceof \WP_Term ? ['taxonomy' => $term->taxonomy, 'term_id' => (int) $term->term_id] : null;
    }

    /** woocommerce_product_query: the filters WooCommerce doesn't apply itself. */
    public static function query(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        $filters = self::current();

        if (isset($filters['tax']['cat'])) {
            $tax   = (array) $query->get('tax_query');
            $tax[] = ['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $filters['tax']['cat']['terms'], 'operator' => $filters['tax']['cat']['operator']];
            $query->set('tax_query', $tax);
        }
        if ($filters['instock']) {
            $meta   = (array) $query->get('meta_query');
            $meta[] = ['key' => '_stock_status', 'value' => 'instock'];
            $query->set('meta_query', $meta);
        }
        $variant = self::variant_ids($filters);
        if (null !== $variant) {
            $existing = array_filter(array_map('absint', (array) $query->get('post__in')));
            $ids      = $existing ? array_values(array_intersect($existing, $variant)) : $variant;
            $query->set('post__in', $ids ?: [0]);
        }
    }

    /**
     * Products with an IN-STOCK variation for the filtered sizes (values of a
     * facet: any; facets: all), plus simple products carrying them in stock.
     * Null when no size-like facet is on.
     *
     * @param string|null $except A facet to leave out (its own counts).
     * @return list<int>|null
     */
    public static function variant_ids(array $filters, ?string $except = null): ?array
    {
        $facets = self::facets();
        $active = [];
        foreach ($filters['tax'] as $key => $filter) {
            if ($key !== $except && !empty($facets[$key]['variation'])) {
                $active[$filter['taxonomy']] = $filter;
            }
        }
        if (!$active) {
            return null;
        }

        global $wpdb;
        $sql  = "SELECT DISTINCT v.post_parent FROM {$wpdb->posts} v";
        $args = [];
        $i    = 0;
        foreach ($active as $taxonomy => $filter) {
            ++$i;
            $placeholders = implode(',', array_fill(0, count($filter['terms']), '%s'));
            $sql         .= " INNER JOIN {$wpdb->postmeta} m{$i} ON m{$i}.post_id = v.ID AND m{$i}.meta_key = %s AND LOWER(m{$i}.meta_value) IN ({$placeholders})";
            $args[]       = 'attribute_' . $taxonomy;
            foreach ($filter['terms'] as $term) {
                $args[] = strtolower($term);
            }
        }
        $sql .= " INNER JOIN {$wpdb->postmeta} s ON s.post_id = v.ID AND s.meta_key = '_stock_status' AND s.meta_value = 'instock'";
        $sql .= " WHERE v.post_type = 'product_variation' AND v.post_status = 'publish'";
        $parents = array_map('intval', $wpdb->get_col($wpdb->prepare($sql, $args))); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from placeholders only.

        $tax_query = ['relation' => 'AND', ['taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'simple']];
        foreach ($active as $taxonomy => $filter) {
            $tax_query[] = ['taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $filter['terms'], 'operator' => $filter['operator']];
        }
        $simple = (new \WP_Query(
            [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
                'fields'         => 'ids',
                'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery
                'meta_query'     => [['key' => '_stock_status', 'value' => 'instock']], // phpcs:ignore WordPress.DB.SlowDBQuery
            ]
        ))->posts;

        return array_values(array_unique(array_merge($parents, array_map('intval', $simple))));
    }

    /**
     * The ids of the collection's products the filters let through.
     *
     * @return list<int>
     */
    public static function matching_ids(array $filters, ?array $scope, ?string $except = null): array
    {
        $variant    = self::variant_ids($filters, $except);
        $facets     = self::facets();
        $tax_query  = ['relation' => 'AND', self::visibility_clause()];
        $meta_query = [];
        if ($scope) {
            $tax_query[] = ['taxonomy' => $scope['taxonomy'], 'field' => 'term_id', 'terms' => $scope['term_id'], 'include_children' => true];
        }
        foreach ($filters['tax'] as $key => $filter) {
            if ($key === $except || (null !== $variant && !empty($facets[$key]['variation']))) {
                continue; // a size is matched on stock, through $variant
            }
            $tax_query[] = ['taxonomy' => $filter['taxonomy'], 'field' => 'slug', 'terms' => $filter['terms'], 'operator' => $filter['operator']];
        }
        if (null !== $filters['min_price'] || null !== $filters['max_price']) {
            $meta_query[] = ['key' => '_price', 'value' => [$filters['min_price'] ?? 0, $filters['max_price'] ?? PHP_INT_MAX], 'compare' => 'BETWEEN', 'type' => 'NUMERIC'];
        }
        if ($filters['instock']) {
            $meta_query[] = ['key' => '_stock_status', 'value' => 'instock'];
        }
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'fields'         => 'ids',
            'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery
            'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery
        ];
        if (null !== $variant) {
            $args['post__in'] = $variant ?: [0];
        }
        return array_map('intval', (new \WP_Query($args))->posts);
    }

    /**
     * Counts next to every option, within the collection:
     *
     *   total    products in the collection
     *   instock  how many of them are in stock (without filters)
     *   terms    facet key → [slug → count], each facet counted with its own
     *            selection left out (its values are alternatives)
     *
     * Null when the collection is too large to count. Cached per catalog
     * generation.
     */
    public static function counts(array $filters, ?array $scope): ?array
    {
        $key    = 'hive_counts_' . md5((string) wp_json_encode([$filters, $scope, Store::generation()]));
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }

        $empty = Rules::parse([], []);
        $base  = self::matching_ids($empty, $scope);
        if (count($base) > self::COUNT_LIMIT) {
            return null;
        }
        $filtered = Rules::active($filters);
        $out      = [
            'total'   => count($base),
            'instock' => $filtered ? null : count(self::matching_ids(['instock' => true] + $empty, $scope)),
            'terms'   => [],
        ];
        $facets = self::facets();
        foreach (self::taxonomies() as $facet => $taxonomy) {
            $ids = $filtered ? self::matching_ids($filters, $scope, $facet) : $base;
            if (!empty($facets[$facet]['variation'])) {
                $out['terms'][$facet] = self::variation_counts($ids, $taxonomy);
                continue;
            }
            $out['terms'][$facet] = [];
            if ($ids) {
                $terms = wp_get_object_terms($ids, $taxonomy, ['fields' => 'all_with_object_id']);
                foreach (is_array($terms) ? $terms : [] as $term) {
                    $out['terms'][$facet][$term->slug] = ($out['terms'][$facet][$term->slug] ?? 0) + 1;
                }
            }
        }
        set_transient($key, $out, DAY_IN_SECONDS);
        return $out;
    }

    /**
     * How many of these products have an in-stock variation of each size.
     *
     * @param list<int> $ids
     * @return array<string, int> slug → products
     */
    private static function variation_counts(array $ids, string $taxonomy): array
    {
        if (!$ids) {
            return [];
        }
        global $wpdb;
        // The IN list is placeholders only ('%d,%d,…'), filled by prepare().
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $rows         = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT LOWER(a.meta_value) slug, COUNT(DISTINCT v.post_parent) c FROM {$wpdb->posts} v
                 INNER JOIN {$wpdb->postmeta} a ON a.post_id = v.ID AND a.meta_key = %s AND a.meta_value <> ''
                 INNER JOIN {$wpdb->postmeta} s ON s.post_id = v.ID AND s.meta_key = '_stock_status' AND s.meta_value = 'instock'
                 WHERE v.post_type = 'product_variation' AND v.post_status = 'publish' AND v.post_parent IN ({$placeholders})
                 GROUP BY slug",
                array_merge(['attribute_' . $taxonomy], $ids)
            )
        );
        // phpcs:enable
        $out = [];
        foreach ((array) $rows as $row) {
            $out[(string) $row->slug] = (int) $row->c;
        }
        return $out;
    }

    /**
     * The collection's lowest and highest price (WooCommerce's lookup table),
     * whole euros. Cached per catalog generation.
     *
     * @return array{0: int, 1: int}
     */
    public static function price_range(?array $scope): array
    {
        $key    = 'hive_price_' . md5((string) wp_json_encode([$scope, Store::generation()]));
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'wc_product_meta_lookup';
        $range = [0, 0];
        if ($scope) {
            $ids = self::matching_ids(Rules::parse([], []), $scope);
            if ($ids) {
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $row          = $wpdb->get_row($wpdb->prepare("SELECT MIN(min_price) lo, MAX(max_price) hi FROM {$table} WHERE product_id IN ({$placeholders}) AND min_price IS NOT NULL", $ids));
                // phpcs:enable
                $range = $row ? [(float) $row->lo, (float) $row->hi] : $range;
            }
        } else {
            $row   = $wpdb->get_row("SELECT MIN(min_price) lo, MAX(max_price) hi FROM {$table} WHERE min_price IS NOT NULL"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
            $range = $row ? [(float) $row->lo, (float) $row->hi] : $range;
        }
        $range = [(int) floor($range[0]), (int) ceil($range[1])];
        set_transient($key, $range, DAY_IN_SECONDS);
        return $range;
    }

    /** Products the shop shows: not hidden from the catalog, and in stock when the shop hides sold-out ones. */
    private static function visibility_clause(): array
    {
        $hidden = ['exclude-from-catalog'];
        if ('yes' === get_option('woocommerce_hide_out_of_stock_items')) {
            $hidden[] = 'outofstock';
        }
        return ['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => $hidden, 'operator' => 'NOT IN'];
    }
}
