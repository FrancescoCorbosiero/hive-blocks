<?php
/**
 * The settings as CSS custom properties, printed right after
 * assets/base.css (which holds every other token). Pure: tested by
 * tests/php/DesignTokensTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class DesignTokens
{
    /**
     * --hive-accent        the brand colour
     * --hive-on-accent     text on it: white or near-black, whichever reads better
     * --hive-round         1 rounded, 0 sharp: every radius is calc(N * var(--hive-round))
     */
    public static function css(array $settings): string
    {
        $settings = SettingsSchema::sanitize($settings);
        $accent   = $settings['accent'];

        return sprintf(
            ':root{--hive-accent:%s;--hive-on-accent:%s;--hive-round:%d}',
            $accent,
            Color::readable_on($accent),
            'sharp' === $settings['shape'] ? 0 : 1
        );
    }
}
