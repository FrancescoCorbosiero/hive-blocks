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
     * Names of every block in parsed content, inner blocks and synced
     * patterns (core/block) included, each once.
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
            if (!is_string($name)) {
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
