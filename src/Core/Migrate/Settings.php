<?php
/**
 * The migration's settings: golden-hive-blocks' options (Customizer and
 * Tools → GH Preload) as Hive's (Hive → Impostazioni). Only what the store
 * set is carried; the rest keeps Hive's defaults.
 *
 * Pure logic: no WordPress calls. Tested in tests/php/MigrateSettingsTest.php.
 *
 * @package Hive
 */

namespace Hive\Core\Migrate;

defined('ABSPATH') || exit;

final class Settings
{
    /** Old option => [Hive setting, kind]. */
    public const OPTIONS = [
        'ghb_whatsapp_number'   => ['whatsapp', 'text'],
        'ghb_design_shape'      => ['shape', 'text'],
        'ghb_filters_layout'    => ['filters_layout', 'text'],
        'ghb_sold_out_last'     => ['sold_out_last', 'yesno'],
        'ghb_pp_sold_count'     => ['sold_count', 'yesno'],
        'ghb_pp_band'           => ['band', 'yesno'],
        'ghb_pp_colorways'      => ['colorways', 'yesno'],
        'ghb_pp_recent'         => ['recent', 'yesno'],
        'ghb_delivery_estimate' => ['delivery', 'yesno'],
        'ghb_delivery_cutoff'   => ['delivery_cutoff', 'text'],
        'ghb_delivery_days_min' => ['delivery_min', 'int'],
        'ghb_delivery_days_max' => ['delivery_max', 'int'],
    ];

    /**
     * @param array<string, mixed> $old     Old option => its stored value (missing: not set).
     * @param array<string, mixed> $preload golden-hive-blocks' ghb_preload_settings, if set.
     * @return array<string, mixed> Hive settings to merge in (sanitized later by SettingsSchema).
     */
    public static function from(array $old, array $preload = []): array
    {
        $out = [];
        foreach (self::OPTIONS as $option => [$key, $kind]) {
            if (!array_key_exists($option, $old) || null === $old[$option] || false === $old[$option]) {
                continue;
            }
            $value = $old[$option];
            $out[$key] = match ($kind) {
                'yesno' => !in_array($value, ['no', '0', 0, false, ''], true),
                'int'   => is_numeric($value) ? (int) $value : null,
                default => is_scalar($value) ? (string) $value : null,
            };
            if (null === $out[$key]) {
                unset($out[$key]);
            }
        }
        if ($preload) {
            foreach (['preload_urls' => 'preload', 'prerender_urls' => 'prerender', 'prefetch_urls' => 'prefetch'] as $from => $to) {
                if (isset($preload[$from]) && is_string($preload[$from]) && '' !== trim($preload[$from])) {
                    $out[$to] = trim($preload[$from]);
                }
            }
            if (isset($preload['preload_homepage_only'])) {
                $out['preload_home'] = (bool) $preload['preload_homepage_only'];
            }
        }
        return $out;
    }
}
