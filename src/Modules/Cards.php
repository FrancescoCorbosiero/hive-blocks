<?php
/**
 * Module "Schede prodotto": WooCommerce's product lists (shop, categories,
 * search, related products, cross-sells, the [products] shortcode) show
 * Hive's cards in Hive's grid instead of the theme's.
 *
 * It swaps three WooCommerce templates, whichever theme is active:
 * content-product.php (the card) and loop/loop-start.php, loop-end.php (the
 * ul.products around them). The cards fire none of WooCommerce's loop
 * hooks, so what a theme or plugin adds there doesn't reach them.
 *
 * @package Hive
 */

namespace Hive\Modules;

use Hive\Module;
use Hive\Plugin;

defined('ABSPATH') || exit;

final class Cards implements Module
{
    private const TEMPLATES = [
        'loop/loop-start.php' => 'templates/woocommerce/loop-start.php',
        'loop/loop-end.php'   => 'templates/woocommerce/loop-end.php',
    ];

    public static function id(): string
    {
        return 'cards';
    }

    public static function label(): string
    {
        return 'Schede prodotto';
    }

    public static function description(): string
    {
        return 'Le liste di prodotti di WooCommerce (negozio, categorie, ricerca, prodotti correlati) usano le schede di Hive al posto di quelle del tema.';
    }

    public static function enabled_by_default(): bool
    {
        return true;
    }

    public static function boot(): void
    {
        add_filter('wc_get_template_part', [self::class, 'card_template'], 20, 3);
        add_filter('wc_get_template', [self::class, 'loop_template'], 20, 2);
        add_action('wp_enqueue_scripts', [self::class, 'early_assets'], 5);
    }

    public static function card_template(mixed $template, string $slug, string $name): mixed
    {
        return 'content' === $slug && 'product' === $name ? Plugin::path('templates/woocommerce/content-product.php') : $template;
    }

    public static function loop_template(mixed $template, string $template_name): mixed
    {
        return isset(self::TEMPLATES[$template_name]) ? Plugin::path(self::TEMPLATES[$template_name]) : $template;
    }

    /** Where WooCommerce lists products, the cards' CSS goes in <head> (classic themes print it before the lists). */
    public static function early_assets(): void
    {
        if (is_shop() || is_product_taxonomy() || is_product() || is_cart() || is_search()) {
            wp_enqueue_style('hive-shop');
        }
    }
}
