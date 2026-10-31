<?php
/**
 * The plugin's settings as data: each one's type, default and allowed
 * values, and the sanitizing that keeps the stored option valid. Pure: no
 * WordPress, tested by tests/php/SettingsSchemaTest.php. The admin screen
 * (Hive\Settings) adds the labels.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class SettingsSchema
{
    /**
     * Every setting.
     *
     *   color   '#rrggbb'
     *   choice  one of 'choices'
     *   key     a taxonomy or attribute name: a-z, 0-9, _ and -, up to 32
     *   phone   digits only, 6 to 15 (international format without +), or ''
     *   bool    on or off
     *   int     a whole number between 'min' and 'max'
     *   time    'HH:MM', 24 hours
     *   flags   [key => bool], e.g. which modules are on
     */
    public const FIELDS = [
        'accent'         => ['type' => 'color', 'default' => '#721124'],
        'button'         => ['type' => 'color', 'default' => '#0a0a0a'],
        'shape'          => ['type' => 'choice', 'default' => 'round', 'choices' => ['round', 'sharp']],
        'size_attribute' => ['type' => 'key', 'default' => 'pa_taglia'],
        'whatsapp'       => ['type' => 'phone', 'default' => ''],
        'sold_out_last'  => ['type' => 'bool', 'default' => true],
        'filters_layout' => ['type' => 'choice', 'default' => 'sidebar', 'choices' => ['sidebar', 'drawer']],
        'gallery'        => ['type' => 'choice', 'default' => 'slider', 'choices' => ['slider', 'grid']],
        'sticky_bar'     => ['type' => 'bool', 'default' => true],
        'stock_low'      => ['type' => 'int', 'default' => 5, 'min' => 0, 'max' => 50],
        'sold_count'     => ['type' => 'bool', 'default' => true],
        'delivery'       => ['type' => 'bool', 'default' => false],
        'delivery_cutoff' => ['type' => 'time', 'default' => '13:00'],
        'delivery_min'   => ['type' => 'int', 'default' => 2, 'min' => 0, 'max' => 30],
        'delivery_max'   => ['type' => 'int', 'default' => 5, 'min' => 0, 'max' => 30],
        'band'           => ['type' => 'bool', 'default' => true],
        'colorways'      => ['type' => 'bool', 'default' => true],
        'recent'         => ['type' => 'bool', 'default' => true],
        'modules'        => ['type' => 'flags', 'default' => []],
    ];

    /** @return array<string, mixed> */
    public static function defaults(array $fields = self::FIELDS): array
    {
        return array_map(static fn(array $field) => $field['default'], $fields);
    }

    /**
     * Any input (the stored option, a form post) → every setting, valid.
     * Unknown keys are dropped and invalid values fall back to the default.
     *
     * @return array<string, mixed>
     */
    public static function sanitize(mixed $input, array $fields = self::FIELDS): array
    {
        $input = is_array($input) ? $input : [];
        $clean = [];
        foreach ($fields as $key => $field) {
            $clean[$key] = self::value($input[$key] ?? null, $field);
        }
        return $clean;
    }

    private static function value(mixed $value, array $field): mixed
    {
        switch ($field['type']) {
            case 'color':
                return Color::hex(is_string($value) ? $value : '') ?? $field['default'];

            case 'choice':
                return is_string($value) && in_array($value, $field['choices'], true) ? $value : $field['default'];

            case 'key':
                return is_string($value) && 1 === preg_match('/^[a-z0-9_-]{1,32}$/', $value) ? $value : $field['default'];

            case 'phone':
                $digits = is_string($value) ? (string) preg_replace('/\D/', '', $value) : '';
                return 1 === preg_match('/^\d{6,15}$/', $digits) ? $digits : $field['default'];

            case 'int':
                if (is_int($value) || (is_string($value) && 1 === preg_match('/^\d{1,6}$/', trim($value)))) {
                    return max($field['min'], min($field['max'], (int) $value));
                }
                return $field['default'];

            case 'time':
                return is_string($value) && 1 === preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', trim($value)) ? trim($value) : $field['default'];

            case 'bool':
                if (null === $value) {
                    return $field['default'];
                }
                return true === $value || in_array($value, ['1', 1, 'true', 'yes', 'on'], true);

            case 'flags':
                $flags = [];
                foreach (is_array($value) ? $value : [] as $key => $on) {
                    if (is_string($key) && 1 === preg_match('/^[a-z0-9-]+$/', $key)) {
                        $flags[$key] = (bool) $on;
                    }
                }
                return $flags;
        }
        return $field['default'];
    }
}
