<?php
/**
 * The product card: one renderer for every list of products (rails, the
 * shop's lists, search, colorways, recently viewed). It's Hive's own markup,
 * styled by assets/shop.css, so it looks the same on any theme, and it fires
 * none of WooCommerce's loop hooks: nothing another plugin or the theme adds
 * there can reshape it. Its own hooks: hive_card_state, hive_card_badges.
 *
 *   <li class="hive-card">
 *     media: image, badges ("-20%", "Esaurito"), quick view button
 *     body: brand, title (its link covers the card), price, the filtered
 *           size's line ("Taglia 42 · 180 €")
 *     actions: add to cart (simple), choose a size (variable: Quick Add),
 *              a link (other types), or a disabled label (sold out)
 *   </li>
 *
 * The buttons work through the @hive/cards script module, which loads Quick
 * View and Quick Add only when someone reaches for them.
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Assets;
use Hive\Core\Price;
use Hive\Core\Sizes as SizeRules;
use Hive\Modules;

defined('ABSPATH') || exit;

final class Card
{
    /** Cards rendered in this request: the first ones of a list load their image eagerly. */
    private static int $count = 0;

    /**
     * @param array{heading?: string, eager?: bool, size_filter?: string|null} $options
     *   heading      h2 on archive pages, h3 under a section title (default)
     *   eager        load the image right away (the first cards of a page)
     *   size_filter  the size filter's value, when not this request's URL
     */
    public static function render(\WC_Product $product, array $options = []): string
    {
        $prelude = Assets::use_style('hive-shop');
        Assets::use_module('@hive/cards');

        $id      = $product->get_id();
        $url     = $product->get_permalink();
        $name    = wp_strip_all_tags($product->get_name());
        $state   = self::state($product);
        $heading = $options['heading'] ?? 'h3';
        $heading = in_array($heading, ['h2', 'h3', 'h4'], true) ? $heading : 'h3';
        $eager   = $options['eager'] ?? false;
        $matches = $product->is_type('variable') ? Sizes::matching($product, $options['size_filter'] ?? null) : [];
        // Found through one size: its page opens with that size picked (WooCommerce's own ?attribute_pa_taglia=42).
        if (1 === count($matches) && '' !== $matches[0]['value']) {
            $url = add_query_arg('attribute_' . sanitize_title(Store::size_attribute()), rawurlencode($matches[0]['value']), $url);
        }
        $brand   = Store::brand($product);
        // The brand line only when the name doesn't already start with it ("Nike" over "Nike Dunk Low").
        if ('' !== $brand && 0 === stripos($name, $brand)) {
            $brand = '';
        }

        $classes = ['hive-card', 'is-' . $state['kind']];
        if ($product->is_on_sale()) {
            $classes[] = 'is-on-sale';
        }
        self::$count++;

        $html  = $prelude;
        $html .= sprintf('<li class="%s" data-product-id="%d">', esc_attr(implode(' ', $classes)), $id);
        $html .= '<div class="hive-card__media">' . self::image($product, $eager) . self::badges($product, $state);
        if (Modules::enabled('quick-view')) {
            $html .= sprintf(
                '<button type="button" class="hive-card__peek" data-hive-quick-view="%d"%s aria-label="%s">%s</button>',
                $id,
                self::size_data($matches),
                esc_attr('Anteprima rapida: ' . $name),
                '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5C6.5 5 2.7 9.4 1.5 12c1.2 2.6 5 7 10.5 7s9.3-4.4 10.5-7C21.3 9.4 17.5 5 12 5Zm0 11.5a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9Zm0-2.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>'
            );
        }
        $html .= '</div><div class="hive-card__body">';
        if ('' !== $brand) {
            $html .= '<p class="hive-card__brand">' . esc_html($brand) . '</p>';
        }
        $html .= sprintf('<%1$s class="hive-card__title"><a class="hive-card__link" href="%2$s">%3$s</a></%1$s>', $heading, esc_url($url), esc_html($name));
        $price = $product->get_price_html();
        if ('' !== $price) {
            $html .= '<div class="hive-card__price">' . wp_kses_post($price) . '</div>';
        }
        $line = SizeRules::line($matches);
        if ('' !== $line) {
            $html .= '<p class="hive-card__match">' . esc_html($line) . '</p>';
        }
        $html .= '</div><div class="hive-card__actions">' . self::action($product, $state, $matches) . '</div></li>';

        return $html;
    }

    /** Cards rendered so far in this request. */
    public static function count(): int
    {
        return self::$count;
    }

    /**
     * What the card's button does, as kind + label:
     *
     *   simple    adds to the cart
     *   variable  opens Quick Add to choose a size
     *   link      goes to the product page (other types, or Quick Add off)
     *   sold-out  nothing, "Esaurito"
     *
     * Filtered by hive_card_state (a drop not out yet becomes "soon").
     *
     * @return array{kind: string, label: string}
     */
    public static function state(\WC_Product $product): array
    {
        if ($product->is_type(['simple', 'variable']) && (!$product->is_purchasable() || !$product->is_in_stock())) {
            $state = ['kind' => 'sold-out', 'label' => 'Esaurito'];
        } elseif ($product->is_type('simple')) {
            $state = ['kind' => 'simple', 'label' => 'Aggiungi al carrello'];
        } elseif ($product->is_type('variable')) {
            $state = Modules::enabled('quick-add')
                ? ['kind' => 'variable', 'label' => 'Aggiungi al carrello']
                : ['kind' => 'link', 'label' => 'Scegli la taglia'];
        } else {
            $state = ['kind' => 'link', 'label' => 'Vedi prodotto'];
        }
        $state = apply_filters('hive_card_state', $state, $product);
        return [
            'kind'  => (string) ($state['kind'] ?? 'link'),
            'label' => (string) ($state['label'] ?? 'Vedi prodotto'),
        ];
    }

    private static function image(\WC_Product $product, bool $eager): string
    {
        $attributes = [
            'class'    => 'hive-card__image',
            'loading'  => $eager ? 'eager' : 'lazy',
            'decoding' => 'async',
            'sizes'    => '(max-width: 767px) 50vw, (max-width: 1024px) 33vw, 300px',
        ];
        if ($eager && 1 === self::$count) {
            $attributes['fetchpriority'] = 'high';
        }
        $image_id = (int) $product->get_image_id();
        $image    = $image_id ? wp_get_attachment_image($image_id, 'woocommerce_thumbnail', false, $attributes) : '';
        return '' !== $image ? $image : wc_placeholder_img('woocommerce_thumbnail', $attributes);
    }

    /** The card's badges, drawn. */
    private static function badges(\WC_Product $product, array $state): string
    {
        $badges = self::badge_list($product, $state);
        if (!$badges) {
            return '';
        }
        $html = '<p class="hive-card__badges">';
        foreach ($badges as $class => $text) {
            $html .= sprintf('<span class="hive-card__badge %s">%s</span>', esc_attr((string) $class), esc_html((string) $text));
        }
        return $html . '</p>';
    }

    /**
     * The sale badge ("-20%", "Fino a -30%"), "Esaurito", and whatever the
     * hive_card_badges filter adds: [class => text]. The product page shows
     * the same ones on its gallery.
     *
     * @param array{kind: string, label: string} $state self::state()
     * @return array<string, string>
     */
    public static function badge_list(\WC_Product $product, array $state): array
    {
        $badges = [];
        if ('sold-out' === $state['kind']) {
            $badges['is-sold-out'] = 'Esaurito';
        } elseif ($product->is_on_sale()) {
            $sale = Price::badge(self::price_pairs($product));
            if ('' !== $sale) {
                $badges['is-sale'] = $sale;
            }
        }
        return array_map('strval', (array) apply_filters('hive_card_badges', $badges, $product, $state));
    }

    /**
     * [regular, current] price per variation (or the product's own), from
     * WooCommerce's cached variation prices.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function price_pairs(\WC_Product $product): array
    {
        if ($product instanceof \WC_Product_Variable) {
            $prices = $product->get_variation_prices(true);
            $pairs  = [];
            foreach ($prices['regular_price'] ?? [] as $variation_id => $regular) {
                $pairs[] = [$regular, $prices['price'][$variation_id] ?? $regular];
            }
            return $pairs;
        }
        return [[$product->get_regular_price(), $product->get_price()]];
    }

    private static function action(\WC_Product $product, array $state, array $matches): string
    {
        $label = esc_html($state['label']);
        switch ($state['kind']) {
            case 'simple':
                return sprintf('<button type="button" class="hive-button hive-card__add" data-hive-add="%d">%s</button>', $product->get_id(), $label);
            case 'variable':
                return sprintf('<button type="button" class="hive-button hive-card__add" data-hive-add="%d" data-hive-pick%s>%s</button>', $product->get_id(), self::size_data($matches), $label);
            case 'link':
                return sprintf('<a class="hive-button hive-card__add" href="%s">%s</a>', esc_url($product->get_permalink()), $label);
        }
        return sprintf('<span class="hive-button hive-card__add is-disabled" aria-disabled="true">%s</span>', $label);
    }

    /** data-hive-size: the variation of the one filtered size the product has, so Quick View and Quick Add open with it picked. */
    private static function size_data(array $matches): string
    {
        return 1 === count($matches) ? sprintf(' data-hive-size="%d"', (int) $matches[0]['variation_id']) : '';
    }
}
