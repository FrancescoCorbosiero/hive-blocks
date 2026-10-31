<?php
/**
 * The block fields the Store Hub's Vetrina may write, and how each is
 * checked: a section's texts, button and switch, and the titles of the other
 * homepage blocks. Anything not listed here cannot be changed through
 * wc-gh/v1, whatever the Hub sends: the list is the upper bound, the Hub's
 * config picks from it. Keyed by Hive's block names; the app knows them by
 * HubNames::block_to_app(). The same rules as golden-hive-blocks'
 * ghb_hub_field_specs(), ghb_hub_block_fields() and ghb_hub_apply_fields().
 * Pure: tested by tests/php/HubTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class HubFields
{
    /**
     * Block name => field => rule.
     *
     *   type "text"  plain text (tags stripped), at most max characters
     *   type "url"   an http(s) or site-relative link, or empty
     *   type "enum"  one of options
     *
     * A section's "hidden" is its switch: "yes" keeps the section on the
     * page, with its products and their order, and shows nothing on the site.
     */
    public const SPECS = [
        HubRail::BLOCK         => [
            'eyebrow'         => ['type' => 'text', 'max' => 80],
            'title'           => ['type' => 'text', 'max' => 120],
            'backgroundColor' => ['type' => 'enum', 'options' => ['white', 'gray', 'black']],
            'buttonText'      => ['type' => 'text', 'max' => 60],
            'buttonUrl'       => ['type' => 'url', 'max' => 500],
            'hidden'          => ['type' => 'enum', 'options' => ['', 'yes']],
        ],
        'hive/category-slider' => [
            'title' => ['type' => 'text', 'max' => 120],
        ],
        'hive/brand-marquee'   => [
            'title' => ['type' => 'text', 'max' => 120],
        ],
        'hive/faq-schema'      => [
            'title'    => ['type' => 'text', 'max' => 120],
            'subtitle' => ['type' => 'text', 'max' => 300],
        ],
        'hive/social-proof'    => [
            'title' => ['type' => 'text', 'max' => 120],
        ],
        'hive/whatsapp-button' => [
            'buttonText' => ['type' => 'text', 'max' => 60],
            'message'    => ['type' => 'text', 'max' => 300],
        ],
    ];

    /**
     * A block's editable fields as the site renders them: the saved value,
     * or the block's default when the attribute is not saved.
     *
     * @param array<string, array>  $spec     field => rule (SPECS)
     * @param array<string, string> $defaults field => the block type's default
     * @return array<string, string>
     */
    public static function read(array $attrs, array $spec, array $defaults): array
    {
        $fields = [];
        foreach (array_keys($spec) as $field) {
            $value          = array_key_exists($field, $attrs) ? $attrs[$field] : ($defaults[$field] ?? '');
            $fields[$field] = is_scalar($value) ? (string) $value : '';
        }
        return $fields;
    }

    /**
     * Apply field edits to a block's attributes. Every field must be in the
     * block's spec; each value goes through $clean (WordPress's sanitizers,
     * injected so this stays testable without WordPress) and is then checked
     * against its rule. A value equal to the block's default is removed
     * rather than written, as the rail shortcode does.
     *
     * @param array<string, mixed>  $changes  field => new value
     * @param array<string, array>  $spec     field => rule
     * @param array<string, string> $defaults field => the block type's default
     * @param callable(string, string): string $clean (rule type, raw value) => clean value
     * @return array{ok: true, attrs: array, changed: list<string>}|array{ok: false, error: string, field: string}
     */
    public static function apply(array $attrs, array $changes, array $spec, array $defaults, callable $clean): array
    {
        $current = self::read($attrs, $spec, $defaults);
        $changed = [];
        foreach ($changes as $field => $raw) {
            $field = (string) $field;
            if (!isset($spec[$field])) {
                return ['ok' => false, 'error' => 'not_editable', 'field' => $field];
            }
            if (!is_string($raw)) {
                return ['ok' => false, 'error' => 'not_text', 'field' => $field];
            }
            $rule  = $spec[$field];
            $value = (string) $clean($rule['type'], $raw);
            if ('enum' === $rule['type'] && !in_array($value, $rule['options'], true)) {
                return ['ok' => false, 'error' => 'not_an_option', 'field' => $field];
            }
            if ('url' === $rule['type'] && '' === $value && '' !== trim($raw)) {
                return ['ok' => false, 'error' => 'bad_url', 'field' => $field];
            }
            if (isset($rule['max']) && mb_strlen($value) > (int) $rule['max']) {
                return ['ok' => false, 'error' => 'too_long', 'field' => $field];
            }
            if ($value === $current[$field]) {
                continue;
            }
            if ($value === (string) ($defaults[$field] ?? '')) {
                unset($attrs[$field]);
            } else {
                $attrs[$field] = $value;
            }
            $current[$field] = $value;
            $changed[]       = $field;
        }
        return ['ok' => true, 'attrs' => $attrs, 'changed' => $changed];
    }
}
