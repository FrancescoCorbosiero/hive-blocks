<?php
/**
 * WooCommerce's breadcrumb (its trail, so SEO plugins that filter it still
 * apply) in Hive's markup: an ordered list in a labelled <nav>.
 *
 * @package Hive
 */

namespace Hive\Shop;

defined('ABSPATH') || exit;

final class Breadcrumb
{
    public static function render(string $class): string
    {
        if (!function_exists('woocommerce_breadcrumb')) {
            return '';
        }
        ob_start();
        woocommerce_breadcrumb(
            [
                'wrap_before' => '<nav class="' . esc_attr($class) . '" aria-label="Percorso"><ol>',
                'wrap_after'  => '</ol></nav>',
                'before'      => '<li>',
                'after'       => '</li>',
                'delimiter'   => '',
            ]
        );
        return (string) ob_get_clean();
    }
}
