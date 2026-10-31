<?php
/**
 * The shop's pages, the shop and the category, brand, tag and attribute
 * archives, as Hive draws them (module "Pagine del negozio"):
 *
 *   breadcrumb, then the hero (eyebrow, the collection's name as <h1>, its
 *   description, how many products, its image)
 *   the filters: a sidebar on desktop (or a panel, Hive → Negozio), a
 *   panel on phones. Every option is a link to the filtered URL, so every
 *   filtered page is a plain page the CDN can cache; @hive/catalog swaps
 *   the results in place instead of reloading
 *   the toolbar: the count, the "Filtri" button, the sort order
 *   the grid of Hive cards, then the pages
 *
 * The products are WordPress's main query, as WooCommerce prepares it
 * (ordering, per page, its own layered-nav filters), with Hive's extras
 * (Filters::query(), Ordering).
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Assets;
use Hive\Core\Filters as Rules;
use Hive\Core\Sizes as SizeRules;
use Hive\Settings;

defined('ABSPATH') || exit;

final class Catalog
{
    /** Options a long list shows before "Mostra tutti". */
    private const LIST_LIMIT = 8;

    /** Options from which a list gets a search box. */
    private const SEARCH_FROM = 12;

    /**
     * The whole page body.
     *
     * @param bool $canvas The page is Hive's alone (block themes: between the
     *                     theme's header and footer parts), so it brings its own
     *                     <main> and width.
     */
    public static function render(bool $canvas = false): string
    {
        global $wp_query;
        $html = Assets::use_style('hive-catalog');
        Assets::use_module('@hive/catalog');

        $scope   = Filters::scope();
        $params  = Filters::params();
        $filters = Rules::parse($params, Filters::taxonomies());
        $base    = self::base_url();
        $drawer  = 'drawer' === Settings::get('filters_layout');

        $classes = 'hive-block hive-catalog' . ($canvas ? ' hive-catalog--canvas' : '') . ($drawer ? ' hive-catalog--drawer' : '');
        $html   .= $canvas ? '<main id="hive-main" class="hive-catalog-main">' : '';
        $html   .= sprintf('<div class="%s" data-hive-catalog>', esc_attr($classes));
        $html   .= Breadcrumb::render('hive-catalog__crumbs');
        $html   .= self::hero($scope, $filters);

        ob_start();
        woocommerce_output_all_notices();
        $notices = (string) ob_get_clean();
        $html   .= '' !== trim(wp_strip_all_tags($notices)) ? '<div class="hive-catalog__notices">' . $notices . '</div>' : '';

        $found = (int) $wp_query->found_posts;
        $html .= '<div class="hive-catalog__layout">';
        $html .= self::panel($filters, $scope, $params, $base, $found);
        $html .= '<div class="hive-catalog__results" data-hive-catalog-results>';
        $html .= self::toolbar($filters, $params, $base, $found);
        $html .= self::grid();
        $html .= self::pages();
        $html .= '</div></div>';
        $html .= '<div class="hive-catalog__backdrop" data-hive-filters-close hidden></div>';
        $html .= '</div>' . ($canvas ? '</main>' : '');
        return $html;
    }

    /** The collection's own URL, page 1, no parameters: the shop, or the term's archive. */
    public static function base_url(): string
    {
        $term = is_product_taxonomy() ? get_queried_object() : null;
        $link = $term instanceof \WP_Term ? get_term_link($term) : get_post_type_archive_link('product');
        return is_string($link) ? $link : home_url('/');
    }

    /** A catalog URL with these parameters (commas kept as they are: one spelling per URL, one cache entry). */
    private static function url(string $base, array $params): string
    {
        $encode = static fn(string $value): string => str_replace('%2C', ',', rawurlencode($value));
        return $params ? add_query_arg(array_map($encode, $params), $base) : $base;
    }

    private static function hero(?array $scope, array $filters): string
    {
        $term = $scope ? get_term($scope['term_id'], $scope['taxonomy']) : null;
        if ($term instanceof \WP_Term) {
            $title       = $term->name;
            $description = (string) $term->description;
            $image       = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
            $eyebrow     = 'Collezione';
        } else {
            $shop        = wc_get_page_id('shop');
            $title       = $shop > 0 ? get_the_title($shop) : 'Negozio';
            $description = $shop > 0 ? (string) get_post_field('post_excerpt', $shop) : '';
            $image       = $shop > 0 ? (int) get_post_thumbnail_id($shop) : 0;
            $eyebrow     = 'Il nostro catalogo';
        }
        $image   = (int) apply_filters('hive_catalog_image', $image, $term);
        $eyebrow = (string) apply_filters('hive_catalog_eyebrow', $eyebrow, $term);
        $counts  = Filters::counts(Rules::parse([], []), $scope);
        $total   = $counts ? (int) $counts['total'] : ($term instanceof \WP_Term ? (int) $term->count : 0);

        $html = sprintf('<header class="hive-catalog__hero%s">', $image ? ' has-image' : '');
        if ($image) {
            $html .= '<div class="hive-catalog__hero-media" aria-hidden="true">'
                . wp_get_attachment_image($image, 'full', false, ['class' => 'hive-catalog__hero-image', 'alt' => '', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async', 'sizes' => '100vw'])
                . '</div>';
        }
        $html .= '<div class="hive-catalog__hero-body">';
        if ('' !== $eyebrow) {
            $html .= '<p class="hive-catalog__eyebrow">' . esc_html($eyebrow) . '</p>';
        }
        $html .= '<h1 class="hive-catalog__title">' . esc_html($title) . '</h1>';
        if ('' !== trim(wp_strip_all_tags($description))) {
            $html .= '<div class="hive-catalog__intro">' . wp_kses_post(wpautop($description)) . '</div>';
        }
        if ($total > 0) {
            $html .= '<p class="hive-catalog__total">' . esc_html(1 === $total ? '1 prodotto' : $total . ' prodotti') . '</p>';
        }
        return $html . '</div></header>';
    }

    /* The filters panel ----------------------------------------------- */

    private static function panel(array $filters, ?array $scope, array $params, string $base, int $found): string
    {
        $html  = '<aside class="hive-catalog__filters" id="hive-filters" data-hive-filters aria-labelledby="hive-filters-title">';
        $html .= '<div class="hive-filters__head"><h2 class="hive-filters__title" id="hive-filters-title">Filtri</h2>';
        $html .= '<button type="button" class="hive-filters__close" data-hive-filters-close aria-label="Chiudi i filtri"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.4 5 5 6.4 10.6 12 5 17.6 6.4 19l5.6-5.6 5.6 5.6 1.4-1.4-5.6-5.6L19 6.4 17.6 5 12 10.6z"/></svg></button></div>';
        $html .= '<div class="hive-filters__body" data-hive-filters-body>' . self::facets($filters, $scope, $params, $base) . '</div>';
        $html .= '<div class="hive-filters__foot" data-hive-filters-foot><button type="button" class="hive-button hive-filters__done" data-hive-filters-close>'
            . esc_html(0 === $found ? 'Nessun prodotto' : (1 === $found ? 'Vedi 1 prodotto' : 'Vedi ' . $found . ' prodotti'))
            . '</button></div>';
        return $html . '</aside>';
    }

    /** The active filters as removable chips, then every facet. */
    private static function facets(array $filters, ?array $scope, array $params, string $base): string
    {
        $facets  = Filters::facets();
        $empty   = Rules::parse([], []);
        $base_c  = Filters::counts($empty, $scope);
        $current = Rules::active($filters) ? Filters::counts($filters, $scope) : $base_c;
        $html    = self::chips($filters, $params, $base);

        foreach ($facets as $key => $facet) {
            switch ($facet['type']) {
                case 'price':
                    $html .= self::price_facet($filters, $scope, $params, $base);
                    break;
                case 'stock':
                    $html .= self::stock_facet($filters, $params, $base, $base_c);
                    break;
                default:
                    $html .= self::term_facet((string) $key, $facet, $filters, $params, $base, $base_c, $current);
            }
        }
        return $html;
    }

    private static function chips(array $filters, array $params, string $base): string
    {
        if (!Rules::active($filters)) {
            return '';
        }
        $facets = Filters::facets();
        $chips  = [];
        foreach ($filters['tax'] as $key => $filter) {
            foreach ($filter['terms'] as $slug) {
                $term    = get_term_by('slug', $slug, $filter['taxonomy']);
                $label   = ($facets[$key]['label'] ?? $key) . ': ' . ($term instanceof \WP_Term ? $term->name : $slug);
                $chips[] = [$label, Rules::toggle($params, $key, $slug)];
            }
        }
        if (null !== $filters['min_price'] || null !== $filters['max_price']) {
            $range   = trim((null !== $filters['min_price'] ? 'da ' . (int) $filters['min_price'] . ' €' : '') . ' ' . (null !== $filters['max_price'] ? 'fino a ' . (int) $filters['max_price'] . ' €' : ''));
            $chips[] = ['Prezzo: ' . $range, Rules::without($params, 'price')];
        }
        if ($filters['instock']) {
            $chips[] = ['Solo disponibili', Rules::without($params, 'instock')];
        }

        $html = '<div class="hive-filters__active"><ul class="hive-filters__chips">';
        foreach ($chips as [$label, $next]) {
            $html .= sprintf(
                '<li><a class="hive-filters__chip" href="%s" data-hive-nav>%s<span class="hive-visually-hidden"> (togli)</span><span aria-hidden="true"> ×</span></a></li>',
                esc_url(self::url($base, $next)),
                esc_html($label)
            );
        }
        $html .= '</ul>';
        $html .= sprintf('<a class="hive-filters__clear" href="%s" data-hive-nav>Togli tutti i filtri</a></div>', esc_url(self::url($base, Rules::cleared($params))));
        return $html;
    }

    private static function price_facet(array $filters, ?array $scope, array $params, string $base): string
    {
        [$floor, $ceil] = Filters::price_range($scope);
        if ($ceil <= $floor) {
            return '';
        }
        $min  = max($floor, min($ceil, (int) floor($filters['min_price'] ?? $floor)));
        $max  = max($floor, min($ceil, (int) ceil($filters['max_price'] ?? $ceil)));
        $keep = Rules::without($params, 'price');

        $html  = '<details class="hive-facet hive-facet--price" open><summary class="hive-facet__label">Prezzo</summary>';
        $html .= sprintf('<form class="hive-price" method="get" action="%s" data-hive-price data-floor="%d" data-ceil="%d">', esc_url($base), $floor, $ceil);
        $html .= self::hidden($base, $keep);
        $html .= '<div class="hive-price__slider"><div class="hive-price__track"><div class="hive-price__fill"></div></div>';
        $html .= sprintf('<input type="range" class="hive-price__range" name="min_price" min="%1$d" max="%2$d" value="%3$d" aria-label="Prezzo minimo">', $floor, $ceil, $min);
        $html .= sprintf('<input type="range" class="hive-price__range" name="max_price" min="%1$d" max="%2$d" value="%3$d" aria-label="Prezzo massimo">', $floor, $ceil, $max);
        $html .= '</div><div class="hive-price__fields">';
        $html .= sprintf('<label class="hive-price__field"><span>Da</span><input type="number" inputmode="numeric" min="%1$d" max="%2$d" value="%3$d" data-hive-price-field="min"><span aria-hidden="true">€</span></label>', $floor, $ceil, $min);
        $html .= sprintf('<label class="hive-price__field"><span>A</span><input type="number" inputmode="numeric" min="%1$d" max="%2$d" value="%3$d" data-hive-price-field="max"><span aria-hidden="true">€</span></label>', $floor, $ceil, $max);
        $html .= '</div><button type="submit" class="hive-button hive-price__apply">Applica</button></form></details>';
        return $html;
    }

    private static function stock_facet(array $filters, array $params, string $base, ?array $counts): string
    {
        // Everything in the collection is in stock already: the option would do nothing.
        if (!$filters['instock'] && $counts && null !== $counts['instock'] && $counts['instock'] >= $counts['total']) {
            return '';
        }
        $next = $filters['instock'] ? Rules::without($params, 'instock') : array_merge(Rules::without($params, 'instock'), ['instock' => '1']);
        return '<details class="hive-facet" open><summary class="hive-facet__label">Disponibilità</summary><ul class="hive-facet__options hive-facet__options--list">'
            . self::option(self::url($base, $next), 'Solo disponibili', $filters['instock'], null)
            . '</ul></details>';
    }

    private static function term_facet(string $key, array $facet, array $filters, array $params, string $base, ?array $base_counts, ?array $counts): string
    {
        $taxonomy = (string) ($facet['taxonomy'] ?? '');
        if ('' === $taxonomy || !taxonomy_exists($taxonomy)) {
            return '';
        }
        $variation = !empty($facet['variation']);
        $active    = $filters['tax'][$key]['terms'] ?? [];
        $names     = self::term_names($taxonomy);
        $present   = isset($base_counts['terms'][$key]) ? array_map('strval', array_keys($base_counts['terms'][$key])) : array_keys($names);
        foreach ($active as $slug) {
            if (!in_array($slug, $present, true)) {
                $present[] = $slug;
            }
        }
        if ('product_cat' === $taxonomy) {
            $default = get_term((int) get_option('default_product_cat'), 'product_cat');
            $present = array_values(array_diff($present, $default instanceof \WP_Term ? [$default->slug] : []));
        }

        // The store's own order of terms; sizes by size.
        $order = array_flip(array_keys($names));
        usort($present, static fn($a, $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));
        if ($variation) {
            $present = array_column(SizeRules::sort(array_map(static fn($slug) => ['slug' => $slug, 'label' => $names[$slug] ?? $slug], $present)), 'slug');
        }

        $total   = (int) ($base_counts['total'] ?? 0);
        $options = [];
        foreach ($present as $slug) {
            $on = in_array($slug, $active, true);
            // Carried by every product here (the category you're in): it can't narrow anything. Sizes always show.
            if (!$on && !$variation && $total > 0 && ($base_counts['terms'][$key][$slug] ?? 0) >= $total) {
                continue;
            }
            $count     = null === $counts ? null : (int) ($counts['terms'][$key][$slug] ?? 0);
            $options[] = self::option(self::url($base, Rules::toggle($params, $key, $slug)), $names[$slug] ?? $slug, $on, $count);
        }
        if (!$options) {
            return '';
        }

        $ui     = (string) ($facet['ui'] ?? 'list');
        $fold   = 'list' === $ui && count($options) > self::LIST_LIMIT + 2;
        $search = 'list' === $ui && count($options) >= self::SEARCH_FROM;
        $html   = sprintf('<details class="hive-facet hive-facet--%s" data-hive-facet open><summary class="hive-facet__label">%s</summary>', esc_attr($ui), esc_html((string) $facet['label']));
        if ($search) {
            $html .= sprintf('<input type="search" class="hive-facet__search" data-hive-facet-search placeholder="%1$s" aria-label="%1$s" autocomplete="off">', esc_attr('Cerca ' . strtolower((string) $facet['label']) . '…'));
        }
        $html .= sprintf('<ul class="hive-facet__options hive-facet__options--%s">', esc_attr($ui));
        foreach ($options as $index => $option) {
            $html .= $fold && $index >= self::LIST_LIMIT ? str_replace('<li>', '<li class="is-extra">', $option) : $option;
        }
        $html .= '</ul>';
        if ($fold) {
            $html .= sprintf('<button type="button" class="hive-facet__more" data-hive-facet-more aria-expanded="false" data-less="Mostra meno">Mostra tutti (%d)</button>', count($options));
        }
        return $html . '</details>';
    }

    /**
     * Hidden fields for a GET form: the parameters to carry, and those of
     * the base URL itself (a form's action loses its query string on
     * submit: ?post_type=product with plain permalinks).
     */
    private static function hidden(string $base, array $params): string
    {
        $own   = [];
        $query = (string) wp_parse_url($base, PHP_URL_QUERY);
        if ('' !== $query) {
            wp_parse_str($query, $own);
        }
        $html = '';
        foreach (array_merge($own, $params) as $name => $value) {
            if (is_string($value)) {
                $html .= sprintf('<input type="hidden" name="%s" value="%s">', esc_attr((string) $name), esc_attr($value));
            }
        }
        return $html;
    }

    /** One option: a link to the URL with it switched, or a disabled label when nothing would match. */
    private static function option(string $url, string $label, bool $on, ?int $count): string
    {
        $count_html = null === $count ? '' : sprintf('<span class="hive-facet__count">%d</span>', $count);
        if (!$on && 0 === $count) {
            return sprintf('<li><span class="hive-facet__option is-disabled" aria-disabled="true"><span class="hive-facet__name">%s</span>%s</span></li>', esc_html($label), $count_html);
        }
        return sprintf(
            '<li><a class="hive-facet__option%s" href="%s" data-hive-nav rel="nofollow"><span class="hive-facet__name">%s</span>%s%s</a></li>',
            $on ? ' is-on' : '',
            esc_url($url),
            esc_html($label),
            $on ? '<span class="hive-visually-hidden"> (scelto)</span>' : '',
            $count_html
        );
    }

    /** @return array<string, string> slug → name, in the store's order. */
    private static function term_names(string $taxonomy): array
    {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);
        $names = [];
        foreach (is_array($terms) ? $terms : [] as $term) {
            $names[(string) $term->slug] = html_entity_decode($term->name, ENT_QUOTES, 'UTF-8');
        }
        return $names;
    }

    /* The results ----------------------------------------------------- */

    private static function toolbar(array $filters, array $params, string $base, int $found): string
    {
        global $wp_query;
        $active = count($filters['tax'] ? array_merge(...array_column($filters['tax'], 'terms')) : []) + (null !== $filters['min_price'] || null !== $filters['max_price'] ? 1 : 0) + ($filters['instock'] ? 1 : 0);
        $page   = max(1, (int) get_query_var('paged'));
        $per    = (int) $wp_query->get('posts_per_page');

        $html  = '<div class="hive-catalog__toolbar">';
        $html .= sprintf(
            '<button type="button" class="hive-catalog__filters-toggle" data-hive-filters-open aria-controls="hive-filters" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18M7 12h10M10 18h4"/></svg>Filtri%s</button>',
            $active ? sprintf('<span class="hive-catalog__filters-count">%d</span>', $active) : ''
        );
        $html .= '<p class="hive-catalog__count" role="status">' . esc_html(Rules::count_label($found, $per, $page)) . '</p>';
        $html .= self::sort_form($params, $base);
        return $html . '</div>';
    }

    private static function sort_form(array $params, string $base): string
    {
        $default = (string) apply_filters('woocommerce_default_catalog_orderby', get_option('woocommerce_default_catalog_orderby', 'menu_order'));
        $current = $params['orderby'] ?? $default;
        $options = (array) apply_filters(
            'woocommerce_catalog_orderby',
            [
                'menu_order' => 'In evidenza',
                'popularity' => 'Più venduti',
                'rating'     => 'Più votati',
                'date'       => 'Più recenti',
                'price'      => 'Prezzo: dal più basso',
                'price-desc' => 'Prezzo: dal più alto',
            ]
        );
        if (!wc_review_ratings_enabled()) {
            unset($options['rating']);
        }

        $html  = sprintf('<form class="hive-catalog__sort" method="get" action="%s" data-hive-sort-form>', esc_url($base));
        $html .= self::hidden($base, array_diff_key($params, ['orderby' => true]));
        $html .= '<label class="hive-catalog__sort-label"><span class="hive-visually-hidden">Ordina per</span><select name="orderby" data-hive-sort>';
        foreach ($options as $value => $label) {
            $html .= sprintf('<option value="%s"%s>%s</option>', esc_attr((string) $value), selected($current, $value, false), esc_html((string) $label));
        }
        return $html . '</select></label><button type="submit" class="hive-catalog__sort-go">Ordina</button></form>';
    }

    private static function grid(): string
    {
        if (!have_posts()) {
            return '<div class="hive-catalog__empty"><p>Nessun prodotto con questi filtri.</p>'
                . sprintf('<a class="hive-button" href="%s" data-hive-nav>Togli tutti i filtri</a></div>', esc_url(self::url(self::base_url(), Rules::cleared(Filters::params()))));
        }
        $columns = max(1, min(6, wc_get_default_products_per_row()));
        $html    = Assets::use_style('hive-shop');
        $html   .= sprintf('<ul class="hive-block hive-grid" style="--hive-grid-cols:%d" role="list">', $columns);
        while (have_posts()) {
            the_post();
            $product = wc_get_product(get_the_ID());
            if ($product instanceof \WC_Product && $product->is_visible()) {
                $html .= Card::render($product, ['heading' => 'h2', 'eager' => Card::count() < $columns]);
            }
        }
        wp_reset_postdata();
        return $html . '</ul>';
    }

    private static function pages(): string
    {
        global $wp_query;
        $total = (int) $wp_query->max_num_pages;
        if ($total < 2) {
            return '';
        }
        $links = paginate_links(
            [
                'base'      => esc_url_raw(str_replace('999999999', '%#%', remove_query_arg('add-to-cart', get_pagenum_link(999999999, false)))),
                'format'    => '',
                'add_args'  => false,
                'current'   => max(1, (int) get_query_var('paged')),
                'total'     => $total,
                'type'      => 'list',
                'end_size'  => 1,
                'mid_size'  => 1,
                'prev_text' => '<span aria-hidden="true">‹</span><span class="hive-visually-hidden">Pagina precedente</span>',
                'next_text' => '<span aria-hidden="true">›</span><span class="hive-visually-hidden">Pagina successiva</span>',
            ]
        );
        if (!is_string($links)) {
            return '';
        }
        // Page links swap in place too.
        $links = str_replace('<a class="page-numbers', '<a data-hive-nav class="page-numbers', $links);
        $links = str_replace('<a class="next page-numbers', '<a data-hive-nav class="next page-numbers', $links);
        $links = str_replace('<a class="prev page-numbers', '<a data-hive-nav class="prev page-numbers', $links);
        return '<nav class="hive-catalog__pages" aria-label="Pagine dei prodotti">' . $links . '</nav>';
    }
}
