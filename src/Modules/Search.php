<?php
/**
 * Module "Ricerca istantanea": results as you type, in a dialog, from
 * Hive's own cacheable read (Hive\Shop\Search). The "Cerca" block
 * (hive/search) opens it; the hive_search_trigger_selector filter can make
 * a theme's own search form open it too.
 *
 * @package Hive
 */

namespace Hive\Modules;

use Hive\Module;
use Hive\Shop\ProductData;
use Hive\Shop\Search as Feature;
use Hive\Shop\Store;

defined('ABSPATH') || exit;

final class Search implements Module
{
    public static function id(): string
    {
        return 'search';
    }

    public static function label(): string
    {
        return 'Ricerca istantanea';
    }

    public static function description(): string
    {
        return 'I prodotti mentre scrivi, in una finestra aperta dal blocco «Cerca» (con Relevanssi se c’è): risposte che la CDN può tenere in cache.';
    }

    public static function enabled_by_default(): bool
    {
        return true;
    }

    public static function boot(): void
    {
        Feature::register();
        add_filter('script_module_data_@hive/search', [self::class, 'module_data']);
        add_action('wp_enqueue_scripts', [self::class, 'theme_trigger']);
    }

    /** @param array<string, mixed> $data */
    public static function module_data(array $data): array
    {
        return [
            'api'      => rest_url(ProductData::NAMESPACE . '/'),
            'v'        => Store::generation(),
            'home'     => home_url('/'),
            'selector' => (string) apply_filters('hive_search_trigger_selector', ''),
            'popular'  => array_values(array_filter(array_map('strval', (array) apply_filters('hive_search_popular', [])))),
        ] + $data;
    }

    /** A theme's search form chosen to open the dialog: the script on every page. */
    public static function theme_trigger(): void
    {
        if ('' !== (string) apply_filters('hive_search_trigger_selector', '')) {
            wp_enqueue_script_module('@hive/search');
        }
    }
}
