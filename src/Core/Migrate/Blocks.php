<?php
/**
 * The migration's block rewrite (Hive\Migration): golden-hive-blocks'
 * blocks, as WordPress's block parser gives them, into Hive's.
 *
 * A map entry for an old block name says what it becomes:
 *
 *   'name'   the Hive block
 *   'fill'   the old block's defaults: the editor doesn't save an attribute
 *            left at its default, and a merged block's defaults differ, so
 *            what the page showed is written out (before the mapping)
 *   'attrs'  old attribute => new attribute (left out: the name stays)
 *   'drop'   attributes Hive doesn't have
 *   'set'    attributes to add (a layout or a variation, say)
 *   'map'    old attribute => [old value => new value]
 *   'remove' true: the block goes (it has no Hive counterpart)
 *   'resolve' a function given the new attributes and the old ones,
 *            returning the new ones: for what needs WordPress (an image's id
 *            from its URL, say)
 *
 * Shortcodes are renamed wherever they are: in a block's saved HTML and in
 * its string attributes (golden-hive/shortcode-wrapper keeps its shortcode
 * in one). Blocks that aren't golden-hive-blocks' are left as they are,
 * their inner blocks rewritten.
 *
 * Pure logic: no WordPress calls. Tested in tests/php/MigrateBlocksTest.php.
 *
 * @package Hive
 */

namespace Hive\Core\Migrate;

defined('ABSPATH') || exit;

final class Blocks
{
    /**
     * An attribute a 'resolve' sets to say what a person has to finish
     * (a product to pick, a date to rewrite): collected, never saved.
     */
    public const NOTE = 'hiveMigrationNote';

    /**
     * @param list<array<string, mixed>>   $blocks     parse_blocks() output.
     * @param array<string, array>         $map        Old block name => what it becomes.
     * @param array<string, string>        $shortcodes Old shortcode tag => new tag ('' removes it).
     * @param array<string, int>           $count      Filled with how many of each old block were rewritten.
     * @param list<string>                 $notes      Filled with what a person has to finish (see NOTE).
     * @return list<array<string, mixed>>
     */
    public static function rewrite(array $blocks, array $map, array $shortcodes, array &$count = [], array &$notes = []): array
    {
        $out = [];
        foreach ($blocks as $block) {
            $name = (string) ($block['blockName'] ?? '');
            if (isset($map[$name])) {
                $count[$name] = ($count[$name] ?? 0) + 1;
                if (!empty($map[$name]['remove'])) {
                    continue;
                }
                $block = self::block($block, $map[$name]);
                if (isset($block['attrs'][self::NOTE])) {
                    $notes[] = (string) $block['attrs'][self::NOTE];
                    unset($block['attrs'][self::NOTE]);
                }
            }
            $block['attrs'] = self::attrs_shortcodes((array) ($block['attrs'] ?? []), $shortcodes);
            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::rewrite($block['innerBlocks'], $map, $shortcodes, $count, $notes);
            }
            if (isset($block['innerHTML']) && is_string($block['innerHTML'])) {
                $block['innerHTML'] = Shortcodes::rename($block['innerHTML'], $shortcodes);
            }
            foreach ((array) ($block['innerContent'] ?? []) as $i => $chunk) {
                if (is_string($chunk)) {
                    $block['innerContent'][$i] = Shortcodes::rename($chunk, $shortcodes);
                }
            }
            $out[] = $block;
        }
        return $out;
    }

    /** One block, renamed and its attributes mapped. */
    private static function block(array $block, array $spec): array
    {
        $old   = array_merge((array) ($spec['fill'] ?? []), (array) ($block['attrs'] ?? []));
        $attrs = [];
        foreach ($old as $key => $value) {
            if (in_array($key, (array) ($spec['drop'] ?? []), true)) {
                continue;
            }
            if (isset($spec['map'][$key]) && is_scalar($value) && array_key_exists((string) $value, $spec['map'][$key])) {
                $value = $spec['map'][$key][(string) $value];
            }
            $attrs[(string) ($spec['attrs'][$key] ?? $key)] = $value;
        }
        $attrs = array_merge($attrs, (array) ($spec['set'] ?? []));
        if (isset($spec['resolve']) && is_callable($spec['resolve'])) {
            $attrs = (array) ($spec['resolve'])($attrs, $old);
        }
        $block['blockName'] = (string) $spec['name'];
        $block['attrs']     = $attrs;
        return $block;
    }

    /** @param array<string, mixed> $attrs */
    private static function attrs_shortcodes(array $attrs, array $shortcodes): array
    {
        foreach ($attrs as $key => $value) {
            if (is_string($value) && str_contains($value, '[')) {
                $attrs[$key] = Shortcodes::rename($value, $shortcodes);
            }
        }
        return $attrs;
    }

    /** Old block names a post's content has ("golden-hive/hero-split"…), without parsing it. */
    public static function names_in(string $content, string $namespace = 'golden-hive'): array
    {
        preg_match_all('#<!--\s+wp:' . preg_quote($namespace, '#') . '/([a-z0-9-]+)#', $content, $matches);
        return array_values(array_unique(array_map(static fn(string $name): string => $namespace . '/' . $name, $matches[1])));
    }
}
