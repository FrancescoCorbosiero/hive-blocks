<?php
/**
 * Hive → Velocità: what <head> asks the browser to fetch early.
 *
 *   preload    files the first paint needs (the homepage's hero, a font):
 *              one URL per line, "url|type" to force the type; images get
 *              fetchpriority="high"
 *   prerender  pages to render ahead (speculation rules)
 *   prefetch   pages to fetch ahead
 *
 * Pure logic: no WordPress calls. Tested in tests/php/PreloadTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class Preload
{
    private const TYPES = [
        'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image', 'avif' => 'image', 'svg' => 'image',
        'mp4' => 'video', 'webm' => 'video', 'mov' => 'video',
        'woff2' => 'font', 'woff' => 'font',
        'css' => 'style',
        'js' => 'script', 'mjs' => 'script',
    ];

    /** @return list<string> The non-empty lines, without "#" comments. */
    public static function lines(string $raw, int $max = 30): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ('' !== $line && !str_starts_with($line, '#')) {
                $out[] = $line;
            }
        }
        return array_slice($out, 0, $max);
    }

    /** "url|type" or a URL whose extension tells: [url, type|null]. */
    public static function entry(string $line): array
    {
        $type = null;
        $url  = $line;
        if (str_contains($line, '|')) {
            [$url, $type] = array_map('trim', explode('|', $line, 2));
            $type         = in_array($type, array_values(self::TYPES), true) ? $type : null;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return [$url, $type ?? (self::TYPES[$ext] ?? null)];
    }

    /**
     * The <link rel="preload"> attributes for a URL of a type (the URL as
     * given: the caller escapes it).
     *
     * @return array<string, string>
     */
    public static function attributes(string $url, string $type): array
    {
        $attributes = ['rel' => 'preload', 'as' => $type, 'href' => $url];
        $ext        = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        switch ($type) {
            case 'font':
                $attributes['type']        = 'woff' === $ext ? 'font/woff' : 'font/woff2';
                $attributes['crossorigin'] = '';
                break;
            case 'video':
                $attributes['type'] = 'webm' === $ext ? 'video/webm' : ('mov' === $ext ? 'video/quicktime' : 'video/mp4');
                break;
            case 'image':
                // Listed here means the first paint needs it.
                $attributes['fetchpriority'] = 'high';
                break;
        }
        return $attributes;
    }

    /**
     * The speculation rules for listed pages ([] without any).
     *
     * @param list<string> $prerender
     * @param list<string> $prefetch
     */
    public static function rules(array $prerender, array $prefetch): array
    {
        $rules = [];
        if ($prerender) {
            $rules['prerender'] = [['source' => 'list', 'urls' => array_values($prerender)]];
        }
        if ($prefetch) {
            $rules['prefetch'] = [['source' => 'list', 'urls' => array_values($prefetch)]];
        }
        return $rules;
    }
}
