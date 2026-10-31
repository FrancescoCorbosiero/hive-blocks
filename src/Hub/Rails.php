<?php
/**
 * A homepage section as the Store Hub reads it: its settings, the terms it
 * shows, its products as cards, why a pinned product isn't in it; and a new
 * source checked against the shop. Which products a rail shows, and in what
 * order, is Shop\RailQuery's (with Core\RailOrder's rule), exactly as the
 * site renders it.
 *
 * @package Hive
 */

namespace Hive\Hub;

use Hive\Core\HubNames;
use Hive\Core\HubPage;
use Hive\Core\HubRail;
use Hive\Core\RailOrder;
use Hive\Shop\RailQuery;
use Hive\Shop\Store;

defined('ABSPATH') || exit;

final class Rails
{
    /**
     * The section payload the Hub reads: settings, terms and, optionally, the
     * products it renders.
     *
     * @param array $rail Core\HubRail::from_block() output.
     */
    public static function payload(string $key, string $path, array $block, array $rail, bool $with_products): array
    {
        $atts  = RailQuery::normalize($rail['atts']);
        $terms = self::terms($atts);
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        $text  = static fn(string $name, string $default = ''): string => isset($attrs[$name]) && is_scalar($attrs[$name]) ? (string) $attrs[$name] : $default;

        $payload = [
            'key'              => $key,
            'path'             => $path,
            'attrs_hash'       => HubPage::attrs_hash($attrs),
            'title'            => $text('title'),
            'eyebrow'          => $text('eyebrow'),
            'background'       => $text('backgroundColor', 'white'),
            'button'           => [
                'text' => $text('buttonText'),
                'url'  => $text('buttonUrl'),
            ],
            'shortcode'        => HubNames::shortcode_to_app($rail['shortcode']),
            'atts'             => $rail['atts'],
            'limit'            => max(1, (int) $atts['limit']),
            'taxonomy'         => $terms['taxonomy'],
            'terms'            => $terms['terms'],
            'pin'              => $rail['pin'],
            'exclude'          => $rail['exclude'],
            'fallback'         => $rail['fallback'],
            'fallback_default' => $rail['fallback_default'],
            // A fixed list of products is the editor's to change.
            'editable'         => empty($rail['atts']['ids']),
            'fields'           => (object) (Fields::of($block) ?? []),
            // Switched off from the Vetrina: on the page, rendering nothing. (Not
            // "hidden": /rail's answer lists the products hidden from it under that.)
            'switched_off'     => !empty($attrs['hidden']),
        ];
        if ($with_products) {
            $payload['products'] = self::cards(RailQuery::ids($atts, true));
        }
        return $payload;
    }

    /**
     * The taxonomy and terms a rail shows, resolved the way the rail query
     * resolves them (brand slugs may live in any of the brand taxonomies).
     *
     * @param array $atts RailQuery::normalize() output.
     * @return array{taxonomy: string|null, terms: list<array>}
     */
    public static function terms(array $atts): array
    {
        $out = ['taxonomy' => null, 'terms' => []];
        if (!empty($atts['category'])) {
            $out['taxonomy'] = 'product_cat';
            $slugs           = array_map('trim', explode(',', (string) $atts['category']));
            $taxonomies      = ['product_cat'];
        } elseif (!empty($atts['brand'])) {
            $slugs      = array_map('trim', explode(',', (string) $atts['brand']));
            $taxonomies = Store::brand_taxonomies();
        } else {
            return $out;
        }
        foreach ($taxonomies as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            foreach ($slugs as $slug) {
                $term = get_term_by('slug', $slug, $taxonomy);
                if (!$term instanceof \WP_Term) {
                    continue;
                }
                $out['taxonomy'] = $taxonomy;
                $link            = get_term_link($term);
                $out['terms'][]  = [
                    'id'    => (int) $term->term_id,
                    'slug'  => $term->slug,
                    'name'  => html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'),
                    'count' => (int) $term->count,
                    'link'  => is_string($link) ? $link : null,
                ];
            }
            if ([] !== $out['terms']) {
                break;
            }
        }
        return $out;
    }

