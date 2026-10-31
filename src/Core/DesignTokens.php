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
     * --hive-accent        the brand colour: badges, highlights, focus
     * --hive-on-accent     text on it: white or near-black, whichever reads better
     * --hive-button        the main buttons ("Aggiungi al carrello")
     * --hive-on-button     text on them, chosen the same way
     * --hive-round         1 rounded, 0 sharp: every radius is calc(N * var(--hive-round))
     */
    public static function css(array $settings): string
    {
        $settings = SettingsSchema::sanitize($settings);

        return sprintf(
            ':root{--hive-accent:%s;--hive-on-accent:%s;--hive-button:%s;--hive-on-button:%s;--hive-round:%d}',
            $settings['accent'],
            Color::readable_on($settings['accent']),
            $settings['button'],
            Color::readable_on($settings['button']),
            'sharp' === $settings['shape'] ? 0 : 1
        );
    }
}
