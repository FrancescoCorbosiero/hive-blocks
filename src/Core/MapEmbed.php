<?php
/**
 * A map pasted in a block (Contatti, Vieni in negozio, Mappa): the code
 * Google Maps gives under "Condividi → Incorpora una mappa", or just its
 * URL. Only Google Maps' own embed addresses pass, so a block can't frame
 * any other site; the iframe is Hive's own (Blocks\Map), nothing pasted is
 * printed. Also the links that open Google Maps on an address.
 * Pure: tested by tests/php/MapEmbedTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class MapEmbed
{
    /** google.com, google.it, google.co.uk…, with www. or maps. in front, or neither. */
    private const HOST = '/^(?:www\.|maps\.)?google\.(?:com|[a-z]{2}|com?\.[a-z]{2})$/';

    /**
     * The embed URL in a pasted code: the first <iframe>'s src, or the code
     * itself when it's a URL. Google Maps embeds only (/maps/embed…, or
     * /maps?…&output=embed), always as https; null for anything else.
     */
    public static function src(string $code): ?string
    {
        $code = trim($code);
        if ('' === $code) {
            return null;
        }
        if (false !== stripos($code, '<iframe')) {
            if (1 !== preg_match('/<iframe\b[^>]*?\ssrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $code, $match)) {
                return null;
            }
            $code = $match[1] . ($match[2] ?? '') . ($match[3] ?? '');
        }

        $url = trim(html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }
        if ('' === $url || 1 === preg_match('/[\s"\'<>\\\\`]/', $url)) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host   = strtolower((string) ($parts['host'] ?? ''));
        $path   = (string) ($parts['path'] ?? '');
        $query  = (string) ($parts['query'] ?? '');
        if (!in_array($scheme, ['https', 'http'], true) || 1 !== preg_match(self::HOST, $host)) {
            return null;
        }
        $embed = str_starts_with($path, '/maps/embed')
            || ('/maps' === rtrim($path, '/') && 1 === preg_match('/(?:^|&)output=embed(?:&|$)/', $query));
        if (!$embed) {
            return null;
        }
        return 'https://' . $host . $path . ('' === $query ? '' : '?' . $query);
    }

    /** Google Maps searching for an address ("Apri in Google Maps"). */
    public static function search_url(string $address): string
    {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(trim($address));
    }

    /** Google Maps with the way to an address ("Indicazioni"). */
    public static function directions_url(string $address): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode(trim($address));
    }
}
