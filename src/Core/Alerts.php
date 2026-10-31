<?php
/**
 * Who hears about an arrival (Hive\Shop\Alerts): the size alerts ("a pair
 * in my size arrived", optionally of some brands) and the waitlist ("this
 * product, in this size, is back"), one email per person with every match.
 *
 * An arrival is a product and the size keys that came into stock ("42"; a
 * product without sizes has the key "*"). A subscriber's waitlist entries
 * are "productId|key" ("812|42", "815|*"). Nobody hears twice about the
 * same product and size ("told").
 *
 * Pure logic: no WordPress calls. Tested in tests/php/AlertsTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class Alerts
{
    /** The key of a product without sizes. */
    public const ANY = '*';

    /**
     * @param array<int, array{keys: list<string>, t: int}> $live      Product id => the keys that arrived, and when.
     * @param list<string>                                  $sizes     The subscriber's sizes.
     * @param list<string>                                  $brands    Their brands (none: any brand).
     * @param array<int, list<string>>                      $brands_of Product id => its brand slugs.
     * @param list<string>                                  $waits     Their waitlist: "productId|key".
     * @param list<string>                                  $told      Already sent: "productId|key".
     * @return array<int, list<string>> Product id => keys, newest arrival first.
     */
    public static function pick(array $live, array $sizes, array $brands, array $brands_of, array $waits, array $told): array
    {
        uasort($live, static fn(array $a, array $b): int => ((int) ($b['t'] ?? 0)) <=> ((int) ($a['t'] ?? 0)));
        $told  = array_flip($told);
        $waits = array_flip($waits);
        $out   = [];
        foreach ($live as $pid => $arrival) {
            $keys = [];
            $by_size = $sizes && (!$brands || array_intersect($brands, (array) ($brands_of[$pid] ?? [])));
            foreach ((array) ($arrival['keys'] ?? []) as $key) {
                $key = (string) $key;
                if (isset($told[$pid . '|' . $key])) {
                    continue;
                }
                if (isset($waits[$pid . '|' . $key]) || ($by_size && in_array($key, $sizes, true))) {
                    $keys[] = $key;
                }
            }
            if ($keys) {
                $out[$pid] = array_values(array_unique($keys));
            }
        }
        return $out;
    }

    /**
     * Newly confirmed sizes first, then the ones already there, at most $max.
     *
     * @param list<mixed> $current
     * @param list<mixed> $new
     * @return list<string>
     */
    public static function merge_sizes(array $current, array $new, int $max = 3): array
    {
        $sizes = array_filter(array_map('strval', array_merge($new, $current)), 'strlen');
        return array_slice(array_values(array_unique($sizes)), 0, $max);
    }

    /**
     * Waitlist entries after a confirmation or a send: the new ones added,
     * the ones just told removed, the newest $max kept.
     *
     * @param list<string> $current
     * @param list<string> $add
     * @param list<string> $remove
     * @return list<string>
     */
    public static function waits(array $current, array $add, array $remove = [], int $max = 50): array
    {
        $all = array_values(array_unique(array_filter(array_map('strval', array_merge($current, $add)), [self::class, 'is_wait'])));
        $all = array_values(array_diff($all, $remove));
        return array_slice($all, -$max);
    }

    /** "812|42", "815|*". */
    public static function is_wait(string $entry): bool
    {
        return 1 === preg_match('/^[1-9]\d*\|(\*|[a-z0-9]{1,20})$/', $entry);
    }

    /** "42", "42 e 43", "40, 42 e 43". */
    public static function labels(array $labels): string
    {
        $labels = array_values(array_filter(array_map('strval', $labels), 'strlen'));
        if (count($labels) < 2) {
            return $labels[0] ?? '';
        }
        $last = array_pop($labels);
        return implode(', ', $labels) . ' e ' . $last;
    }
}
