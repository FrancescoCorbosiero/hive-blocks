<?php
/**
 * The modules, and which of them are on: the settings (Hive → Moduli) or,
 * over them, the hive_module_enabled filter:
 *
 *   add_filter('hive_module_enabled', fn($on, $id) => 'gallery' === $id ? false : $on, 10, 2);
 *
 * @package Hive
 */

namespace Hive;

defined('ABSPATH') || exit;

final class Modules
{
    /** @var list<class-string<Module>> Built-in modules, in the order the admin lists them. */
    private const BUILT_IN = [
        Modules\Cards::class,
        Modules\QuickView::class,
        Modules\QuickAdd::class,
        Modules\Catalog::class,
        Modules\ProductPage::class,
        Modules\Drops::class,
        Modules\Alerts::class,
    ];

    /** @return array<string, class-string<Module>> id → class */
    public static function all(): array
    {
        $all = [];
        foreach ((array) apply_filters('hive_modules', self::BUILT_IN) as $class) {
            if (is_string($class) && is_subclass_of($class, Module::class)) {
                $all[$class::id()] = $class;
            }
        }
        return $all;
    }

    public static function enabled(string $id): bool
    {
        $class = self::all()[$id] ?? null;
        if (null === $class) {
            return false;
        }
        $flags = (array) Settings::get('modules');
        $on    = array_key_exists($id, $flags) ? (bool) $flags[$id] : $class::enabled_by_default();
        return (bool) apply_filters('hive_module_enabled', $on, $id);
    }

    /** Boots the modules that are on. */
    public static function register(): void
    {
        foreach (self::all() as $id => $class) {
            if (self::enabled($id)) {
                $class::boot();
            }
        }
    }
}
