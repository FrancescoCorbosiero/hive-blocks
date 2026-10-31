<?php
/**
 * The product in the "Prodotto in evidenza" block (blocks/product-spotlight):
 * the one chosen in the block, or else the shop's newest featured product
 * that can be bought, or else its newest product that can be bought. A
 * chosen product shows whatever its state (sold out, a drop not out yet);
 * one picked automatically is always on sale.
 *
 * Its button is the product card's (Card::state()), with the card's data
 * attributes, so @hive/cards adds a simple product to the cart and opens
 * Quick Add for one with sizes.
 *
 * @package Hive
 */

namespace Hive\Shop;

defined('ABSPATH') || exit;

final class Spotlight
{
    /** Candidates looked at, per source, for one that can be bought. */
    private const CANDIDATES = 12;

    public static function product(int $id): ?\WC_Product
    {
        if ($id > 0) {
            $product = wc_get_product($id);
            return ProductData::is_public($product) ? $product : null;
        }

        foreach (['featured', 'recent'] as $type) {
            $args = RailQuery::query_args(RailQuery::normalize(['type' => $type, 'limit' => self::CANDIDATES]));
            if ('recent' === $type) {
                // Products saved in the same second still come in a fixed order.
                $args['orderby'] = ['date' => 'DESC', 'ID' => 'DESC'];
                unset($args['order']);
            }
            $args['fields']        = 'ids';
            $args['no_found_rows'] = true;
            foreach ((new \WP_Query($args))->posts as $candidate) {
                $product = wc_get_product((int) $candidate);
                if ($product instanceof \WC_Product && $product->is_visible() && $product->is_purchasable() && $product->is_in_stock()) {
                    return $product;
                }
            }
        }
        return null;
    }

    /**
     * The product card's button for this product, with the block's own class:
     * add to cart (simple), choose a size (variable), a link (other types), or
     * a disabled label (sold out, a drop not out yet). $label replaces the
     * text of the first three.
     */
    public static function button(\WC_Product $product, string $class, string $label = ''): string
    {
        $state = Card::state($product);
        $text  = '' !== $label && in_array($state['kind'], ['simple', 'variable', 'link'], true) ? $label : $state['label'];
        $id    = $product->get_id();
        switch ($state['kind']) {
            case 'simple':
                return sprintf('<button type="button" class="hive-button %s" data-hive-add="%d">%s</button>', esc_attr($class), $id, esc_html($text));
            case 'variable':
                return sprintf('<button type="button" class="hive-button %s" data-hive-add="%d" data-hive-pick>%s</button>', esc_attr($class), $id, esc_html($text));
            case 'link':
                return sprintf('<a class="hive-button %s" href="%s">%s</a>', esc_attr($class), esc_url($product->get_permalink()), esc_html($text));
        }
        return sprintf('<span class="hive-button %s is-disabled" aria-disabled="true">%s</span>', esc_attr($class), esc_html($text));
    }
}
