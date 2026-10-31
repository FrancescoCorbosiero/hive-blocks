<?php
/**
 * Store data the shop features share: the size attribute, the brand
 * taxonomies, and the catalog generation, a number that changes whenever a
 * product, its stock or its terms change. The generation is part of every
 * product cache key and of the product data URLs (?v=), so caches and the
 * CDN never serve a stale product for long, and nothing has to be purged by
 * hand.
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Settings;

defined('ABSPATH') || exit;

final class Store
{
    public const GENERATION = 'hive_catalog_gen';

    public static function register(): void
    {
        $hooks = [
            'woocommerce_new_product',
            'woocommerce_update_product',
            'woocommerce_new_product_variation',
            'woocommerce_update_product_variation',
            'woocommerce_product_set_stock_status',
            'woocommerce_variation_set_stock_status',
            'woocommerce_delete_product',
            'woocommerce_trash_product',
        ];
        foreach ($hooks as $hook) {
            add_action($hook, [self::class, 'bump']);
        }
        add_action('set_object_terms', [self::class, 'terms_changed'], 10, 4);
    }

    /** The current catalog generation. */
    public static function generation(): int
    {
        return max(1, (int) get_option(self::GENERATION, 1));
    }

    /** Products changed: every product cache key and data URL moves on. Once per request is enough. */
    public static function bump(): void
    {
        static $done = false;
        if (!$done) {
            $done = true;
            update_option(self::GENERATION, self::generation() + 1, true);
        }
    }

    /** A product's categories, brands, tags or visibility changed. */
    public static function terms_changed(int $object_id, mixed $terms, mixed $tt_ids, string $taxonomy): void
    {
        if (in_array($taxonomy, array_merge(['product_cat', 'product_tag', 'product_visibility'], self::brand_taxonomies()), true)) {
            self::bump();
        }
    }

    /** The attribute that holds the sizes (Hive → Negozio), e.g. pa_taglia. */
    public static function size_attribute(): string
    {
        return (string) apply_filters('hive_size_attribute', (string) Settings::get('size_attribute'));
    }

    /** The URL parameter of the shop's size filter: WooCommerce's own, filter_taglia for pa_taglia. */
    public static function size_param(): string
    {
        $attribute = self::size_attribute();
        return 'filter_' . (str_starts_with($attribute, 'pa_') ? substr($attribute, 3) : $attribute);
    }

    /**
     * The taxonomies a brand can live in, those that exist on this site:
     * WooCommerce Brands, Perfect Brands, or a brand attribute.
     *
     * @return list<string>
     */
    public static function brand_taxonomies(): array
    {
        $candidates = (array) apply_filters('hive_brand_taxonomies', ['product_brand', 'pwb-brand', 'pa_brand', 'pa_marca']);
        return array_values(array_filter($candidates, static fn($taxonomy) => is_string($taxonomy) && taxonomy_exists($taxonomy)));
    }

    /** A product's brand name, from the first brand taxonomy that has one; '' without. */
    public static function brand(\WC_Product $product): string
    {
        foreach (self::brand_taxonomies() as $taxonomy) {
            $terms = get_the_terms($product->get_id(), $taxonomy);
            if (is_array($terms) && $terms) {
                return html_entity_decode($terms[0]->name, ENT_QUOTES, 'UTF-8');
            }
        }
        return '';
    }
}
