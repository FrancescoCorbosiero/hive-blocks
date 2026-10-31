<?php
/**
 * Hive → Velocità: what the browser fetches before it's asked.
 *
 *   WordPress's own speculative loading (6.8+), tuned: prefetch or
 *   prerender a link on hover (eagerness "moderate") instead of on click,
 *   or switched off
 *   files the first paint needs, preloaded (on the homepage only, or
 *   everywhere): the hero image, a font
 *   pages to prerender or prefetch as soon as any page loads (a drop
 *   page on release day, say)
 *
 * All of it is the same for every visitor, so cached pages carry it.
 *
 * @package Hive
 */

namespace Hive;

use Hive\Core\Preload;

defined('ABSPATH') || exit;

final class Speed
{
    public static function register(): void
    {
        add_filter('wp_speculation_rules_configuration', [self::class, 'speculation']);
        add_action('wp_head', [self::class, 'head'], 2);
    }

    /** WordPress's speculative loading, as set in Hive → Velocità. */
    public static function speculation(mixed $config): mixed
    {
        switch (Settings::get('speculation')) {
            case 'off':
                return null;
            case 'prefetch':
                return ['mode' => 'prefetch', 'eagerness' => 'moderate'];
            case 'prerender':
                return ['mode' => 'prerender', 'eagerness' => 'moderate'];
        }
        return $config;
    }

    /** Preloads and the listed pages' speculation rules, early in <head>. */
    public static function head(): void
    {
        if (is_admin() || is_feed()) {
            return;
        }
        $home = is_front_page() || is_home();
        if (!Settings::get('preload_home') || $home) {
            foreach (Preload::lines((string) Settings::get('preload')) as $line) {
                [$url, $type] = Preload::entry($line);
                if (null === $type) {
                    continue;
                }
                $html = '<link';
                foreach (Preload::attributes($url, $type) as $name => $value) {
                    $html .= 'href' === $name ? sprintf(' href="%s"', esc_url($value)) : ('' === $value ? ' ' . esc_attr($name) : sprintf(' %s="%s"', esc_attr($name), esc_attr($value)));
                }
                echo $html . ">\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every attribute escaped above.
            }
        }
        $urls  = static fn(string $key): array => array_values(array_filter(array_map(static fn(string $line): string => esc_url_raw(Preload::entry($line)[0]), Preload::lines((string) Settings::get($key)))));
        $rules = Preload::rules($urls('prerender'), $urls('prefetch'));
        if ($rules) {
            echo '<script type="speculationrules">' . wp_json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with <, > escaped.
        }
    }
}
