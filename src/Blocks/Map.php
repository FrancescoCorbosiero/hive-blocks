<?php
/**
 * The map of the Contatti, Vieni in negozio and Mappa blocks, loaded only
 * when the visitor asks: a cover with the address, "Mostra la mappa" and
 * "Apri in Google Maps", and Google's iframe waiting in a <template> until
 * @hive/map puts it in. Until then the page loads nothing from Google and
 * Google sets no cookie. Styled by assets/map.css (handle hive-map).
 *
 * @package Hive
 */

namespace Hive\Blocks;

use Hive\Core\MapEmbed;

defined('ABSPATH') || exit;

final class Map
{
    private const PIN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';

    /**
     * The map's markup, escaped; '' when the code holds no Google Maps embed
     * (Core\MapEmbed).
     *
     * @param string $code The embed code pasted in the block, or its URL.
     * @param array  $args {
     *     @type string $address Shown on the cover; "Apri in Google Maps" searches for it.
     *     @type string $open    Where "Apri in Google Maps" goes instead (the block's own link).
     *     @type int    $image   A picture of the map behind the cover (an attachment ID).
     * }
     */
    public static function facade(string $code, array $args = []): string
    {
        $src = MapEmbed::src($code);
        if (null === $src) {
            return '';
        }
        $address = trim((string) ($args['address'] ?? ''));
        $open    = trim((string) ($args['open'] ?? ''));
        if ('' === $open) {
            // Without an address, the embed itself opens full page.
            $open = '' !== $address ? MapEmbed::search_url($address) : $src;
        }
        $image   = (int) ($args['image'] ?? 0);
        $picture = $image > 0
            ? wp_get_attachment_image(
                $image,
                'large',
                false,
                [
                    'class'    => 'hive-map__picture',
                    'alt'      => '',
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                    'sizes'    => '(min-width: 900px) 50vw, 100vw',
                ]
            )
            : '';

        return sprintf(
            '<div class="hive-map" data-hive-map>'
                . '<template data-hive-map-frame><iframe class="hive-map__frame" src="%1$s" title="%2$s" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe></template>'
                . '%3$s'
                . '<div class="hive-map__cover" data-hive-map-cover><div class="hive-map__card">'
                . '<span class="hive-map__pin">%4$s</span>'
                . '%5$s'
                . '<button type="button" class="hive-button hive-map__load" data-hive-map-load>Mostra la mappa</button>'
                . '<a class="hive-map__open" href="%6$s" target="_blank" rel="noopener noreferrer">Apri in Google Maps</a>'
                . '<p class="hive-map__note">La mappa è di Google: caricandola accetti i suoi cookie.</p>'
                . '</div></div>'
                . '</div>',
            esc_url($src),
            esc_attr('' !== $address ? 'Mappa: ' . $address : 'Mappa'),
            '' !== $picture ? '<div class="hive-map__image" data-hive-map-cover>' . $picture . '</div>' : '',
            self::PIN,
            '' !== $address ? '<p class="hive-map__address">' . esc_html($address) . '</p>' : '',
            esc_url($open)
        );
    }
}
