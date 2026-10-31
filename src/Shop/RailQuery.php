<?php
/**
 * Which products a rail shows, in what order. The same rules as
 * golden-hive-blocks' [gh_product_rail] (ghb_carousel_query_args() and the
 * Store Hub's ordering), because the Store Hub app previews rails with them:
 *
 *   source   type (recent | featured | best_selling | sale | top_rated),
 *            category, brand, tag (slugs, comma separated) or ids
 *   order    pin (shown first), exclude (never shown), fallback (the order
 *            of everything else: menu_order | date | popularity | price |
 *            price-desc | rating). Core\RailOrder holds the rule.
 *
 * A rail only shows what the shop would: published, visible in the
 * catalog, and in stock when the shop hides sold-out products.
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Core\RailOrder;

defined('ABSPATH') || exit;

final class RailQuery
{
    /** Most products a rail considers before ordering: a runaway guard, not a limit. */
    private const MAX_CANDIDATES = 2000;

    /** Every rail attribute (block and shortcode alike), with its default. */
    public const DEFAULTS = [
        'title'          => '',
        'type'           => 'recent',
        'category'       => '',
        'brand'          => '',
        'tag'            => '',
        'ids'            => '',
        'pin'            => '',
        'exclude'        => '',
        'fallback'       => '',
        'limit'          => 12,
        'columns'        => 4,
        'columns_tablet' => 0,
        'columns_mobile' => 0,
        'ratio'          => 'square',
        'fit'            => 'contain',
        'show_all'       => true,
        'link'           => '',
    ];

    /** Attributes from a block or a shortcode, cleaned, every key present. */
    public static function normalize(array $atts): array
    {
        $atts = array_merge(self::DEFAULTS, array_intersect_key($atts, self::DEFAULTS));

        $atts['title'] = trim((string) $atts['title']);
        $atts['type']  = in_array($atts['type'], RailOrder::TYPES, true) ? $atts['type'] : 'recent';
        foreach (['category', 'brand', 'tag'] as $key) {
            $atts[$key] = implode(',', array_filter(array_map('sanitize_title', explode(',', (string) $atts[$key]))));
        }
        foreach (['ids', 'pin', 'exclude'] as $key) {
            $atts[$key] = implode(',', RailOrder::parse_ids($atts[$key]));
        }
        $atts['fallback']       = in_array($atts['fallback'], RailOrder::FALLBACKS, true) ? $atts['fallback'] : '';
        $atts['limit']          = max(1, min(48, (int) $atts['limit']));
        $atts['columns']        = max(1, min(8, (int) $atts['columns']));
        $atts['columns_tablet'] = max(0, min(8, (int) $atts['columns_tablet']));
        $atts['columns_mobile'] = max(0, min(8, (int) $atts['columns_mobile']));
        $atts['fit']            = 'cover' === $atts['fit'] ? 'cover' : 'contain';
        $atts['show_all']       = filter_var($atts['show_all'], FILTER_VALIDATE_BOOLEAN);
        $atts['link']           = esc_url_raw(trim((string) $atts['link']));
        return $atts;
    }

    /**
     * The products a rail shows, in order, at most its limit.
     *
     * @param array $atts normalize() output.
     * @return list<int>
     */
    public static function ids(array $atts): array
    {
        $args = self::query_args($atts);

        // An explicit list is its own order; an untouched rail is exactly its query.
        if ('' !== $atts['ids'] || !RailOrder::has_ordering($atts)) {
            $args['fields']        = 'ids';
            $args['no_found_rows'] = true;
            return array_map('intval', (new \WP_Query($args))->posts);
        }

        $ordered = RailOrder::order(
            self::visible_ids($args, RailOrder::fallback($atts['fallback'], $atts)),
            RailOrder::parse_ids($atts['pin']),
            RailOrder::parse_ids($atts['exclude'])
        );
        return array_slice($ordered, 0, $atts['limit']);
    }

    /** The WP_Query arguments: which products the rail may show and its own order. */
    public static function query_args(array $atts): array
    {
        $args = [
            'post_type'           => 'product',
            'post_status'         => 'publish',
            'posts_per_page'      => $atts['limit'],
            'ignore_sticky_posts' => true,
        ];

        $hidden = ['exclude-from-catalog'];
        if ((bool) apply_filters('hive_rail_hide_out_of_stock', 'yes' === get_option('woocommerce_hide_out_of_stock_items'), $atts)) {
            $hidden[] = 'outofstock';
        }
        $tax_query = [['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => $hidden, 'operator' => 'NOT IN']];

        switch ($atts['type']) {
            case 'best_selling':
                $args += ['meta_key' => 'total_sales', 'orderby' => 'meta_value_num', 'order' => 'DESC']; // phpcs:ignore WordPress.DB.SlowDBQuery
                break;
            case 'featured':
                $tax_query[] = ['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => 'featured'];
                $args       += ['orderby' => 'menu_order title', 'order' => 'ASC'];
                break;
            case 'sale':
                $args += ['post__in' => array_merge([0], wc_get_product_ids_on_sale()), 'orderby' => 'date', 'order' => 'DESC'];
                break;
            case 'top_rated':
                $args += ['meta_key' => '_wc_average_rating', 'orderby' => 'meta_value_num', 'order' => 'DESC']; // phpcs:ignore WordPress.DB.SlowDBQuery
                break;
            default:
                $args += ['orderby' => 'date', 'order' => 'DESC'];
        }

        // A category or brand rail follows the shop's custom order, like its archive page.
        if (('' !== $atts['category'] || '' !== $atts['brand']) && !in_array($atts['type'], ['best_selling', 'top_rated'], true)) {
            $args['orderby'] = 'menu_order title';
            $args['order']   = 'ASC';
        }

        if ('' !== $atts['category']) {
            $tax_query[] = ['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => explode(',', $atts['category'])];
        }
        if ('' !== $atts['tag']) {
            $tax_query[] = ['taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => explode(',', $atts['tag'])];
        }
        if ('' !== $atts['brand']) {
            $brand = self::brand_clause(explode(',', $atts['brand']));
            if ($brand) {
                $tax_query[] = $brand;
            }
        }
        $args['tax_query'] = array_merge(['relation' => 'AND'], $tax_query); // phpcs:ignore WordPress.DB.SlowDBQuery

        if ('' !== $atts['ids']) {
            $args['post__in'] = array_map('intval', explode(',', $atts['ids']));
            $args['orderby']  = 'post__in';
        }
        return $args;
    }

    /**
     * Brand slugs, matched in whichever brand taxonomy holds them (a site may
     * have several), child brands included.
     */
    private static function brand_clause(array $slugs): array
    {
        $clauses = [];
        foreach (Store::brand_taxonomies() as $taxonomy) {
            $ids = [];
            foreach ($slugs as $slug) {
                $term = get_term_by('slug', $slug, $taxonomy);
                if ($term instanceof \WP_Term) {
                    $ids[] = $term->term_id;
                }
            }
            if ($ids) {
                $clauses[] = ['taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => array_unique($ids), 'include_children' => true];
            }
        }
        if (!$clauses) {
            // No brand matched: the rail shows nothing rather than everything.
            return ['taxonomy' => 'product_visibility', 'field' => 'term_id', 'terms' => [0]];
        }
        return 1 === count($clauses) ? $clauses[0] : array_merge(['relation' => 'OR'], $clauses);
    }

    /**
     * Every visible member of a rail in fallback order, before pins and
     * exclusions. Cached per catalog generation.
     *
     * @return list<int>
     */
    private static function visible_ids(array $args, string $fallback): array
    {
        $key    = 'hive_rail_' . md5((string) wp_json_encode([$args, $fallback, Store::generation()]));
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }

        // The source stays (post__in holds the products on sale); only the order is replaced.
        unset($args['meta_key'], $args['orderby'], $args['order']);
        $args['fields']         = 'ids';
        $args['posts_per_page'] = self::MAX_CANDIDATES;
        $args['no_found_rows']  = true;

        $ordering        = self::fallback_order($fallback);
        $args['orderby'] = $ordering['orderby'];
        $args['order']   = $ordering['order'];
        if ('' !== $ordering['meta_key']) {
            $args['meta_key'] = $ordering['meta_key']; // phpcs:ignore WordPress.DB.SlowDBQuery
        }

        $ids = array_map('intval', (new \WP_Query($args))->posts);
        if (function_exists('WC') && WC()->query instanceof \WC_Query) {
            // Popularity and price order through posts_clauses filters WooCommerce added for this query.
            WC()->query->remove_ordering_args();
        }
        set_transient($key, $ids, 10 * MINUTE_IN_SECONDS);
        return $ids;
    }

    /** A fallback as WP_Query ordering, through WooCommerce's own catalog ordering. */
    private static function fallback_order(string $fallback): array
    {
        $map = [
            'menu_order' => ['menu_order', 'ASC'],
            'date'       => ['date', 'DESC'],
            'popularity' => ['popularity', 'DESC'],
            'price'      => ['price', 'ASC'],
            'price-desc' => ['price', 'DESC'],
            'rating'     => ['rating', 'DESC'],
        ];
        [$orderby, $order] = $map[$fallback] ?? $map['menu_order'];
        if (function_exists('WC') && WC()->query instanceof \WC_Query) {
            $args = WC()->query->get_catalog_ordering_args($orderby, $order);
            return ['orderby' => $args['orderby'], 'order' => $args['order'], 'meta_key' => (string) ($args['meta_key'] ?? '')];
        }
        return 'date' === $fallback
            ? ['orderby' => 'date ID', 'order' => 'DESC', 'meta_key' => '']
            : ['orderby' => 'menu_order title', 'order' => 'ASC', 'meta_key' => ''];
    }
}
