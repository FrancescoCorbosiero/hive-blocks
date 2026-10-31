<?php
/**
 * The product page's photos (in place of WooCommerce's gallery and
 * CommerceKit's Product Gallery):
 *
 *   a row of photos that scrolls sideways with CSS scroll snapping: swiping
 *   needs no script; on desktop, one photo at a time with thumbnails and
 *   arrows, or every photo in a two-column grid (Hive → Pagina prodotto)
 *   the first photo loads at once with high priority (it is the page's
 *   largest paint), the others lazily
 *   each photo links to its full size, so it opens without JavaScript too;
 *   @hive/gallery opens it in a zoomable full-screen view instead
 *   the badges the cards show ("-20%", "Esaurito")
 *
 * A size with its own photo shows it when picked (@hive/product).
 *
 * @package Hive
 */

namespace Hive\Shop;

defined('ABSPATH') || exit;

final class Gallery
{
    private const ARROW_PREV = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m14.5 6-6 6 6 6"/></svg>';
    private const ARROW_NEXT = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9.5 6 6 6-6 6"/></svg>';

    /**
     * @param string               $layout slider | grid (desktop)
     * @param array<string,string> $badges [class => text], Card::badge_list()
     */
    public static function render(\WC_Product $product, string $layout, array $badges = []): string
    {
        $ids   = self::image_ids($product);
        $name  = wp_strip_all_tags($product->get_name());
        $count = count($ids);
        $grid  = 'grid' === $layout;
        $sizes = $grid ? '(min-width: 1025px) 30vw, (min-width: 768px) 50vw, 100vw' : '(min-width: 1025px) 55vw, (min-width: 768px) 50vw, 100vw';

        $html  = sprintf('<div class="hive-gallery hive-gallery--%s" data-hive-gallery data-count="%d">', $grid ? 'grid' : 'slider', max(1, $count));
        $html .= '<div class="hive-gallery__stage">';
        if ($badges) {
            $html .= '<p class="hive-gallery__badges">';
            foreach ($badges as $class => $text) {
                $html .= sprintf('<span class="hive-gallery__badge %s">%s</span>', esc_attr((string) $class), esc_html((string) $text));
            }
            $html .= '</p>';
        }
        $html .= sprintf('<ul class="hive-gallery__track" role="list" tabindex="0" aria-label="%s" data-hive-gallery-track>', esc_attr('Foto di ' . $name));

        if (!$ids) {
            $html .= '<li class="hive-gallery__slide">' . wc_placeholder_img('woocommerce_single', ['class' => 'hive-gallery__image', 'fetchpriority' => 'high', 'loading' => 'eager']) . '</li>';
        }
        foreach ($ids as $index => $id) {
            $full = wp_get_attachment_image_src($id, 'full');
            $alt  = trim((string) get_post_meta($id, '_wp_attachment_image_alt', true));
            $attributes = [
                'class'    => 'hive-gallery__image',
                'alt'      => '' !== $alt ? $alt : ($count > 1 ? sprintf('%s, foto %d di %d', $name, $index + 1, $count) : $name),
                'sizes'    => $sizes,
                'loading'  => 'lazy',
                'decoding' => 'async',
            ];
            if (0 === $index) {
                // The first photo is the page's largest paint: no lazy loading, high priority.
                $attributes['loading']       = 'eager';
                $attributes['fetchpriority'] = 'high';
            }
            $img = wp_get_attachment_image($id, 'woocommerce_single', false, $attributes);
            if ('' === $img) {
                continue;
            }
            $html .= sprintf('<li class="hive-gallery__slide" data-image-id="%d">', $id);
            if (is_array($full)) {
                $html .= sprintf(
                    '<a class="hive-gallery__zoom" href="%s" data-width="%d" data-height="%d" aria-label="%s">%s</a>',
                    esc_url($full[0]),
                    (int) $full[1],
                    (int) $full[2],
                    esc_attr($count > 1 ? sprintf('Ingrandisci la foto %d di %d', $index + 1, $count) : 'Ingrandisci la foto'),
                    $img
                );
            } else {
                $html .= $img;
            }
            $html .= '</li>';
        }
        $html .= '</ul>';

        if ($count > 1) {
            $html .= '<button type="button" class="hive-gallery__arrow is-prev" data-hive-gallery-step="-1" aria-label="Foto precedente" hidden>' . self::ARROW_PREV . '</button>';
            $html .= '<button type="button" class="hive-gallery__arrow is-next" data-hive-gallery-step="1" aria-label="Foto successiva" hidden>' . self::ARROW_NEXT . '</button>';
            $html .= sprintf('<p class="hive-gallery__count" aria-hidden="true"><span data-hive-gallery-index>1</span> / %d</p>', $count);
        }
        $html .= '</div>';

        if ($count > 1 && !$grid) {
            $html .= '<div class="hive-gallery__thumbs" role="group" aria-label="Miniature">';
            foreach ($ids as $index => $id) {
                $html .= sprintf(
                    '<button type="button" class="hive-gallery__thumb" data-hive-gallery-go="%d" aria-label="%s"%s>%s</button>',
                    $index,
                    esc_attr(sprintf('Foto %d di %d', $index + 1, $count)),
                    0 === $index ? ' aria-current="true"' : '',
                    wp_get_attachment_image($id, 'woocommerce_gallery_thumbnail', false, ['class' => 'hive-gallery__thumb-image', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async'])
                );
            }
            $html .= '</div>';
        }
        return $html . '</div>';
    }

    /**
     * The main photo, then the gallery's, each once.
     *
     * @return list<int>
     */
    public static function image_ids(\WC_Product $product): array
    {
        $ids = array_merge([(int) $product->get_image_id()], array_map('intval', $product->get_gallery_image_ids()));
        return array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0 && wp_attachment_is_image($id))));
    }
}
