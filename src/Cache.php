<?php
/**
 * Full-page cache purge, for the moments a cached page is wrong for every
 * visitor at once, e.g. a design setting changed. Empties whichever page
 * cache the site runs; a CDN can follow on hive_pages_purged.
 *
 * @package Hive
 */

namespace Hive;

defined('ABSPATH') || exit;

final class Cache
{
    public static function register(): void
    {
        // The design tokens are printed into every page with a Hive block.
        add_action('update_option_' . Settings::OPTION, [self::class, 'purge_pages']);
    }

    public static function purge_pages(): void
    {
        do_action('litespeed_purge_all');
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        if (function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache();
        }
        do_action('hive_pages_purged');
    }
}
