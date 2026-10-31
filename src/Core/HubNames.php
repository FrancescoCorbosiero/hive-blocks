<?php
/**
 * The names the Store Hub app knows. The page holds Hive's blocks
 * (hive/shortcode-wrapper, hive/category-slider…) and Hive's rail shortcode
 * ([hive_product_rail …]); the app knows them by their golden-hive-blocks
 * names (golden-hive/shortcode-wrapper, [gh_product_rail …]) and isn't
 * changing. So the bridge's payloads speak the app's names, and its writes
 * accept both. One map, both ways:
 *
 *   hive/x               ↔  golden-hive/x
 *   [hive_product_rail]  →  [gh_product_rail]   (the app never sends one)
 *
 * Pure: tested by tests/php/HubTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class HubNames
{
    /** Block namespaces: Hive's => the app's. */
    public const BLOCKS = ['hive/' => 'golden-hive/'];

    /** Shortcode tags: Hive's => the app's. */
    public const SHORTCODES = ['hive_product_rail' => 'gh_product_rail'];

    /** A block name as the app knows it: hive/x → golden-hive/x; any other name as it is. */
    public static function block_to_app(string $name): string
    {
        foreach (self::BLOCKS as $hive => $app) {
            if (str_starts_with($name, $hive)) {
                return $app . substr($name, strlen($hive));
            }
        }
        return $name;
    }

    /**
     * A block name the app sent, as the page holds it: golden-hive/x →
     * hive/x. A Hive name, or any other, comes back as it is.
     */
    public static function block_from_app(string $name): string
    {
        foreach (self::BLOCKS as $hive => $app) {
            if (str_starts_with($name, $app)) {
                return $hive . substr($name, strlen($app));
            }
        }
        return $name;
    }

    /** A shortcode as the app knows it: [hive_product_rail …] → [gh_product_rail …]; any other as it is. */
    public static function shortcode_to_app(string $shortcode): string
    {
        foreach (self::SHORTCODES as $hive => $app) {
            $renamed = preg_replace('/^(\s*\[)' . preg_quote($hive, '/') . '(?![\w-])/', '${1}' . $app, $shortcode, 1, $count);
            if (is_string($renamed) && $count > 0) {
                return $renamed;
            }
        }
        return $shortcode;
    }
}
