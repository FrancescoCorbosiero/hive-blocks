<?php
/**
 * The product page as Hive draws it (module "Pagina prodotto"), inside the
 * theme's header and footer:
 *
 *   breadcrumb, WooCommerce's notices
 *   the photos (Gallery) | brand, name as <h1>, rating, price, summary, the
 *     buying part (BuyForm: sizes with prices, quantity, button, stock),
 *     the size guide, then whatever plugins add to the summary
 *   the details as an accordion: WooCommerce's tabs (description, details,
 *     reviews, plugins' tabs), Hive's own "Dettagli"
 *   "Ti consigliamo" (upsells) and "Potrebbe piacerti anche" (related) as
 *     Hive rails
 *   the sticky add-to-cart bar (Hive → Pagina prodotto)
 *
 * WooCommerce's product page hooks are fired in their usual places, without
 * WooCommerce's own callbacks Hive replaces and without the theme's (Hooks).
 * woocommerce_single_product_summary is split around the buying part:
 * priorities up to 29 before it, 30 and later after, as on WooCommerce's
 * own page.
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Assets;
use Hive\Settings;

defined('ABSPATH') || exit;

final class ProductPage
{
    /** WooCommerce's own callbacks Hive draws itself, per hook. */
    private const REPLACED = [
        'woocommerce_before_single_product_summary' => ['woocommerce_show_product_sale_flash', 'woocommerce_show_product_images'],
        'woocommerce_single_product_summary'        => [
            'woocommerce_template_single_title',
            'woocommerce_template_single_rating',
            'woocommerce_template_single_price',
            'woocommerce_template_single_excerpt',
            'woocommerce_template_single_add_to_cart',
            'woocommerce_template_single_meta',
            'woocommerce_template_single_sharing',
        ],
        'woocommerce_after_single_product_summary'  => ['woocommerce_output_product_data_tabs', 'woocommerce_upsell_display', 'woocommerce_output_related_products'],
    ];

    /** Rails under the product. */
    private const RAIL_LIMIT = 12;

    /**
     * @param bool $canvas The page is Hive's alone (block themes: between the
     *                     theme's header and footer parts), so it brings its own <main>.
     */
    public static function render(bool $canvas = false): string
    {
        global $product;
        $product = wc_get_product(get_the_ID());
        if (!$product instanceof \WC_Product) {
            return '';
        }

        $html = Assets::use_style('hive-product');
        Assets::use_module('@hive/product');

        $layout  = 'grid' === Settings::get('gallery') ? 'grid' : 'slider';
        $classes = 'hive-block hive-product hive-product--' . $layout . ($canvas ? ' hive-product--canvas' : '');
        $html   .= $canvas ? '<main id="hive-main" class="hive-product-main">' : '';
        $html   .= sprintf('<div class="%s" data-hive-product="%d">', esc_attr($classes), $product->get_id());
        $html   .= Breadcrumb::render('hive-product__crumbs');

        // WooCommerce's notices (and its empty wrapper, where scripts put new ones).
        $html .= '<div class="hive-product__notices">' . Hooks::run('woocommerce_before_single_product') . '</div>';

        if (post_password_required()) {
            $html .= '<div class="hive-product__locked">' . get_the_password_form() . '</div></div>' . ($canvas ? '</main>' : '');
            return $html;
        }

        $guide     = SizeGuides::for_product($product);
        $state     = Card::state($product);
        $html     .= sprintf('<div id="product-%d" class="hive-product__top">', $product->get_id());
        $html     .= '<div class="hive-product__media">';
        $html     .= Hooks::run('woocommerce_before_single_product_summary', self::REPLACED['woocommerce_before_single_product_summary']);
        $html     .= Gallery::render($product, $layout, Card::badge_list($product, $state));
        $html     .= '</div>';
        $html     .= '<div class="hive-product__summary" data-hive-product-summary>';
        $html     .= self::heading($product);
        $html     .= Hooks::run('woocommerce_single_product_summary', self::REPLACED['woocommerce_single_product_summary'], null, 29);
        $html     .= BuyForm::render($product, $guide ? SizeGuides::button($guide) : '');
        $html     .= Hooks::run('woocommerce_single_product_summary', self::REPLACED['woocommerce_single_product_summary'], 30);
        $html     .= Hooks::run('woocommerce_share');
        $html     .= self::details($product);
        $html     .= '</div></div>';

        $html .= Hooks::run('woocommerce_after_single_product_summary', self::REPLACED['woocommerce_after_single_product_summary']);
        $html .= self::rails($product);
        $html .= Hooks::run('woocommerce_after_single_product');
        if ($guide) {
            $html .= SizeGuides::template($guide);
        }
        if (Settings::get('sticky_bar') && $product->is_type(['simple', 'variable']) && $product->is_purchasable() && $product->is_in_stock()) {
            $html .= self::sticky_bar($product);
        }
        $html .= '</div>' . ($canvas ? '</main>' : '');
        return $html;
    }

    /** Brand, name, rating, price and summary. */
    private static function heading(\WC_Product $product): string
    {
        $name  = wp_strip_all_tags($product->get_name());
        $brand = self::brand_link($product);

        $html = '' !== $brand ? '<p class="hive-product__brand">' . $brand . '</p>' : '';
        $html .= '<h1 class="hive-product__title">' . esc_html($name) . '</h1>';

        $count = $product->get_review_count();
        if ($count > 0 && function_exists('wc_review_ratings_enabled') && wc_review_ratings_enabled()) {
            $average = (float) $product->get_average_rating();
            $html   .= sprintf(
                '<a class="hive-product__rating" href="#hive-reviews" data-hive-open-details="hive-reviews"><span class="hive-product__stars" style="--hive-rating:%s" aria-hidden="true"></span><span>%s</span></a>',
                esc_attr((string) round($average / 5 * 100, 1) . '%'),
                esc_html(sprintf('%s su 5 · %d %s', number_format_i18n($average, 1), $count, 1 === $count ? 'recensione' : 'recensioni'))
            );
        }

        $price = $product->get_price_html();
        if ('' !== $price) {
            $html .= '<div class="hive-product__price" data-hive-price>' . wp_kses_post($price) . '</div>';
        }

        $excerpt = (string) apply_filters('woocommerce_short_description', get_post_field('post_excerpt', $product->get_id()));
        if ('' !== trim($excerpt)) {
            $html .= '<div class="hive-product__excerpt">' . $excerpt . '</div>';
        }
        return $html;
    }

    /** The brand, linked to its page when it has one. */
    private static function brand_link(\WC_Product $product): string
    {
        foreach (Store::brand_taxonomies() as $taxonomy) {
            $terms = get_the_terms($product->get_id(), $taxonomy);
            if (!is_array($terms) || !$terms) {
                continue;
            }
            $term = $terms[0];
            $name = esc_html(html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'));
            $link = is_taxonomy_viewable($taxonomy) ? get_term_link($term) : '';
            return is_string($link) && '' !== $link ? sprintf('<a href="%s">%s</a>', esc_url($link), $name) : $name;
        }
        return '';
    }

    /**
     * WooCommerce's tabs as an accordion of <details>: description, details
     * (Hive's own list in place of WooCommerce's table), reviews, and the
     * tabs plugins add. The first one starts open.
     */
    private static function details(\WC_Product $product): string
    {
        $tabs = Hooks::around('woocommerce_product_tabs', [], static fn() => apply_filters('woocommerce_product_tabs', []));
        $tabs = is_array($tabs) ? $tabs : [];

        $rows = self::detail_rows($product);
        if (isset($tabs['additional_information'])) {
            $tabs['additional_information']['title'] = 'Dettagli';
            $tabs['additional_information']['callback'] = static fn() => self::print_details($rows);
        } elseif ($rows) {
            $tabs['additional_information'] = ['title' => 'Dettagli', 'priority' => 20, 'callback' => static fn() => self::print_details($rows)];
            uasort($tabs, static fn(array $a, array $b): int => ($a['priority'] ?? 50) <=> ($b['priority'] ?? 50));
        }
        if (!$tabs) {
            return '';
        }

        // WooCommerce's description and details templates print their own heading; the accordion has it.
        add_filter('woocommerce_product_description_heading', '__return_empty_string', 99);
        add_filter('woocommerce_product_additional_information_heading', '__return_empty_string', 99);

        $html  = '<div class="hive-product__details">';
        $first = true;
        foreach ($tabs as $key => $tab) {
            if (!is_array($tab) || !isset($tab['callback']) || !is_callable($tab['callback'])) {
                continue;
            }
            ob_start();
            call_user_func($tab['callback'], $key, $tab);
            $body = (string) ob_get_clean();
            if ('' === trim($body)) {
                continue;
            }
            $id    = 'reviews' === $key ? 'hive-reviews' : 'hive-details-' . sanitize_html_class((string) $key);
            $title = wp_strip_all_tags((string) apply_filters('woocommerce_product_' . $key . '_tab_title', $tab['title'] ?? '', $key));
            $html .= sprintf(
                '<details class="hive-product__detail" id="%s"%s><summary class="hive-product__detail-title">%s</summary><div class="hive-product__detail-body">%s</div></details>',
                esc_attr($id),
                $first ? ' open' : '',
                esc_html($title),
                $body
            );
            $first = false;
        }

        remove_filter('woocommerce_product_description_heading', '__return_empty_string', 99);
        remove_filter('woocommerce_product_additional_information_heading', '__return_empty_string', 99);
        return $first ? '' : $html . '</div>';
    }

    /**
     * Brand, categories, product code and the attributes shown on the
     * product page (not the ones that vary: the buttons show those).
     *
     * @return list<array{0: string, 1: string}> [label, value HTML]
     */
    private static function detail_rows(\WC_Product $product): array
    {
        $rows  = [];
        $brand = self::brand_link($product);
        if ('' !== $brand) {
            $rows[] = ['Marca', $brand];
        }
        $cats = get_the_term_list($product->get_id(), 'product_cat', '', ', ');
        if (is_string($cats) && '' !== $cats) {
            $rows[] = ['Categoria', $cats];
        }
        $brand_taxonomies = Store::brand_taxonomies();
        foreach ($product->get_attributes() as $attribute) {
            if (!$attribute instanceof \WC_Product_Attribute || !$attribute->get_visible() || $attribute->get_variation() || in_array($attribute->get_name(), $brand_taxonomies, true)) {
                continue;
            }
            $values = $attribute->is_taxonomy()
                ? wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names'])
                : $attribute->get_options();
            $values = array_filter(array_map('strval', $values));
            if ($values) {
                $rows[] = [wc_attribute_label($attribute->get_name(), $product), esc_html(implode(', ', $values))];
            }
        }
        if (wc_product_sku_enabled() && '' !== (string) $product->get_sku()) {
            $rows[] = ['Codice', esc_html((string) $product->get_sku())];
        }
        return $rows;
    }

    /** @param list<array{0: string, 1: string}> $rows */
    private static function print_details(array $rows): void
    {
        echo '<dl class="hive-product__facts">';
        foreach ($rows as [$label, $value]) {
            printf('<div><dt>%s</dt><dd>%s</dd></div>', esc_html($label), wp_kses_post($value));
        }
        echo '</dl>';
        // Plugins that add to WooCommerce's product meta (codes, labels) keep their place.
        echo Hooks::run('woocommerce_product_meta_start') . Hooks::run('woocommerce_product_meta_end'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- what the plugins print, as on WooCommerce's own page.
    }

    /** Upsells, then related products, as Hive rails. */
    private static function rails(\WC_Product $product): string
    {
        $html    = '';
        $upsells = array_slice(array_map('intval', $product->get_upsell_ids()), 0, self::RAIL_LIMIT);
        if ($upsells) {
            $html .= Rail::render(['title' => 'Ti consigliamo', 'ids' => implode(',', $upsells), 'limit' => self::RAIL_LIMIT, 'show_all' => false]);
        }
        $related = array_diff(array_map('intval', wc_get_related_products($product->get_id(), self::RAIL_LIMIT, $upsells)), $upsells);
        if ($related) {
            $html .= Rail::render(['title' => 'Potrebbe piacerti anche', 'ids' => implode(',', $related), 'limit' => self::RAIL_LIMIT, 'show_all' => false]);
        }
        return '' !== $html ? '<div class="hive-product__rails">' . $html . '</div>' : '';
    }

    /**
     * The bar that takes over when the add-to-cart button scrolls away:
     * photo, name, price (the picked size's), and a button that adds, or
     * brings back the sizes when none is picked. Hidden until @hive/product
     * shows it.
     */
    private static function sticky_bar(\WC_Product $product): string
    {
        $image = (int) $product->get_image_id();
        $thumb = $image ? wp_get_attachment_image($image, 'woocommerce_gallery_thumbnail', false, ['class' => 'hive-sticky__image', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async']) : '';
        $label = $product->single_add_to_cart_text();
        if ($product instanceof \WC_Product_Variable) {
            $label = array_keys($product->get_variation_attributes()) === [Store::size_attribute()] ? 'Scegli la taglia' : 'Scegli le opzioni';
        }
        return sprintf(
            '<div class="hive-sticky" data-hive-sticky hidden><div class="hive-sticky__inner">%s<div class="hive-sticky__text"><p class="hive-sticky__title">%s</p><p class="hive-sticky__price" data-hive-sticky-price>%s</p></div><button type="button" class="hive-button hive-sticky__add" data-hive-sticky-add>%s</button></div></div>',
            $thumb,
            esc_html(wp_strip_all_tags($product->get_name())),
            wp_kses_post($product->get_price_html()),
            esc_html($label)
        );
    }
}
