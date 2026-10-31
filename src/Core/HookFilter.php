<?php
/**
 * Which callbacks of a WordPress hook to leave out when Hive fires it on
 * its own page (Hive\Shop\Hooks): WooCommerce's defaults that Hive draws
 * itself (title, price, gallery, tabs…), the theme's callbacks (Hive's page
 * is not the theme's), and, optionally, the priorities outside a range (a
 * hook split around Hive's add-to-cart form).
 *
 * Pure logic: no WordPress calls. Tested in tests/php/HookFilterTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class HookFilter
{
    /**
     * @param array<int, array<string, array{function: mixed, accepted_args?: int}>> $callbacks A WP_Hook's callbacks.
     * @param list<string> $skip     Callbacks to leave out, by name: 'function' or 'Class::method'.
     * @param callable(mixed): string $file_of The file a callback is defined in ('' when unknown).
     * @param list<string> $dirs     Leave out callbacks defined under these directories (with a trailing slash).
     * @param int|null $min          Leave out priorities below this.
     * @param int|null $max          Leave out priorities above this.
     * @return list<array{0: int, 1: string}> [priority, callback id] pairs.
     */
    public static function excluded(array $callbacks, array $skip, callable $file_of, array $dirs, ?int $min = null, ?int $max = null): array
    {
        $out = [];
        foreach ($callbacks as $priority => $entries) {
            $priority = (int) $priority;
            foreach ($entries as $id => $entry) {
                $function = $entry['function'] ?? null;
                if (
                    (null !== $min && $priority < $min)
                    || (null !== $max && $priority > $max)
                    || in_array(self::name($function), $skip, true)
                    || self::under(($file_of)($function), $dirs)
                ) {
                    $out[] = [$priority, (string) $id];
                }
            }
        }
        return $out;
    }

    /** 'function', 'Class::method', or '' for closures and invokable objects. */
    public static function name(mixed $function): string
    {
        if (is_string($function)) {
            return ltrim($function, '\\');
        }
        if (is_array($function) && 2 === count($function) && is_string($function[1] ?? null)) {
            $class = is_object($function[0]) ? get_class($function[0]) : (is_string($function[0]) ? ltrim($function[0], '\\') : '');
            return '' === $class ? '' : $class . '::' . $function[1];
        }
        return '';
    }

    /** @param list<string> $dirs */
    private static function under(string $file, array $dirs): bool
    {
        if ('' === $file) {
            return false;
        }
        foreach ($dirs as $dir) {
            if ('' !== $dir && str_starts_with($file, $dir)) {
                return true;
            }
        }
        return false;
    }
}
