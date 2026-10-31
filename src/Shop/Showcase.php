<?php
/**
 * The store's categories and brands as the content blocks show them, each
 * with its link and its picture:
 *
 *   categories()  the category slider (blocks/category-slider): the chosen
 *                 product categories, or the top-level ones with products,
 *                 in the shop's order; the picture is the category's own
 *                 (Prodotti → Categorie) or, without one, the photo of one
 *                 of its products
 *   brands()      the brand marquee (blocks/brand-marquee): the chosen
 *                 brands, or every brand with products, from the brand
 *                 taxonomy the site has (Store::brand_taxonomies()); the
 *                 logo is the brand's image (WooCommerce Brands, Perfect
 *                 Brands; filter hive_brand_logo), else the name is shown
 *
 * @package Hive
 */

namespace Hive\Shop;

defined('ABSPATH') || exit;

final class Showcase
{
    /** Most categories or brands a block shows. */
    public const MAX = 24;

    /**
     * @param string $slugs Category slugs, comma separated, in the order to show; '' for the automatic list.
     * @return list<array{id: int, name: string, url: string, image: int}>
     */
    public static function categories(string $slugs): array
    {
        $picked = self::slugs($slugs);
        $terms  = get_terms(
            $picked
                ? ['taxonomy' => 'product_cat', 'slug' => $picked, 'hide_empty' => false, 'orderby' => 'slug__in']
                : [
                    'taxonomy'   => 'product_cat',
                    'parent'     => 0,
                    'hide_empty' => true,
                    // "Uncategorized" is where products without a category land: nothing to show off.
                    'exclude'    => array_filter([(int) get_option('default_product_cat', 0)]),
                    'number'     => self::MAX,
                ]
        );
        if (!is_array($terms)) {
            return [];
        }

        $items   = [];
        $missing = [];
        foreach (array_slice($terms, 0, self::MAX) as $term) {
            if (!$term instanceof \WP_Term) {
                continue;
            }
            $link    = get_term_link($term);
            $image   = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
            $items[] = [
                'id'    => (int) $term->term_id,
                'name'  => html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'),
                'url'   => is_string($link) ? $link : '',
                'image' => $image,
            ];
            if (!$image) {
                $missing[] = (int) $term->term_id;
            }
        }

        if ($missing) {
            $photos = self::product_photos($missing);
            foreach ($items as $i => $item) {
                if (!$item['image']) {
                    $items[$i]['image'] = $photos[$item['id']] ?? 0;
                }
            }
        }
        return $items;
    }

    /**
     * A photo for each category that has none: the main image of its newest
     * product with one. Cached per catalog generation (a product change makes
     * a new key).
     *
     * @param list<int> $term_ids
     * @return array<int, int> term id → attachment id
     */
    private static function product_photos(array $term_ids): array
    {
        $key    = 'hive_cat_photos_' . md5(Store::generation() . '|' . implode(',', $term_ids));
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }
        $photos = [];
        foreach ($term_ids as $term_id) {
            $ids = get_posts(
                [
                    'post_type'      => 'product',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                    'orderby'        => ['date' => 'DESC', 'ID' => 'DESC'],
                    'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery
                        ['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => [$term_id], 'include_children' => true],
                        ['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => ['exclude-from-catalog'], 'operator' => 'NOT IN'],
                    ],
                    'meta_query'     => [['key' => '_thumbnail_id', 'compare' => 'EXISTS']], // phpcs:ignore WordPress.DB.SlowDBQuery
                ]
            );
            $photos[$term_id] = $ids ? (int) get_post_thumbnail_id((int) $ids[0]) : 0;
        }
        set_transient($key, $photos, DAY_IN_SECONDS);
        return $photos;
    }

    /**
     * @param string $slugs Brand slugs, comma separated, in the order to show; '' for every brand with products.
     * @return list<array{name: string, url: string, logo: int}>
     */
    public static function brands(string $slugs): array
    {
        $taxonomies = Store::brand_taxonomies();
        $picked     = self::slugs($slugs);
        $terms      = [];
        if ($picked) {
            foreach ($picked as $slug) {
                foreach ($taxonomies as $taxonomy) {
                    $term = get_term_by('slug', $slug, $taxonomy);
                    if ($term instanceof \WP_Term) {
                        $terms[] = $term;
                        break;
                    }
                }
            }
        } else {
            // The first brand taxonomy that has brands: a site with two (WooCommerce Brands and a
            // "Marca" attribute, say) would otherwise show every brand twice.
            foreach ($taxonomies as $taxonomy) {
                $found = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => self::MAX, 'orderby' => 'name']);
                if (is_array($found) && $found) {
                    $terms = $found;
                    break;
                }
            }
        }

        $brands = [];
        foreach (array_slice($terms, 0, self::MAX) as $term) {
            if ($term instanceof \WP_Term) {
                $brands[] = [
                    'name' => html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'),
                    'url'  => self::brand_link($term),
                    'logo' => self::brand_logo($term),
                ];
            }
        }
        return $brands;
    }

    /** The brand's page; for a brand attribute without its own pages, the shop filtered by it. */
    private static function brand_link(\WP_Term $term): string
    {
        $taxonomy = get_taxonomy($term->taxonomy);
        if (function_exists('taxonomy_is_product_attribute') && taxonomy_is_product_attribute($term->taxonomy) && !($taxonomy && $taxonomy->public)) {
            $shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '';
            return '' !== $shop ? add_query_arg('filter_' . substr($term->taxonomy, 3), rawurlencode($term->slug), $shop) : '';
        }
        $link = get_term_link($term);
        return is_string($link) ? $link : '';
    }

    /** The brand's logo (an attachment id), 0 without one. */
    private static function brand_logo(\WP_Term $term): int
    {
        $logo = 0;
        foreach (['thumbnail_id', 'pwb_brand_image'] as $meta) {
            $logo = (int) get_term_meta($term->term_id, $meta, true);
            if ($logo) {
                break;
            }
        }
        return (int) apply_filters('hive_brand_logo', $logo, $term);
    }

    /** "nike, new-balance" → ['nike', 'new-balance']. @return list<string> */
    private static function slugs(string $slugs): array
    {
        return array_values(array_unique(array_filter(array_map('sanitize_title', explode(',', $slugs)))));
    }
}
