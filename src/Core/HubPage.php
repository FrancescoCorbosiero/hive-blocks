<?php
/**
 * The homepage as the Store Hub's Vetrina sees it: its blocks in document
 * order, each addressed by a path into parse_blocks() output, its sections
 * (HubRail) by a stable key, and the edits the Vetrina makes to it: one
 * block's attributes, a section added, taken away, or the sections put in
 * another order.
 *
 * Every edit works on block delimiters only, each found exactly once in the
 * content, and touches nothing else: no block is re-serialized, no group is
 * opened or closed. Anything else (not found, found twice) is a refusal,
 * never a guess. The same rules as golden-hive-blocks'
 * includes/hub-rails-core.php (ghb_hub_leaf_blocks, ghb_hub_page_rails,
 * ghb_hub_replace_block_attrs, ghb_hub_insert_block…), tested on the real
 * homepage. Pure: tested by tests/php/HubTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class HubPage
{
    /**
     * Leaf blocks of a parsed page in document order, each with its path:
     * the chain of indexes into parse_blocks() output ("3.0" = fourth
     * top-level block, its first inner block). Containers (a group) are
     * walked, not listed; whitespace between blocks (blockName null) is
     * skipped but still counted, so a path always addresses parse_blocks()
     * output directly.
     *
     * @return list<array{0: string, 1: array}>
     */
    public static function leaf_blocks(array $blocks, string $prefix = ''): array
    {
        $out = [];
        foreach ($blocks as $index => $block) {
            if (!is_array($block) || empty($block['blockName'])) {
                continue;
            }
            $path = '' === $prefix ? (string) $index : $prefix . '.' . $index;
            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                foreach (self::leaf_blocks($block['innerBlocks'], $path) as $leaf) {
                    $out[] = $leaf;
                }
                continue;
            }
            $out[] = [$path, $block];
        }
        return $out;
    }

    /** The block at a path produced by leaf_blocks(), or null. */
    public static function block_at_path(array $blocks, string $path): ?array
    {
        if (1 !== preg_match('/^\d+(\.\d+)*$/', $path)) {
            return null;
        }
        $node = null;
        $list = $blocks;
        foreach (explode('.', $path) as $index) {
            $index = (int) $index;
            if (!isset($list[$index]) || !is_array($list[$index])) {
                return null;
            }
            $node = $list[$index];
            $list = isset($node['innerBlocks']) && is_array($node['innerBlocks']) ? $node['innerBlocks'] : [];
        }
        return $node;
    }

    /**
     * Sections of a parsed page, keyed: "category:saldi-sneakers-outlet#0"
     * is the first rail showing that category. The key is how a rail is
     * found again in an older revision, where paths may differ. $legacy:
     * see HubRail::from_block().
     *
     * @return array<string, array{path: string, block: array, rail: array}>
     */
    public static function rails(array $blocks, bool $legacy = false): array
    {
        $rails = [];
        $seen  = [];
        foreach (self::leaf_blocks($blocks) as [$path, $block]) {
            $rail = HubRail::from_block($block, $legacy);
            if (null === $rail) {
                continue;
            }
            $n                       = $seen[$rail['key_base']] ?? 0;
            $seen[$rail['key_base']] = $n + 1;
            $rails[$rail['key_base'] . '#' . $n] = ['path' => $path, 'block' => $block, 'rail' => $rail];
        }
        return $rails;
    }

    /** Fingerprint of a block's attributes: "is it still the block you read?" */
    public static function attrs_hash(array $attrs): string
    {
        return md5((string) json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Replace ONE block's attributes in raw post content, touching nothing
     * else. The block's comment delimiter is rebuilt from its current
     * attributes with WordPress's own serializer (the same escaping the
     * editor used to write it) and must be found exactly once: a whole-page
     * re-serialization would risk changing blocks nobody meant to touch.
     *
     * @param callable $serialize serialize_block_attributes().
     * @return array{ok: bool, content?: string, error?: string, count?: int}
     */
    public static function replace_block_attrs(string $content, string $name, array $old_attrs, array $new_attrs, callable $serialize): array
    {
        $serialized_name = self::serialized_name($name);
        $old_json        = $serialize($old_attrs);
        $new_json        = $serialize($new_attrs);

        $forms = [
            "<!-- wp:{$serialized_name} {$old_json} /-->" => "<!-- wp:{$serialized_name} {$new_json} /-->",
            "<!-- wp:{$serialized_name} {$old_json} -->"  => "<!-- wp:{$serialized_name} {$new_json} -->",
        ];

        $count       = 0;
        $needle      = '';
        $replacement = '';
        foreach ($forms as $old => $new) {
            $n = substr_count($content, $old);
            if ($n > 0) {
                $count      += $n;
                $needle      = $old;
                $replacement = $new;
            }
        }
        if (1 !== $count) {
            return ['ok' => false, 'error' => 0 === $count ? 'not_found' : 'ambiguous', 'count' => $count];
        }

        $pos = strpos($content, $needle);
        return ['ok' => true, 'content' => substr_replace($content, $replacement, (int) $pos, strlen($needle))];
    }

    /*
     * The page's sections: add one, take one away, put them in another
     * order. Every section on the homepage is a self-closing block, and so is
     * every block a section is placed next to.
     */

    /** A block's self-closing delimiter, written the way the editor writes it. */
    public static function block_delimiter(string $name, array $attrs, callable $serialize): string
    {
        $serialized_name = self::serialized_name($name);
        if ([] === $attrs) {
            return "<!-- wp:{$serialized_name} /-->";
        }
        return "<!-- wp:{$serialized_name} " . $serialize($attrs) . ' /-->';
    }

    /**
     * Where a self-closing block sits in the content, found exactly once, or
     * why not: "not_found", "ambiguous" (the same block twice: refusing beats
     * guessing).
     *
     * @return array{ok: bool, offset?: int, length?: int, text?: string, error?: string, count?: int}
     */
    public static function locate_block(string $content, string $name, array $attrs, callable $serialize): array
    {
        $needle = self::block_delimiter($name, $attrs, $serialize);
        $count  = substr_count($content, $needle);
        if (1 !== $count) {
            return ['ok' => false, 'error' => 0 === $count ? 'not_found' : 'ambiguous', 'count' => $count];
        }
        return ['ok' => true, 'offset' => (int) strpos($content, $needle), 'length' => strlen($needle), 'text' => $needle];
    }

    /**
     * A new block, right after (or before) another one, inside the same
     * group when the other is in one, separated the way the editor separates
     * them. A block identical to one already on the page is refused: two
     * equal delimiters would make both impossible to edit from here.
     *
     * @param array $anchor ['blockName' => …, 'attrs' => …] as parsed
     * @param array $block  ['blockName' => …, 'attrs' => …] to add
     * @return array{ok: bool, content?: string, error?: string}
     */
    public static function insert_block(string $content, array $anchor, string $place, array $block, callable $serialize): array
    {
        $at = self::locate_block($content, (string) $anchor['blockName'], (array) $anchor['attrs'], $serialize);
        if (!$at['ok']) {
            return $at;
        }
        $new = self::block_delimiter((string) $block['blockName'], (array) $block['attrs'], $serialize);
        if (str_contains($content, $new)) {
            return ['ok' => false, 'error' => 'duplicate'];
        }
        $text = 'before' === $place ? $new . "\n\n" . $at['text'] : $at['text'] . "\n\n" . $new;
        return ['ok' => true, 'content' => substr_replace($content, $text, $at['offset'], $at['length'])];
    }

    /**
     * Take one self-closing block out, with the blank line that separated it
     * from its neighbour (the one before it, or else the one after), so the
     * page reads as if it had never been there.
     *
     * @return array{ok: bool, content?: string, error?: string}
     */
    public static function remove_block(string $content, array $block, callable $serialize): array
    {
        $at = self::locate_block($content, (string) $block['blockName'], (array) $block['attrs'], $serialize);
        if (!$at['ok']) {
            return $at;
        }
        $start = $at['offset'];
        $end   = $start + $at['length'];
        if ($start >= 2 && "\n\n" === substr($content, $start - 2, 2)) {
            $start -= 2;
        } elseif ("\n\n" === substr($content, $end, 2)) {
            $end += 2;
        }
        return ['ok' => true, 'content' => substr($content, 0, $start) . substr($content, $end)];
    }

    /**
     * The sections in another order, without moving anything else. The page
     * keeps a place for each section ("slot") where it is (in its group,
     * between the same other blocks) and each slot takes the block of the
     * section that goes there now. A section never leaves the part of the
     * page sections live in, so it never changes width or background.
     *
     * @param array     $slots ['blockName' => …, 'attrs' => …] for each section, in page order
     * @param list<int> $order for each slot, the index into $slots of the section it holds now
     * @return array{ok: bool, content?: string, error?: string, slot?: int}
     */
    public static function arrange_blocks(string $content, array $slots, array $order, callable $serialize): array
    {
        $count  = count($slots);
        $sorted = array_map('intval', $order);
        sort($sorted);
        if (count($order) !== $count || $sorted !== ($count > 0 ? range(0, $count - 1) : [])) {
            return ['ok' => false, 'error' => 'not_a_permutation'];
        }
        $found = [];
        foreach (array_values($slots) as $i => $slot) {
            $at = self::locate_block($content, (string) $slot['blockName'], (array) $slot['attrs'], $serialize);
            if (!$at['ok']) {
                return $at + ['slot' => $i];
            }
            $found[$i] = $at;
        }
        // From the last slot to the first, so every offset found above still holds.
        uasort($found, static fn($a, $b) => $b['offset'] <=> $a['offset']);
        $slots = array_values($slots);
        $order = array_values(array_map('intval', $order));
        foreach ($found as $i => $at) {
            $rail    = $slots[$order[$i]];
            $content = substr_replace(
                $content,
                self::block_delimiter((string) $rail['blockName'], (array) $rail['attrs'], $serialize),
                $at['offset'],
                $at['length']
            );
        }
        return ['ok' => true, 'content' => $content];
    }

    /**
     * A short description of a block that is not a section, for the
     * Vetrina's placeholders: its title if it has one, and how many entries
     * its first list attribute holds (slides, cards, logos, questions).
     *
     * @return array{title: string|null, items: int|null, labels: list<string>}
     */
    public static function block_summary(array $block): array
    {
        $attrs   = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
        $summary = ['title' => null, 'items' => null, 'labels' => []];

        foreach (['title', 'eyebrow'] as $key) {
            if (!empty($attrs[$key]) && is_string($attrs[$key])) {
                $summary['title'] = $attrs[$key];
                break;
            }
        }
        foreach ($attrs as $value) {
            if (!is_array($value) || [] === $value || !array_key_exists(0, $value)) {
                continue;
            }
            $summary['items'] = count($value);
            foreach (array_slice($value, 0, 12) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                foreach (['title', 'name', 'question', 'product'] as $label_key) {
                    if (!empty($entry[$label_key]) && is_string($entry[$label_key])) {
                        $summary['labels'][] = $entry[$label_key];
                        break;
                    }
                }
            }
            break;
        }
        if ('core/shortcode' === ($block['blockName'] ?? '')) {
            $summary['title'] = trim(mb_substr(trim((string) ($block['innerHTML'] ?? '')), 0, 80));
        }
        return $summary;
    }

    /** A block's name as its delimiter writes it: core blocks without "core/". */
    private static function serialized_name(string $name): string
    {
        return str_starts_with($name, 'core/') ? substr($name, 5) : $name;
    }
}
