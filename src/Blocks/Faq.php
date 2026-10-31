<?php
/**
 * The page's FAQPage structured data (schema.org), printed once, by the
 * server: every FAQ block on the page adds its questions here and one
 * <script type="application/ld+json"> goes out in the footer, after all of
 * them have rendered (Core\FaqSchema builds it: a question that comes twice
 * is there once). A block rendered after the footer prints its own.
 *
 * @package Hive
 */

namespace Hive\Blocks;

use Hive\Core\FaqSchema;

defined('ABSPATH') || exit;

final class Faq
{
    /** @var list<array{question: string, answer: string}> The questions of the FAQ blocks rendered so far. */
    private static array $items = [];

    /**
     * A FAQ block's questions, for the page's FAQPage. Returns what the block
     * prints: nothing, or the data itself when the footer has already gone.
     *
     * @param list<array{question: string, answer: string}> $items Core\FaqSchema::items().
     */
    public static function add(array $items): string
    {
        if (!$items) {
            return '';
        }
        if (did_action('wp_footer')) {
            return self::script($items);
        }
        if (!self::$items) {
            add_action('wp_footer', [self::class, 'print_schema']);
        }
        array_push(self::$items, ...$items);
        return '';
    }

    /** Hooked to wp_footer by add(). */
    public static function print_schema(): void
    {
        $items       = self::$items;
        self::$items = [];
        echo self::script($items); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with <, > and & escaped (script()).
    }

    /**
     * The data as a script tag. JSON_HEX_TAG and JSON_HEX_AMP write <, > and
     * & as \u escapes, so nothing in an answer can close the tag.
     */
    private static function script(array $items): string
    {
        $data = FaqSchema::page($items);
        $json = null === $data ? false : wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($json) ? '<script type="application/ld+json">' . $json . '</script>' . "\n" : '';
    }
}
