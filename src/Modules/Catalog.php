<?php
/**
 * Module "Pagine del negozio": the shop and its category, brand, tag and
 * attribute pages are drawn by Hive (Hive\Shop\Catalog), whichever theme is
 * active. The theme keeps its header and footer:
 *
 *   classic themes   templates/catalog.php: get_header('shop'), the
 *                    catalog, get_footer('shop')
 *   block themes     the theme's header and footer parts around the
 *                    hive/catalog block, through WordPress's own template
 *                    canvas
 *
 * Switched off, WooCommerce and the theme draw these pages again (with Hive's
 * cards while "Schede prodotto" is on).
 *
 * @package Hive
 */

namespace Hive\Modules;

use Hive\Module;
use Hive\Plugin;
use Hive\Shop\Filters;

defined('ABSPATH') || exit;

final class Catalog implements Module
{
    public static function id(): string
    {
        return 'catalog';
    }

    public static function label(): string
    {
        return 'Pagine del negozio';
    }

    public static function description(): string
    {
        return 'Il negozio e le pagine di categorie, marche e tag con intestazione, filtri, ordinamento e pagine di Hive, al posto del modello del tema.';
    }

    public static function enabled_by_default(): bool
    {
        return true;
    }

    public static function boot(): void
    {
        add_filter('template_include', [self::class, 'template'], 99);
        add_action('woocommerce_product_query', [Filters::class, 'query'], 20);
        add_action('wp_enqueue_scripts', [self::class, 'early_assets'], 5);
    }

    /** The shop's pages Hive draws: the shop and product taxonomy archives (search results stay the theme's). */
    public static function applies(): bool
    {
        return function_exists('is_shop') && (is_shop() || is_product_taxonomy()) && !is_search();
    }

    public static function template(mixed $template): mixed
    {
        if (!self::applies() || !apply_filters('hive_catalog_template', true)) {
            return $template;
        }
        if (wp_is_block_theme()) {
            global $_wp_current_template_content, $_wp_current_template_id;
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the template canvas renders what these hold.
            $_wp_current_template_content = '<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /--><!-- wp:hive/catalog {"canvas":true} /--><!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->';
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            $_wp_current_template_id = get_stylesheet() . '//hive-catalog';
            return ABSPATH . WPINC . '/template-canvas.php';
        }
        return Plugin::path('templates/catalog.php');
    }

    /** Classic themes print <head> first: the catalog's CSS goes there. */
    public static function early_assets(): void
    {
        if (self::applies()) {
            wp_enqueue_style('hive-catalog');
            wp_enqueue_style('hive-shop');
        }
    }
}
