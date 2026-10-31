<?php
/**
 * WooCommerce's product page hooks, fired on Hive's own product page so
 * other plugins keep their place (structured data, payment buttons and
 * messages, badges, reviews…), but without:
 *
 *   WooCommerce's own callbacks that Hive draws itself (title, price,
 *   gallery, add to cart, tabs, related products…), listed per hook by the
 *   caller
 *   the theme's callbacks: the page is Hive's, not the theme's (filter
 *   hive_product_theme_hooks to keep them)
 *   whatever the hive_product_hook_skip filter lists ('function' or
 *   'Class::method'), e.g. a plugin Hive replaces
 *
 * Callbacks are taken off for the run and put back right after, at their
 * priority. Which ones: Core\HookFilter.
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Core\HookFilter;

defined('ABSPATH') || exit;

final class Hooks
{
    /**
     * Fires an action and returns what it printed.
     *
     * @param list<string> $skip Callbacks to leave out, by name.
     * @param int|null     $min  Only priorities from this one.
     * @param int|null     $max  Only priorities up to this one.
     */
    public static function run(string $hook, array $skip = [], ?int $min = null, ?int $max = null, mixed ...$args): string
    {
        if (!has_action($hook)) {
            return '';
        }
        return self::around(
            $hook,
            $skip,
            static function () use ($hook, $args): string {
                ob_start();
                do_action($hook, ...$args);
                return (string) ob_get_clean();
            },
            $min,
            $max
        );
    }

    /**
     * Runs $work with some of $hook's callbacks taken off.
     *
     * @template T
     * @param list<string> $skip
     * @param callable(): T $work
     * @return T
     */
    public static function around(string $hook, array $skip, callable $work, ?int $min = null, ?int $max = null): mixed
    {
        global $wp_filter;
        $registry = $wp_filter[$hook] ?? null;
        if (!$registry instanceof \WP_Hook) {
            return $work();
        }

        $skip = array_merge($skip, array_filter((array) apply_filters('hive_product_hook_skip', [], $hook), 'is_string'));
        $dirs = apply_filters('hive_product_theme_hooks', false, $hook) ? [] : self::theme_dirs();

        $removed = [];
        foreach (HookFilter::excluded($registry->callbacks, $skip, [self::class, 'file_of'], $dirs, $min, $max) as [$priority, $id]) {
            $entry = $registry->callbacks[$priority][$id] ?? null;
            if (null === $entry) {
                continue;
            }
            $removed[] = [$priority, $entry];
            remove_filter($hook, $entry['function'], $priority);
        }
        try {
            return $work();
        } finally {
            foreach ($removed as [$priority, $entry]) {
                add_filter($hook, $entry['function'], $priority, (int) ($entry['accepted_args'] ?? 1));
            }
        }
    }

    /** The file a callback is written in, '' when it can't be told. */
    public static function file_of(mixed $function): string
    {
        try {
            if (is_string($function) && str_contains($function, '::')) {
                $function = explode('::', $function, 2);
            }
            if (is_array($function) && 2 === count($function)) {
                $reflection = new \ReflectionMethod($function[0], (string) $function[1]);
            } elseif ($function instanceof \Closure || is_string($function)) {
                $reflection = new \ReflectionFunction($function);
            } elseif (is_object($function) && method_exists($function, '__invoke')) {
                $reflection = new \ReflectionMethod($function, '__invoke');
            } else {
                return '';
            }
            $file = $reflection->getFileName();
            return is_string($file) ? wp_normalize_path($file) : '';
        } catch (\ReflectionException) {
            return '';
        }
    }

    /** @return list<string> The active theme's directories (parent and child), with a trailing slash. */
    private static function theme_dirs(): array
    {
        $dirs = [get_template_directory(), get_stylesheet_directory()];
        return array_values(array_unique(array_map(static fn(string $dir): string => trailingslashit(wp_normalize_path($dir)), $dirs)));
    }
}