    /**
     * Cards for a set of products, in the given order: what the Hub needs to
     * draw and join them: id, SKU (the Hub's own key), name, the shop's
     * thumbnail size (never the full image on mobile data), price and stock.
     *
     * @param list<int> $ids
     * @return list<array>
     */
    public static function cards(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        // One query primes post, meta and term caches for every card.
        new \WP_Query(
            [
                'post_type'      => ['product'],
                'post_status'    => 'any',
                'post__in'       => $ids,
                'orderby'        => 'post__in',
                'posts_per_page' => count($ids),
                'no_found_rows'  => true,
            ]
        );

        $cards = [];
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if (!$product instanceof \WC_Product) {
                $cards[] = ['id' => (int) $id, 'missing' => true];
                continue;
            }
            $image_id = (int) $product->get_image_id();
            $src      = $image_id ? wp_get_attachment_image_src($image_id, 'woocommerce_thumbnail') : false;
            $price    = $product->get_price();
            $max      = $product->is_type('variable') && $product instanceof \WC_Product_Variable ? $product->get_variation_price('max') : $price;
            $created  = $product->get_date_created();
            $cards[]  = [
                'id'           => $product->get_id(),
                'sku'          => (string) $product->get_sku(),
                'name'         => html_entity_decode(wp_strip_all_tags($product->get_name()), ENT_QUOTES, 'UTF-8'),
                'type'         => $product->get_type(),
                'status'       => $product->get_status(),
                'visibility'   => $product->get_catalog_visibility(),
                'stock_status' => $product->get_stock_status(),
                'price'        => '' === $price ? null : (float) $price,
                'price_max'    => '' === $max || null === $max ? null : (float) $max,
                'on_sale'      => $product->is_on_sale(),
                'image'        => $src ? $src[0] : wc_placeholder_img_src('woocommerce_thumbnail'),
                'permalink'    => get_permalink($product->get_id()),
                'edit_link'    => admin_url('post.php?post=' . $product->get_id() . '&action=edit'),
                'created'      => $created ? $created->date('c') : null,
            ];
        }
        return $cards;
    }

    /**
     * Why a pinned product is not in its rail: the answer the Vetrina shows
     * next to the greyed-out card.
     *
     * @param array $atts  RailQuery::normalize() output.
     * @param array $terms terms() output.
     */
    public static function hidden_reason(int $id, array $atts, array $terms): string
    {
        $product = wc_get_product($id);
        if (!$product instanceof \WC_Product) {
            return 'missing';
        }
        if ('publish' !== $product->get_status()) {
            return 'unpublished';
        }
        if (in_array($id, RailOrder::parse_ids($atts['exclude'] ?? ''), true)) {
            return 'excluded';
        }
        if (!empty($terms['taxonomy']) && [] !== $terms['terms']) {
            $wanted = [];
            foreach ($terms['terms'] as $term) {
                $wanted[] = $term['id'];
                $children = get_term_children($term['id'], $terms['taxonomy']);
                if (is_array($children)) {
                    foreach ($children as $child) {
                        $wanted[] = (int) $child;
                    }
                }
            }
            $has = wp_get_post_terms($id, $terms['taxonomy'], ['fields' => 'ids']);
            if (!is_array($has) || [] === array_intersect($wanted, array_map('intval', $has))) {
                return 'not_in_section';
            }
        }
        if (in_array($product->get_catalog_visibility(), ['hidden', 'search'], true)) {
            return 'hidden';
        }
        if ('outofstock' === $product->get_stock_status()) {
            return 'outofstock';
        }
        return 'unknown';
    }

    /**
     * The term a section's new source names, looked up the way the rail
     * query reads it: a category in product_cat, a brand in whichever brand
     * taxonomy holds that slug, a tag in product_tag.
     */
    public static function source_term(array $source): \WP_Term|\WP_Error
    {
        $taxonomies = [
            'category' => ['product_cat'],
            'brand'    => Store::brand_taxonomies(),
            'tag'      => ['product_tag'],
        ];
        foreach ($taxonomies[$source['taxonomy']] ?? [] as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            $term = get_term_by('slug', $source['slug'], $taxonomy);
            if ($term instanceof \WP_Term) {
                return $term;
            }
        }
        $where = ['category' => 'tra le categorie', 'brand' => 'tra i brand', 'tag' => 'tra i tag'];
        return new \WP_Error(
            'ghb_bad_source',
            "Non trovo «{$source['slug']}» {$where[$source['taxonomy']]} del negozio.",
            ['status' => 400]
        );
    }

    /**
     * A source the Hub sent, checked for its shape (Core\HubRail) and, a
     * term, for its existence in the shop.
     *
     * @return array|\WP_Error Core\HubRail::parse_source() output, with the WP_Term as "term".
     */
    public static function checked_source(mixed $raw): array|\WP_Error
    {
        $source = HubRail::parse_source($raw);
        if (null === $source) {
            return new \WP_Error('ghb_bad_source', 'Cosa mostra la sezione non è valido.', ['status' => 400]);
        }
        if ('term' === $source['kind']) {
            $term = self::source_term($source);
            if (is_wp_error($term)) {
                return $term;
            }
            $source['term'] = $term;
        }
        return $source;
    }

    /**
     * Where a section's button should point once the section shows something
     * else, only when it pointed at what the section showed before ("Esplora
     * le tendenze" still opening the old category would send customers to the
     * wrong page). The new term's page, or the shop's for a shop-wide list; a
     * link that was site-relative stays site-relative. Null: leave the button
     * alone.
     *
     * @param array $old_terms terms() of the rail before the change.
     * @param array $source    checked_source() output.
     */
    public static function followed_button(string $url, array $old_terms, array $source): ?string
    {
        $path    = static fn(mixed $link): string => untrailingslashit((string) wp_parse_url((string) $link, PHP_URL_PATH));
        $current = $path($url);
        if ('' === trim($url) || '' === $current) {
            return null;
        }
        $pointed = false;
        foreach ($old_terms['terms'] ?? [] as $term) {
            if (!empty($term['link']) && $path($term['link']) === $current) {
                $pointed = true;
            }
        }
        if (!$pointed) {
            return null;
        }
        if ('term' === $source['kind']) {
            $link = get_term_link($source['term']);
        } else {
            $orderby = ['recent' => 'date', 'best_selling' => 'popularity', 'top_rated' => 'rating'];
            $link    = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
            if (isset($orderby[$source['type']])) {
                $link = add_query_arg('orderby', $orderby[$source['type']], $link);
            }
        }
        if (!is_string($link) || '' === $link) {
            return null;
        }
        return str_starts_with(ltrim($url), '/') ? wp_make_link_relative($link) : $link;
    }
}
