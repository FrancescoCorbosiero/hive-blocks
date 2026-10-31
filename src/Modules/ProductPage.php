<?php
/**
 * Module "Pagina prodotto": the product page is drawn by Hive
 * (Hive\Shop\ProductPage), whichever theme is active, with what the store
 * used CommerceKit for: the gallery, the size buttons with prices, the
 * sticky add-to-cart bar, the stock meter and the size guides. The theme
 * keeps its header and footer:
 *
 *   classic themes   templates/product.php: get_header('shop'), the page,
 *                    get_footer('shop')
 *   block themes     the theme's header and footer parts around the
 *                    hive/product block, through WordPress's own template
 *                    canvas
 *
 * WooCommerce's gallery scripts (zoom, slider, PhotoSwipe) don't load on
 * Hive's page: it has its own, lighter gallery.
 *
 * Switched off, WooCommerce and the theme draw the product page again.
 *
 * @package Hive
 */

namespace Hive\Modules;

use Hive\Module;
use Hive\Plugin;
use Hive\Shop\BuyForm;
use Hive\Shop\ProductExtras;

defined('ABSPATH') || exit;

final class ProductPage implements Module
{
    public static function id(): string
    {
        return 'product-page';
    }

    public static function label(): string
    {
        return 'Pagina prodotto';
    }

    public static function description(): string
    {
        return 'La pagina del prodotto di Hive: galleria, taglie con il prezzo, scorte, guida alle taglie e barra di acquisto fissa, al posto del modello del tema e di CommerceKit.';
    }

    public static function enabled_by_default(): bool
    {
        return true;
    }

    public static function boot(): void
    {
        BuyForm::register();
        ProductExtras::register();
        add_filter('template_include', [self::class, 'template'], 99);
        add_action('wp_enqueue_scripts', [self::class, 'early_assets'], 5);
        // Again right before the scripts print: some setups enqueue them later than wp_enqueue_scripts.
        add_action('wp_enqueue_scripts', [self::class, 'drop_gallery_scripts'], 99);
        add_action('wp_print_scripts', [self::class, 'drop_gallery_scripts'], 1);
        add_action('wp_print_footer_scripts', [self::class, 'drop_gallery_scripts'], 1);
        add_filter('script_module_data_@hive/product', [self::class, 'module_data']);
    }

    public static function applies(): bool
    {
        return function_exists('is_product') && is_product() && (bool) apply_filters('hive_product_template', true);
    }

    public static function template(mixed $template): mixed
    {
        if (!self::applies()) {
            return $template;
        }
        if (wp_is_block_theme()) {
            global $_wp_current_template_content, $_wp_current_template_id;
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the template canvas renders what these hold.
            $_wp_current_template_content = '<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /--><!-- wp:hive/product {"canvas":true} /--><!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->';
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            $_wp_current_template_id = get_stylesheet() . '//hive-product';
            return ABSPATH . WPINC . '/template-canvas.php';
        }
        return Plugin::path('templates/product.php');
    }

    /** Classic themes print <head> first: the page's CSS goes there. */
    public static function early_assets(): void
    {
        if (self::applies()) {
            wp_enqueue_style('hive-product');
            wp_enqueue_style('hive-shop');
        }
    }

    /** WooCommerce's gallery scripts and PhotoSwipe's markup: Hive's page has its own gallery. */
    public static function drop_gallery_scripts(): void
    {
        if (!self::applies()) {
            return;
        }
        // WooCommerce 10 renamed them (wc-zoom…); the old names stay for older versions.
        foreach (['zoom', 'flexslider', 'photoswipe-ui-default', 'photoswipe', 'wc-zoom', 'wc-flexslider', 'wc-photoswipe-ui-default', 'wc-photoswipe'] as $handle) {
            wp_dequeue_script($handle);
        }
        foreach (['photoswipe-default-skin', 'photoswipe'] as $handle) {
            wp_dequeue_style($handle);
        }
        remove_action('wp_footer', 'woocommerce_photoswipe');
    }

    /** What @hive/product needs: where to post the form, whether WooCommerce sends shoppers to the cart. */
    public static function module_data(array $data): array
    {
        return [
            'ajax'     => 'yes' === get_option('woocommerce_cart_redirect_after_add') ? '' : \WC_AJAX::get_endpoint(BuyForm::AJAX),
            'cartUrl'  => wc_get_cart_url(),
        ] + $data;
    }
}
