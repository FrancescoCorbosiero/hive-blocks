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
     *   flags   [key => bool], e.g. which modules are on
     */
    public const FIELDS = [
        'accent'  => ['type' => 'color', 'default' => '#721124'],
        'shape'   => ['type' => 'choice', 'default' => 'round', 'choices' => ['round', 'sharp']],
        'modules' => ['type' => 'flags', 'default' => []],
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
