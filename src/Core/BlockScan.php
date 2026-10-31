<?php
/**
 * Which blocks a piece of content uses. Pure: tested by
 * tests/php/BlockScanTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class BlockScan
{
    /** How deep synced patterns may nest in each other before the scan stops (a loop guard). */
    private const MAX_DEPTH = 5;

    /**
     * Whether the editor hid the block (its toolbar's "Nascondi", WordPress
     * 6.9+): nothing of it shows, inner blocks included. Hidden only on some
     * screens (a viewport list, WordPress 7.0+) isn't hidden: it renders,
     * and CSS hides it there.
     */
    public static function hidden(mixed $block): bool
    {
        return is_array($block) && false === ($block['attrs']['metadata']['blockVisibility'] ?? null);
    }

    /**
     * Names of every block in parsed content that shows, inner blocks and
     * synced patterns (core/block) included, each once. A hidden block, and
     * what's in it, isn't counted.
     *
     * @param array    $blocks parse_blocks() output.
     * @param callable $synced fn(int $ref): array, the parsed blocks of a synced pattern.
     * @return list<string>
     */
    public static function names(array $blocks, callable $synced, int $depth = 0): array
    {
        $names = [];
        foreach ($blocks as $block) {
            $name = is_array($block) ? ($block['blockName'] ?? null) : null;
            if (!is_string($name) || self::hidden($block)) {
                continue;
            }
            $names[$name] = true;

            $nested = is_array($block['innerBlocks'] ?? null) ? self::names($block['innerBlocks'], $synced, $depth) : [];
            $ref    = (int) ($block['attrs']['ref'] ?? 0);
            if ('core/block' === $name && $ref > 0 && $depth < self::MAX_DEPTH) {
                $nested = array_merge($nested, self::names((array) $synced($ref), $synced, $depth + 1));
            }
            foreach ($nested as $inner) {
                $names[$inner] = true;
            }
        }
        return array_keys($names);
    }
}
