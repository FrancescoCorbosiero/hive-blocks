<?php
/**
 * The migration's rules for golden-hive-blocks' heroes that need more than
 * a rename (Hive\Migration's map holds the rest).
 *
 * Pure logic: no WordPress calls. Tested in tests/php/MigrateBlocksTest.php.
 *
 * @package Hive
 */

namespace Hive\Core\Migrate;

defined('ABSPATH') || exit;

final class Heroes
{
    /**
     * golden-hive/hero-video into hive/hero's cover: a video only when the
     * old block was set to one (mediaType), and then its poster, when it had
     * one, is the picture.
     *
     * @param array<string, mixed> $new The mapped attributes.
     * @param array<string, mixed> $old The old ones, defaults filled in.
     * @return array<string, mixed>
     */
    public static function video(array $new, array $old): array
    {
        if ('video' !== ($old['mediaType'] ?? 'image') || '' === (string) ($old['videoUrl'] ?? '')) {
            unset($new['videoUrl']);
            return $new;
        }
        if ('' !== (string) ($old['posterUrl'] ?? '') || !empty($old['posterUrlId'])) {
            $new['imageUrl'] = (string) ($old['posterUrl'] ?? '');
            $new['imageId']  = (int) ($old['posterUrlId'] ?? 0);
        }
        return $new;
    }
}
