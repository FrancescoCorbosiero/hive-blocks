<?php
/**
 * Boot sequence and the plugin's paths.
 *
 * @package Hive
 */

namespace Hive;

defined('ABSPATH') || exit;

final class Plugin
{
    /** Hooks every part of the plugin into WordPress. Runs on plugins_loaded. */
    public static function boot(): void
    {
        Settings::register();
        Cache::register();
        Speed::register();
        Assets::register();
        Blocks::register();

        // The shop needs WooCommerce (required by the plugin header; this keeps a site
        // whose WooCommerce went missing from a fatal error).
        if (class_exists('WooCommerce')) {
            Shop\Store::register();
            Shop\ProductData::register();
            Shop\Rail::register();
            Shop\Ordering::register();
            Shop\SizeGuides::register();
            Shop\SocialProof::register();
            Shop\Alerts::register_list();
            Shop\Newsletter::register();
            Shop\SizeRail::register();
            Modules::register();
            // The switch-over from golden-hive-blocks: Hive → Migrazione, `wp hive migrate`.
            if (is_admin() || (defined('WP_CLI') && WP_CLI)) {
                Migration::register();
            }
        }
    }

    /** Absolute path of a file in the plugin. */
    public static function path(string $relative = ''): string
    {
        return plugin_dir_path(FILE) . ltrim($relative, '/');
    }

    /** Public URL of a file in the plugin. */
    public static function url(string $relative = ''): string
    {
        return plugins_url(ltrim($relative, '/'), FILE);
    }
}
