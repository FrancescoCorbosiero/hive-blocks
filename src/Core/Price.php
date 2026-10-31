<?php
/**
 * The sale badge a card shows. Pure: tested by tests/php/PriceTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class Price
{
    /**
     * "-20%" when every discounted price is cut by the same share, "Fino a
     * -30%" when they differ (a variable product), '' when nothing is.
     *
     * @param list<array{0: float|int|string, 1: float|int|string}> $pairs [regular, current] per variation (or one, for a simple product).
     */
    public static function badge(array $pairs): string
    {
        $cuts = [];
        foreach ($pairs as [$regular, $current]) {
            $regular = (float) $regular;
            $current = (float) $current;
            if ($regular > 0 && $current >= 0 && $current < $regular) {
                $cuts[] = (int) round(100 * ($regular - $current) / $regular);
            }
        }
        $cuts = array_filter($cuts);
        if (!$cuts) {
            return '';
        }
        $max = max($cuts);
        return (min($cuts) === $max && count($cuts) === count($pairs) ? '' : 'Fino a ') . '-' . $max . '%';
    }
}
