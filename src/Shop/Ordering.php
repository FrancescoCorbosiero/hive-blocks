<?php
/**
 * The order of the shop's pages, on top of whatever sort is in force:
 *
 *   sold-out last   sold-out products after the ones you can buy, on the
 *                   shop and its archives (Hive → Negozio). In SQL, so they
 *                   fill the last pages rather than the bottom of each page.
 *                   "Sold out" is WooCommerce's own outofstock visibility term,
 *                   a variable product once every size is gone.
 *   rail pins       a category or brand page follows its homepage rail: the
 *                   products the Store Hub pinned there come first, the
 *                   excluded ones don't show (Core\ArchivePins). Only with the
 *                   shop's default sort: a visitor's choice wins.
 *
 * Both are posts_clauses filters on WooCommerce's main product query.
 * WooCommerce's own sort replaces ORDER BY at priority 10; the pins (20) and
 * sold-out last (50) put their keys in front. A callback never removes itself
 * while it runs: WP_Hook would then skip the next priority.
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Core\ArchivePins;
use Hive\Settings;

defined('ABSPATH') || exit;

final class Ordering
{
    public static function register(): void
    {
        add_action('woocommerce_product_query', [self::class, 'flag'], 30);
        add_filter('posts_clauses', [self::class, 'pins'], 20, 2);
        add_filter('posts_clauses', [self::class, 'sold_out_last'], 50, 2);
    }

    public static function sold_out_last_on(): bool
    {
        // Sold-out products the shop hides need no ordering.
        $on = (bool) Settings::get('sold_out_last') && 'yes' !== get_option('woocommerce_hide_out_of_stock_items');
        return (bool) apply_filters('hive_sold_out_last', $on);
    }

    /** Marks WooCommerce's main product query (not search: the search engine decides that order). */
    public static function flag(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query() || $query->is_search()) {
            return;
        }
        if (self::sold_out_last_on()) {
            $query->set('hive_sold_out_last', true);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only sort parameter.
        $sorted = !empty($_GET['orderby']);
        $state  = $sorted ? null : self::pins_for(get_queried_object());
        if (null === $state) {
            return;
        }
        if ($state['exclude']) {
            $query->set('post__not_in', array_merge((array) $query->get('post__not_in'), $state['exclude']));
        }
        if ($state['pin']) {
            $query->set('hive_pins', $state['pin']);
        }
    }

    public static function pins(mixed $clauses, mixed $query): mixed
    {
        $pins = $query instanceof \WP_Query ? (array) $query->get('hive_pins') : [];
        if (!$pins || !is_array($clauses)) {
            return $clauses;
        }
        global $wpdb;
        $list               = implode(',', array_map('intval', $pins));
        $field              = "FIELD({$wpdb->posts}.ID, {$list})";
        $rest               = trim((string) ($clauses['orderby'] ?? ''));
        $clauses['orderby'] = "{$field} = 0, {$field}" . ('' !== $rest ? ', ' . $rest : '');
        return $clauses;
    }

    public static function sold_out_last(mixed $clauses, mixed $query): mixed
    {
        if (!$query instanceof \WP_Query || !$query->get('hive_sold_out_last') || !is_array($clauses) || !function_exists('wc_get_product_visibility_term_ids')) {
            return $clauses;
        }
        $sold_out = (int) (wc_get_product_visibility_term_ids()['outofstock'] ?? 0);
        if ($sold_out <= 0) {
            return $clauses;
        }
        global $wpdb;
        $key                = "EXISTS (SELECT 1 FROM {$wpdb->term_relationships} hive_oos WHERE hive_oos.object_id = {$wpdb->posts}.ID AND hive_oos.term_taxonomy_id = {$sold_out}) ASC";
        $rest               = trim((string) ($clauses['orderby'] ?? ''));
        $clauses['orderby'] = $key . ('' !== $rest ? ', ' . $rest : '');
        return $clauses;
    }

    /**
     * The pins and exclusions of the homepage rail of this category or brand,
     * or null.
     *
     * @return array{pin: list<int>, exclude: list<int>}|null
     */
    public static function pins_for(mixed $term): ?array
    {
        if (!$term instanceof \WP_Term || !apply_filters('hive_rail_archive_follow', true)) {
            return null;
        }
        $map = self::rail_map();
        if ('product_cat' === $term->taxonomy) {
            return $map['category:' . $term->slug] ?? null;
        }
        return in_array($term->taxonomy, Store::brand_taxonomies(), true) ? ($map['brand:' . $term->slug] ?? null) : null;
    }

    /** The front page's rails, as Core\ArchivePins::map(). Cached until the front page changes. */
    private static function rail_map(): array
    {
        $page = get_post((int) get_option('page_on_front'));
        if (!$page instanceof \WP_Post) {
            return [];
        }
        $key    = 'hive_rail_pins_' . md5($page->ID . '|' . $page->post_modified_gmt);
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }
        $rails = [];
        $walk  = static function (array $blocks) use (&$walk, &$rails): void {
            foreach ($blocks as $block) {
                if ('hive/product-rail' === ($block['blockName'] ?? null)) {
                    $rails[] = RailQuery::normalize((array) ($block['attrs'] ?? []));
                }
                if (!empty($block['innerBlocks'])) {
                    $walk($block['innerBlocks']);
                }
            }
        };
        $walk(parse_blocks($page->post_content));
        $map = ArchivePins::map($rails);
        set_transient($key, $map, DAY_IN_SECONDS);
        return $map;
    }
}
