<?php
/**
 * Hex colours and WCAG contrast. Pure: tested by tests/php/ColorTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class Color
{
    /** '#abc' or '#AABBCC' → '#aabbcc'; null when the value isn't a hex colour. */
    public static function hex(string $value): ?string
    {
        $value = strtolower(trim($value));
        if (1 === preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m)) {
            return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }
        return 1 === preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : null;
    }

    /** WCAG relative luminance of a '#rrggbb' colour: 0 (black) … 1 (white). */
    public static function luminance(string $hex): float
    {
        $channels = array_map(
            static function (string $pair): float {
                $c = hexdec($pair) / 255;
                return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            },
            str_split(substr($hex, 1), 2)
        );
        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /** WCAG contrast ratio of two '#rrggbb' colours: 1 … 21. */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** The text colour that reads better on $background: $light or $dark. */
    public static function readable_on(string $background, string $light = '#ffffff', string $dark = '#0a0a0a'): string
    {
        return self::contrast($background, $light) >= self::contrast($background, $dark) ? $light : $dark;
    }
}
