<?php
/**
 * The block fields the Store Hub may write (Core\HubFields), on WordPress:
 * filterable for a site that wants fewer (hive_hub_editable_fields, keyed by
 * Hive's block names), read with the block type's own defaults, written
 * through WordPress's sanitizers, refused in the words the Hub shows.
 *
 * @package Hive
 */

namespace Hive\Hub;

use Hive\Core\HubFields;
use Hive\Core\HubNames;

defined('ABSPATH') || exit;

final class Fields
{
    /**
     * Block name => field => rule. Whatever the filter returns is still
     * sanitized by type on write.
     *
     * @return array<string, array<string, array>>
     */
    public static function specs(): array
    {
        return (array) apply_filters('hive_hub_editable_fields', HubFields::SPECS);
    }

    /** The same, keyed by the names the app knows the blocks by. */
    public static function app_specs(): array
    {
        $specs = [];
        foreach (self::specs() as $name => $spec) {
            $specs[HubNames::block_to_app((string) $name)] = $spec;
        }
        return $specs;
    }

    /**
     * The block type's own defaults (block.json) for the given fields; ''
     * for a field without one.
     *
     * @return array<string, string>
     */
    public static function defaults(string $block_name, array $spec): array
    {
        $type     = \WP_Block_Type_Registry::get_instance()->get_registered($block_name);
        $defaults = [];
        foreach (array_keys($spec) as $field) {
            $default          = $type && isset($type->attributes[$field]['default']) ? $type->attributes[$field]['default'] : '';
            $defaults[$field] = is_scalar($default) ? (string) $default : '';
        }
        return $defaults;
    }

    /**
     * WordPress's sanitizer for each field type. The page is written back
     * with kses off (it must keep its own markup), so this is what stands
     * between a field and the page: text loses its tags, a link must be
     * http(s) or local.
     */
    public static function clean(string $type, string $value): string
    {
        switch ($type) {
            case 'url':
                $value = trim($value);
                return '' === $value ? '' : esc_url_raw($value, ['http', 'https']);
            case 'enum':
                return trim($value);
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * A block's editable fields with their current values; null when it has
     * none.
     *
     * @return array<string, string>|null
     */
    public static function of(array $block): ?array
    {
        $specs = self::specs();
        $name  = (string) ($block['blockName'] ?? '');
        if (empty($specs[$name]) || !is_array($specs[$name])) {
            return null;
        }
        $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
        return HubFields::read($attrs, $specs[$name], self::defaults($name, $specs[$name]));
    }

    /**
     * Field edits applied to a block's attributes (Core\HubFields::apply()),
     * with WordPress's sanitizers and the block's defaults.
     *
     * @return array{ok: true, attrs: array, changed: list<string>}|array{ok: false, error: string, field: string}
     */
    public static function apply(string $block_name, array $attrs, array $changes, array $spec): array
    {
        return HubFields::apply($attrs, $changes, $spec, self::defaults($block_name, $spec), [self::class, 'clean']);
    }

    /** What went wrong with a field, in the words the Hub shows. */
    public static function error(array $applied, array $spec): \WP_Error
    {
        $field = (string) $applied['field'];
        $max   = isset($spec[$field]['max']) ? (int) $spec[$field]['max'] : 0;
        switch ($applied['error']) {
            case 'not_editable':
                $message = "Il campo {$field} non si può modificare dalla Vetrina.";
                break;
            case 'bad_url':
                $message = 'Il link non è valido: usa un indirizzo http(s) o un percorso del sito (/…).';
                break;
            case 'too_long':
                $message = "Il testo di {$field} è troppo lungo (al massimo {$max} caratteri).";
                break;
            default:
                $message = "Valore non valido per {$field}.";
        }
        return new \WP_Error('ghb_bad_field', $message, ['status' => 400, 'field' => $field, 'reason' => $applied['error']]);
    }
}
