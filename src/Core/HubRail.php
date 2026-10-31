<?php
/**
 * A homepage section as the Store Hub's Vetrina edits it: a
 * hive/shortcode-wrapper block whose shortcode attribute holds a rail,
 *
 *   [hive_product_rail category="saldi" limit="18" pin="12,5,9" exclude="4" fallback="date"]
 *
 * This reads and rewrites that shortcode: the rail's order (pin, exclude,
 * fallback, limit), what it shows (a category, brand or tag, or a shop-wide
 * list), a new rail's shortcode and block. The ordering rule itself is
 * RailOrder's. The same decisions as golden-hive-blocks'
 * includes/hub-rails-core.php (ghb_hub_parse_shortcode, ghb_hub_set_rail_atts,
 * ghb_hub_set_rail_source, ghb_hub_new_rail_shortcode, ghb_hub_rail_from_block…).
 * Pure: tested by tests/php/HubTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class HubRail
{
    /** The block that holds a section's rail, in its "shortcode" attribute. */
    public const BLOCK = 'hive/shortcode-wrapper';

    /** The shortcode the Vetrina edits. */
    public const TAG = 'hive_product_rail';

    /** The shortcode attributes that say what a rail shows. */
    public const SOURCE_ATTS = ['category', 'brand', 'tag', 'ids', 'type'];

    /** The shortcode attributes the Vetrina writes for the rail's order. */
    public const ORDER_ATTS = ['pin', 'exclude', 'fallback'];

    /** The rail layout a new rail copies from the one it is placed next to. */
    public const LAYOUT_ATTS = ['columns', 'columns_tablet', 'columns_mobile', 'ratio', 'fit'];

    /** The taxonomies a section's source can name. */
    public const SOURCE_TAXONOMIES = ['category', 'brand', 'tag'];

    /**
     * Read one self-closing shortcode ("[tag a="1" b='2' c=3]"), keeping the
     * attributes in the order they were written. The attribute grammar is
     * WordPress's own (shortcode_parse_atts), repeated here so the rewrites
     * below work, and are tested, without WordPress.
     *
     * @return array{tag: string, atts: array}|null Null when the text is not exactly one shortcode.
     */
    public static function parse_shortcode(string $text): ?array
    {
        $text = trim($text);
        if (1 !== preg_match('/^\[([\w-]+)((?:\s[^\[\]]*)?)\]$/s', $text, $m)) {
            return null;
        }
        $atts    = [];
        $pattern = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)/';
        $body    = preg_replace("/[\x{00a0}\x{200b}]+/u", ' ', $m[2]);
        if (preg_match_all($pattern, (string) $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!empty($match[1])) {
                    $atts[strtolower($match[1])] = stripcslashes($match[2]);
                } elseif (!empty($match[3])) {
                    $atts[strtolower($match[3])] = stripcslashes($match[4]);
                } elseif (!empty($match[5])) {
                    $atts[strtolower($match[5])] = stripcslashes($match[6]);
                } elseif (isset($match[7]) && strlen($match[7])) {
                    $atts[] = stripcslashes($match[7]);
                } elseif (isset($match[8]) && strlen($match[8])) {
                    $atts[] = stripcslashes($match[8]);
                } elseif (isset($match[9])) {
                    $atts[] = stripcslashes($match[9]);
                }
            }
        }
        return ['tag' => $m[1], 'atts' => $atts];
    }

    /**
     * Write a shortcode back: [tag k="v" …], attributes in the given order.
     * Null when a value cannot be written safely (a quote or a bracket would
     * end the attribute or the shortcode early).
     */
    public static function build_shortcode(string $tag, array $atts): ?string
    {
        $parts = ['[' . $tag];
        foreach ($atts as $key => $value) {
            $value = (string) $value;
            if (false !== strpbrk($value, '"[]')) {
                return null;
            }
            $parts[] = is_int($key) ? '"' . $value . '"' : $key . '="' . $value . '"';
        }
        return implode(' ', $parts) . ']';
    }

    /**
     * Rewrite a rail shortcode with new pin / exclude / fallback values (and
     * limit, when given). Every other attribute keeps its value and its
     * place; values that say "nothing" (no pins, no exclusions, the default
     * order) are removed rather than written, so an untouched rail keeps an
     * untouched shortcode.
     *
     * @param array{pin?: mixed, exclude?: mixed, fallback?: mixed, limit?: int|null} $values
     * @return string|null Null when the text is not a rail shortcode.
     */
    public static function set_atts(string $shortcode, array $values): ?string
    {
        $parsed = self::parse_shortcode($shortcode);
        if (!$parsed || self::TAG !== $parsed['tag']) {
            return null;
        }
        $atts = $parsed['atts'];

        $pin      = implode(',', RailOrder::parse_ids($values['pin'] ?? []));
        $exclude  = implode(',', RailOrder::parse_ids($values['exclude'] ?? []));
        $default  = RailOrder::default_fallback($atts);
        $wanted   = isset($values['fallback']) && is_scalar($values['fallback']) ? (string) $values['fallback'] : '';
        $fallback = in_array($wanted, RailOrder::FALLBACKS, true) ? $wanted : $default;

        foreach (['pin' => $pin, 'exclude' => $exclude] as $key => $value) {
            if ('' === $value) {
                unset($atts[$key]);
            } else {
                $atts[$key] = $value;
            }
        }
        if ($fallback === $default) {
            unset($atts['fallback']);
        } else {
            $atts['fallback'] = $fallback;
        }
        // How many products the rail shows: only when asked, never inferred.
        if (isset($values['limit'])) {
            $atts['limit'] = (string) max(1, (int) $values['limit']);
        }

        return self::build_shortcode($parsed['tag'], $atts);
    }

    /**
     * What a rail should show, as the Hub asks for it, checked for its shape
     * only (whether the term exists is the WordPress side's question):
     *
     *   { "taxonomy": "category" | "brand" | "tag", "slug": "saldi" }
     *   { "type": "recent" | "best_selling" | "sale" | "featured" | "top_rated" }
     *
     * @return array{kind: string, taxonomy?: string, slug?: string, type?: string}|null Null when malformed.
     */
    public static function parse_source(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }
        if (array_key_exists('type', $raw)) {
            $type = is_string($raw['type']) ? strtolower(trim($raw['type'])) : '';
            return in_array($type, RailOrder::TYPES, true) ? ['kind' => 'type', 'type' => $type] : null;
        }
        $taxonomy = isset($raw['taxonomy']) && is_string($raw['taxonomy']) ? $raw['taxonomy'] : '';
        $slug     = isset($raw['slug']) && is_string($raw['slug']) ? trim($raw['slug']) : '';
        if (!in_array($taxonomy, self::SOURCE_TAXONOMIES, true)) {
            return null;
        }
        // A slug as WordPress makes them: lowercase letters, digits, dashes,
        // underscores, and the percent escapes sanitize_title() leaves for
        // accented letters. Nothing that could end the attribute early.
        if ('' === $slug || strlen($slug) > 200 || 1 !== preg_match('/^[a-z0-9_%-]+$/', $slug)) {
            return null;
        }
        return ['kind' => 'term', 'taxonomy' => $taxonomy, 'slug' => $slug];
    }

    /** A source as shortcode attributes: category="…", brand="…", tag="…" or type="…". */
    public static function source_atts(array $source): array
    {
        return 'type' === $source['kind'] ? ['type' => $source['type']] : [$source['taxonomy'] => $source['slug']];
    }

    /**
     * Point a rail at another source: a category, a brand, a tag, or a
     * shop-wide list. Whatever said what the rail showed goes, and so do its
     * pins, exclusions and fallback: they were about the old products; the
     * caller writes the new ones over the result (set_atts). Every other
     * attribute (limit, columns, ratio…) keeps its value and its place, and
     * the new source takes the old one's.
     *
     * @return string|null Null when the text is not a rail shortcode.
     */
    public static function set_source(string $shortcode, array $source): ?string
    {
        $parsed = self::parse_shortcode($shortcode);
        if (!$parsed || self::TAG !== $parsed['tag']) {
            return null;
        }
        $atts   = [];
        $placed = false;
        foreach ($parsed['atts'] as $key => $value) {
            if (is_string($key) && in_array($key, self::SOURCE_ATTS, true)) {
                if (!$placed) {
                    $atts  += self::source_atts($source);
                    $placed = true;
                }
                continue;
            }
            if (is_string($key) && in_array($key, self::ORDER_ATTS, true)) {
                continue;
            }
            $atts[$key] = $value;
        }
        if (!$placed) {
            $atts = self::source_atts($source) + $atts;
        }
        return self::build_shortcode($parsed['tag'], $atts);
    }

    /**
     * A rail layout value, checked: columns 1–8, a ratio alias or "N/N", a
     * fit. Null when it is not one the rail would read the same way.
     */
    public static function clean_layout_value(string $key, mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = strtolower(trim((string) $value));
        switch ($key) {
            case 'columns':
            case 'columns_tablet':
            case 'columns_mobile':
                return 1 === preg_match('/^[1-8]$/', $value) ? $value : null;
            case 'ratio':
                return 1 === preg_match('#^(square|portrait|tall|landscape|wide|\d{1,2}/\d{1,2})$#', $value) ? $value : null;
            case 'fit':
                return in_array($value, ['cover', 'contain'], true) ? $value : null;
        }
        return null;
    }

    /**
     * The shortcode of a new rail, built from checked values only: what it
     * shows, how many products, the layout of the rail it is placed next to
     * (so it looks like its neighbours; values given in $layout win), then
     * its pins, exclusions and fallback.
     *
     * @param array $order { pin?: int[], exclude?: int[], fallback?: string }
     * @return string|null Null when a value cannot be written safely.
     */
    public static function new_shortcode(array $source, int $limit, array $neighbour_atts, array $layout, array $order): ?string
    {
        $atts          = self::source_atts($source);
        $atts['limit'] = (string) max(1, min(RailOrder::MAX_IDS, $limit));
        foreach (self::LAYOUT_ATTS as $key) {
            $value = array_key_exists($key, $layout) ? $layout[$key] : ($neighbour_atts[$key] ?? null);
            $clean = null === $value ? null : self::clean_layout_value($key, $value);
            if (null !== $clean) {
                $atts[$key] = $clean;
            }
        }
        $shortcode = self::build_shortcode(self::TAG, $atts);
        if (null === $shortcode) {
            return null;
        }
        return self::set_atts(
            $shortcode,
            [
                'pin'      => $order['pin'] ?? [],
                'exclude'  => $order['exclude'] ?? [],
                'fallback' => $order['fallback'] ?? '',
            ]
        );
    }

    /**
     * A new rail block's attributes in the order the editor writes them (its
     * texts around the shortcode), with the fields at their defaults left
     * out, as HubFields::apply() leaves them.
     */
    public static function new_attrs(string $shortcode, array $field_attrs): array
    {
        $attrs = [];
        foreach (['eyebrow', 'title'] as $field) {
            if (isset($field_attrs[$field])) {
                $attrs[$field] = $field_attrs[$field];
            }
        }
        $attrs['shortcode'] = $shortcode;
        foreach ($field_attrs as $field => $value) {
            if (!isset($attrs[$field])) {
                $attrs[$field] = $value;
            }
        }
        return $attrs;
    }

    /**
     * The rail inside a block, when the block is a section: a shortcode
     * wrapper holding a rail shortcode. With $legacy, the names the block and
     * the shortcode had in golden-hive-blocks count too (HubNames), so a
     * rail is found in the page's revisions from before the switch-over.
     * Pure: taxonomy names are left for the WordPress side.
     *
     * @return array{shortcode: string, atts: array, pin: list<int>, exclude: list<int>, fallback: string, fallback_default: string, key_base: string}|null
     */
    public static function from_block(array $block, bool $legacy = false): ?array
    {
        $blocks = [self::BLOCK];
        $tags   = [self::TAG];
        if ($legacy) {
            $blocks[] = HubNames::block_to_app(self::BLOCK);
            $tags[]   = HubNames::SHORTCODES[self::TAG];
        }
        if (!in_array($block['blockName'] ?? null, $blocks, true)) {
            return null;
        }
        $raw       = $block['attrs']['shortcode'] ?? '';
        $shortcode = is_scalar($raw) ? (string) $raw : '';
        $parsed    = self::parse_shortcode($shortcode);
        if (!$parsed || !in_array($parsed['tag'], $tags, true)) {
            return null;
        }
        $atts = $parsed['atts'];

        // A stable name for the rail that survives blocks being added above
        // it: what it shows, not where it sits. "#n" tells apart two rails
        // showing the same thing (added by the caller, which sees the page).
        if (!empty($atts['category'])) {
            $key_base = 'category:' . $atts['category'];
        } elseif (!empty($atts['brand'])) {
            $key_base = 'brand:' . $atts['brand'];
        } elseif (!empty($atts['tag'])) {
            $key_base = 'tag:' . $atts['tag'];
        } elseif (!empty($atts['ids'])) {
            $key_base = 'ids';
        } else {
            $key_base = 'type:' . ($atts['type'] ?? 'recent');
        }

        return [
            'shortcode'        => $shortcode,
            'atts'             => $atts,
            'pin'              => RailOrder::parse_ids($atts['pin'] ?? ''),
            'exclude'          => RailOrder::parse_ids($atts['exclude'] ?? ''),
            'fallback'         => RailOrder::fallback($atts['fallback'] ?? '', $atts),
            'fallback_default' => RailOrder::default_fallback($atts),
            'key_base'         => $key_base,
        ];
    }
}
