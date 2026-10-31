<?php
/**
 * Images for the content blocks: from the media library through
 * wp_get_attachment_image() (srcset, sizes, width and height, the library's
 * alt text), or, for an image known only by its URL (content written for
 * golden-hive-blocks before it kept ids), a plain <img> with the same
 * loading attributes.
 *
 * @package Hive
 */

namespace Hive\Content;

defined('ABSPATH') || exit;

final class Media
{
    /** The attributes a URL-only <img> keeps (sizes means nothing without a srcset). */
    private const PLAIN = ['class', 'alt', 'loading', 'decoding', 'fetchpriority', 'style'];

    /**
     * @param int    $id         Attachment id (0 for none).
     * @param string $url        The image's URL, used when there's no id or it isn't an image.
     * @param array  $attributes <img> attributes: class, loading, decoding, fetchpriority, sizes,
     *                           style; alt only to override the library's (e.g. '' for a backdrop).
     * @return string The <img>, escaped; '' when there's nothing to show.
     */
    public static function image(int $id, string $url, array $attributes): string
    {
        if ($id > 0 && wp_attachment_is_image($id)) {
            $html = wp_get_attachment_image($id, 'full', false, $attributes);
            if ('' !== $html) {
                return $html;
            }
        }
        if ('' === $url) {
            return '';
        }

        $html = '<img src="' . esc_url($url) . '"';
        foreach (self::PLAIN as $name) {
            $value = $attributes[$name] ?? null;
            if ('alt' === $name || (is_string($value) && '' !== $value)) {
                $html .= ' ' . $name . '="' . esc_attr((string) $value) . '"';
            }
        }
        return $html . '>';
    }
}
