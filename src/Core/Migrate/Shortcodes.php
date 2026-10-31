<?php
/**
 * The migration's shortcode renames ([gh_product_rail …] → [hive_product_rail
 * …]): opening and closing tags, attributes kept; a tag mapped to '' goes,
 * with whatever it wrapped.
 *
 * Pure logic: no WordPress calls. Tested in tests/php/MigrateBlocksTest.php.
 *
 * @package Hive
 */

namespace Hive\Core\Migrate;

defined('ABSPATH') || exit;

final class Shortcodes
{
    /** @param array<string, string> $map Old tag => new tag ('' removes). */
    public static function rename(string $text, array $map): string
    {
        if (!str_contains($text, '[') || !$map) {
            return $text;
        }
        foreach ($map as $old => $new) {
            $tag = preg_quote($old, '/');
            if ('' === $new) {
                // [tag …]…[/tag] or [tag …] alone.
                $text = (string) preg_replace('/\[' . $tag . '(?=[\s\]\/])[^\]]*\](?:.*?\[\/' . $tag . '\])?/s', '', $text);
                continue;
            }
            $text = (string) preg_replace('/\[(\/?)' . $tag . '(?=[\s\]\/])/', '[$1' . $new, $text);
        }
        return $text;
    }

    /** Old shortcode tags a text has. */
    public static function tags_in(string $text, array $map): array
    {
        $found = [];
        foreach (array_keys($map) as $old) {
            if (1 === preg_match('/\[' . preg_quote($old, '/') . '(?=[\s\]\/])/', $text)) {
                $found[] = $old;
            }
        }
        return $found;
    }
}
