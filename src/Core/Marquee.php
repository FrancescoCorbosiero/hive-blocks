<?php
/**
 * The brand marquee's arithmetic (blocks/brand-marquee). The loop is CSS
 * only: two identical tracks side by side, each sliding left by its own
 * width, so the second takes the first one's place and the seam never
 * shows. That needs a track at least as wide as the screen, so a short list
 * is repeated, and the server works out how long a lap takes at the speed
 * the block asks for (no script measures it).
 *
 * Pure logic: no WordPress calls. Tested in tests/php/MarqueeTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class Marquee
{
    /** Items a track needs to cover a wide screen (about 200px each). */
    public const MIN_ITEMS = 10;

    /** Width of one item and the gap after it, in px, as style.css draws them. */
    public const PITCH = 200;

    /** How many times a list of $count items goes into one track; 0 for an empty list. */
    public static function copies(int $count, int $min = self::MIN_ITEMS): int
    {
        return $count < 1 ? 0 : max(1, (int) ceil($min / $count));
    }

    /**
     * Seconds a track of $items items takes to slide by, at $speed px per
     * second (1–200, as the block's setting): at least 5.
     */
    public static function duration(int $items, int $speed): int
    {
        $speed = max(1, min(200, $speed));
        return max(5, (int) round(max(0, $items) * self::PITCH / $speed));
    }
}
