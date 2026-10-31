<?php
/**
 * A product rail: a row of product cards that scrolls sideways with CSS
 * scroll-snap, with arrows on desktop (the @hive/rail module). The
 * hive/product-rail block and the [hive_product_rail] shortcode render
 * through here; RailQuery decides the products.
 *
 *   [hive_product_rail title="Novità" limit="12"]
 *   [hive_product_rail category="sneakers" pin="12,5" fallback="price"]
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Assets;

defined('ABSPATH') || exit;

final class Rail
{
    /** Image proportions a rail can use. */
    public const RATIOS = [
        'square'    => '1 / 1',
        'portrait'  => '3 / 4',
        'tall'      => '4 / 5',
        'landscape' => '4 / 3',
        'wide'      => '16 / 9',
    ];

    public static function register(): void
    {
        add_shortcode('hive_product_rail', [self::class, 'shortcode']);
    }

    /** @param array|string $atts */
    public static function shortcode(mixed $atts): string
    {
        return self::render(is_array($atts) ? $atts : []);
    }

    /**
     * @param array         $atts    Rail attributes (RailQuery::DEFAULTS).
     * @param callable|null $wrapper fn(array{class: string, style: string}): string, the wrapper's
     *                               attributes; a block passes get_block_wrapper_attributes() so its
     *                               own classes and styles merge in.
     */
    public static function render(array $atts, ?callable $wrapper = null): string
    {
        if (!function_exists('wc_get_product')) {
            return '';
        }
        $atts = RailQuery::normalize($atts);
        $ids  = RailQuery::ids($atts);
        if (!$ids) {
            return '';
        }

        $products = self::products($ids);
        if (!$products) {
            return '';
        }
        $prelude = Assets::use_style('hive-shop');
        Assets::use_module('@hive/rail');

        $style = sprintf('--hive-rail-cols:%d;', $atts['columns']);
        if ($atts['columns_tablet']) {
            $style .= sprintf('--hive-rail-cols-tablet:%d;', $atts['columns_tablet']);
        }
        if ($atts['columns_mobile']) {
            $style .= sprintf('--hive-rail-cols-mobile:%d;', $atts['columns_mobile']);
        }
        $style .= '--hive-card-ratio:' . (self::RATIOS[$atts['ratio']] ?? self::ratio($atts['ratio'])) . ';';

        $classes    = 'hive-block hive-rail hive-rail--fit-' . $atts['fit'] . ($atts['columns_mobile'] ? ' hive-rail--mobile-cols' : '');
        $attributes = null === $wrapper
            ? sprintf('class="%s" style="%s"', esc_attr($classes), esc_attr($style))
            : (string) $wrapper(['class' => $classes, 'style' => $style]);

        $html = $prelude . '<section ' . $attributes . '>';
        $all  = $atts['show_all'] ? self::all_link($atts) : '';
        if ('' !== $atts['title'] || '' !== $all) {
            $html .= '<div class="hive-rail__head">';
            if ('' !== $atts['title']) {
                $html .= '<h2 class="hive-rail__title">' . esc_html($atts['title']) . '</h2>';
            }
            if ('' !== $all) {
                $html .= sprintf('<a class="hive-rail__all" href="%s">Vedi tutti</a>', esc_url($all));
            }
            $html .= '</div>';
        }
        $html .= '<div class="hive-rail__viewport">';
        $html .= '<button type="button" class="hive-rail__nav" data-dir="-1" aria-label="Prodotti precedenti" hidden><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m14.5 6-6 6 6 6"/></svg></button>';
        $html .= '<ul class="hive-rail__track" role="list">';
        foreach ($products as $product) {
            $html .= Card::render($product);
        }
        $html .= '</ul>';
        $html .= '<button type="button" class="hive-rail__nav" data-dir="1" aria-label="Prodotti successivi" hidden><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9.5 6 6 6-6 6"/></svg></button>';
        $html .= '</div></section>';
        return $html;
    }

    /**
     * The products, in the given order, their posts, meta and terms fetched
     * in one query.
     *
     * @param list<int> $ids
     * @return list<\WC_Product>
     */
    public static function products(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        new \WP_Query(
            [
                'post_type'           => 'product',
                'post__in'            => $ids,
                'orderby'             => 'post__in',
                'posts_per_page'      => count($ids),
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            ]
        );
        $products = [];
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if ($product instanceof \WC_Product && $product->is_visible()) {
                $products[] = $product;
            }
        }
        return $products;
    }

    /** "3/4" → '3 / 4'; anything else, square. */
    private static function ratio(string $raw): string
    {
        return 1 === preg_match('#^\s*(\d{1,2})\s*/\s*(\d{1,2})\s*$#', $raw, $m) && (int) $m[2] > 0 ? $m[1] . ' / ' . $m[2] : '1 / 1';
    }

    /** "Vedi tutti": the rail's own link, or the page of its one category or brand. */
    private static function all_link(array $atts): string
    {
        if ('' !== $atts['link']) {
            return $atts['link'];
        }
        $single = static fn(string $list): string => str_contains($list, ',') ? '' : $list;
        if ('' !== $single($atts['category'])) {
            $link = get_term_link($atts['category'], 'product_cat');
            return is_string($link) ? $link : '';
        }
        if ('' !== $single($atts['brand'])) {
            foreach (Store::brand_taxonomies() as $taxonomy) {
                $link = get_term_link($atts['brand'], $taxonomy);
                if (is_string($link)) {
                    return $link;
                }
            }
        }
        return '';
    }
}
