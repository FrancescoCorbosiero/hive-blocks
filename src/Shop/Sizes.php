<?php
/**
 * A product's sizes, and the shop's size filter. The rules (one variation
 * per physical size, size order, matching) are Core\Sizes; this file reads
 * them out of WooCommerce.
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Core\Sizes as Rules;

defined('ABSPATH') || exit;

final class Sizes
{
    /** Most sizes read from one filter URL. */
    private const MAX_FILTERED = 20;

    /**
     * One row per physical size of a variable product, in size order:
     *
     *   variation_id  the variation that stands for the size
     *   label         the size as people read it ("36 2/3")
     *   value         the attribute value WooCommerce stores ("36-2-3")
     *   price         float, for picking the variation that stands for a size
     *   in_stock      in stock and purchasable
     *   price_html    WooCommerce's price markup (sale-aware)
     *   price_text    the same as plain text, "180,00 €"
     *   max_qty       most that can be bought at once, -1 for no limit
     *   image         the variation's thumbnail URL ('' for the product's)
     *
     * @return list<array>
     */
    public static function rows(\WC_Product $product): array
    {
        static $memo = [];
        if (!$product->is_type('variable')) {
            return [];
        }
        $id = $product->get_id();
        if (isset($memo[$id])) {
            return $memo[$id];
        }

        $attribute = Store::size_attribute();
        $children  = $product->get_children();
        if ($children) {
            // Every variation's post and meta in two queries, not two per variation.
            _prime_post_caches($children, false, true);
        }

        $entries = [];
        foreach ($children as $child_id) {
            $variation = wc_get_product($child_id);
            if (!$variation instanceof \WC_Product_Variation || 'publish' !== $variation->get_status()) {
                continue;
            }
            $label = $variation->get_attribute($attribute);
            if ('' === $label) {
                continue;
            }
            $attributes = $variation->get_variation_attributes();
            $priced     = '' !== $variation->get_price();
            $max        = (int) $variation->get_max_purchase_quantity();
            $entries[]  = [
                'variation_id' => (int) $child_id,
                'label'        => $label,
                'value'        => (string) ($attributes['attribute_' . $attribute] ?? ''),
                'price'        => (float) $variation->get_price(),
                'in_stock'     => $variation->is_in_stock() && $variation->is_purchasable(),
                'price_html'   => $priced ? $variation->get_price_html() : '',
                'price_text'   => $priced ? self::plain_price($variation) : '',
                'max_qty'      => $max > 0 ? $max : -1,
                'image'        => self::own_image($variation, $product),
            ];
        }

        return $memo[$id] = Rules::sort(Rules::dedupe($entries));
    }

    /** "180,00 €": the price as plain text, as the shop displays it (taxes included or not). */
    public static function plain_price(\WC_Product $product): string
    {
        return html_entity_decode(wp_strip_all_tags(wc_price(wc_get_price_to_display($product))), ENT_QUOTES, 'UTF-8');
    }

    /** The variation's own thumbnail, when it has one different from the product's. */
    private static function own_image(\WC_Product_Variation $variation, \WC_Product $parent): string
    {
        $image = (int) $variation->get_image_id('edit');
        if (!$image || $image === (int) $parent->get_image_id()) {
            return '';
        }
        return (string) wp_get_attachment_image_url($image, 'woocommerce_thumbnail');
    }

    /**
     * Keys of the sizes the shop's size filter asks for (?filter_taglia=42,43).
     * A slug is matched through its term's name, so "36-2-3" reaches the
     * size it names whatever its spelling.
     *
     * @param string|null $raw The filter value; null reads it from this request's URL.
     * @return list<string>
     */
    public static function filter_keys(?string $raw = null): array
    {
        static $memo = [];
        if (null === $raw) {
            $param = Store::size_param();
            // A read-only display filter from the URL, like WooCommerce's own: no nonce.
            $raw = isset($_GET[$param]) && is_string($_GET[$param]) ? sanitize_text_field(wp_unslash($_GET[$param])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        if ('' === $raw) {
            return [];
        }
        if (isset($memo[$raw])) {
            return $memo[$raw];
        }

        $attribute = Store::size_attribute();
        $slugs     = array_slice(array_unique(array_filter(array_map('sanitize_title', explode(',', $raw)))), 0, self::MAX_FILTERED);
        $keys      = [];
        foreach ($slugs as $slug) {
            $term   = get_term_by('slug', $slug, $attribute);
            $keys[] = Rules::key($term instanceof \WP_Term ? $term->name : $slug);
        }
        return $memo[$raw] = array_values(array_unique(array_filter($keys)));
    }

    /**
     * The filtered sizes this product has in stock (rows, see rows()). Empty
     * without a size filter.
     *
     * @return list<array>
     */
    public static function matching(\WC_Product $product, ?string $raw = null): array
    {
        $keys = self::filter_keys($raw);
        return $keys ? Rules::pick(self::rows($product), $keys) : [];
    }
}
