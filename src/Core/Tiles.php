<?php
/**
 * A slider's own tiles (Slider categorie with typed tiles: a brand, a
 * collection, a page), each a picture, a name and a link, cleaned. The
 * picture is a media library id or, for one known only by its URL, the URL
 * (Content\Media draws either).
 *
 * Pure logic: no WordPress calls. Tested in tests/php/TilesTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class Tiles
{
    public const MAX = 24;

    /**
     * @return list<array{name: string, url: string, image: string, imageId: int}>
     */
    public static function from(mixed $items): array
    {
        $out = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $tile = [
                'name'    => trim(strip_tags((string) ($item['name'] ?? ''))),
                'url'     => trim((string) ($item['url'] ?? '')),
                'image'   => trim((string) ($item['image'] ?? '')),
                'imageId' => max(0, (int) ($item['imageId'] ?? 0)),
            ];
            // A tile shows a name or a picture; one with neither is left out.
            if ('' === $tile['name'] && '' === $tile['image'] && 0 === $tile['imageId']) {
                continue;
            }
            $out[] = $tile;
            if (count($out) === self::MAX) {
                break;
            }
        }
        return $out;
    }
}
