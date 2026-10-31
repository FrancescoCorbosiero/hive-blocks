<?php
/**
 * A rating out of five as the Trustpilot block shows it: five stars
 * rounded down to the half star, and the numbers written the Italian way.
 * Pure: tested by tests/php/StarRatingTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class StarRating
{
    /** Any input → a rating between 0 and 5, to one decimal. */
    public static function clamp(mixed $rating): float
    {
        return is_numeric($rating) ? round(max(0.0, min(5.0, (float) $rating)), 1) : 0.0;
    }

    /**
     * The five stars, rounded down to the half star: 4.8 → four full and a
     * half, 4.4 → four full and an empty one.
     *
     * @return list<'full'|'half'|'empty'>
     */
    public static function stars(mixed $rating): array
    {
        $rating = self::clamp($rating);
        $full   = (int) floor($rating);
        $stars  = array_fill(0, $full, 'full');
        if ($full < 5 && $rating - $full >= 0.5) {
            $stars[] = 'half';
        }
        return array_pad($stars, 5, 'empty');
    }

    /** 4.8 → '4,8'. */
    public static function score(mixed $rating): string
    {
        return number_format(self::clamp($rating), 1, ',', '.');
    }

    /** 1250 → '1.250'; anything that isn't a count → '0'. */
    public static function count(mixed $count): string
    {
        return number_format(is_numeric($count) ? max(0, (int) $count) : 0, 0, ',', '.');
    }
}
