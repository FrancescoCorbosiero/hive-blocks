<?php
/**
 * Plugin Name:       Hive Blocks
 * Plugin URI:        https://github.com/FrancescoCorbosiero/hive-blocks
 * Description:       Blocchi e interfaccia WooCommerce di ResellPiacenza: veloci, pensati per pagine in cache e CDN, indipendenti dal tema.
 * Version:           0.5.0
 * Requires at least: 6.8
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Golden Hive
 * Author URI:        https://goldenhive.it
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hive-blocks
 * WC requires at least: 9.9
 *
 * @package Hive
 */

namespace Hive;

defined('ABSPATH') || exit;

/** Plugin version. Bump it together with the header above on every release. */
const VERSION = '0.5.0';

/** This file: the root every path and URL is resolved from. */
const FILE = __FILE__;

spl_autoload_register(
    static function (string $class): void {
        $prefix = __NAMESPACE__ . '\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
);

// Orders: the plugin reads them only through WooCommerce's API, so it works
// with order storage in WooCommerce's own tables (HPOS).
add_action(
    'before_woocommerce_init',
    static function (): void {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', FILE, true);
        }
    }
);

add_action('plugins_loaded', [Plugin::class, 'boot']);
