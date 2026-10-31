<?php
/**
 * Instagram — server render: a grid of the store's photos, each a link to
 * its Instagram post (or to the profile), and the button to follow the
 * store. No Instagram script and no embed: the photos are the media
 * library's, through wp_get_attachment_image(), lazy. A cell without a
 * photo keeps its place with the Instagram icon, as in the old block.
 *
 * "Acquista il look": a photo can tag the products it shows (hotspots:
 * productId, x and y in % of the photo). A tag names the product and its
 * price, and opens it in Quick View (@hive/cards, with the module on) or
 * goes to its page. A photo with tags isn't one big link any more: its post
 * stays one tap away from the icon in the corner.
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Assets;
use Hive\Modules;

defined('ABSPATH') || exit;

$heading  = trim((string) ($attributes['title'] ?? ''));
$subtitle = trim((string) ($attributes['subtitle'] ?? ''));
$profile  = trim((string) ($attributes['instagramUrl'] ?? ''));
$follow   = trim((string) ($attributes['buttonText'] ?? ''));
$columns  = max(2, min(6, (int) ($attributes['columns'] ?? 3)));
$images   = array_values(array_filter((array) ($attributes['images'] ?? []), 'is_array'));
$peek     = Modules::enabled('quick-view');

// The Instagram glyph, drawn with strokes (style.css): short enough to repeat in every cell.
$glyph       = '<svg class="hive-instagram-feed__glyph" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><path d="M17.5 6.5h.01"/></svg>';
$placeholder = '<span class="hive-instagram-feed__placeholder">' . $glyph . '</span>';

/** The photo, from the media library (or, for an old block that kept only its address, as it is). */
$photo = static function (array $image) use ($columns): string {
    $id  = (int) ($image['urlId'] ?? 0);
    $url = trim((string) ($image['url'] ?? ''));
    $alt = trim((string) ($image['alt'] ?? ''));
    if ($id <= 0 && '' !== $url) {
        $id = attachment_url_to_postid($url);
    }
    if ($id > 0 && wp_attachment_is_image($id)) {
        $attrs = [
            'class'    => 'hive-instagram-feed__image',
            'loading'  => 'lazy',
            'decoding' => 'async',
            'sizes'    => sprintf('(max-width: 767px) 50vw, %dvw', (int) ceil(100 / $columns)),
        ];
        // Without its own text the photo keeps the media library's.
        if ('' !== $alt) {
            $attrs['alt'] = $alt;
        }
        return (string) wp_get_attachment_image($id, 'large', false, $attrs);
    }
    return '' === $url ? '' : sprintf('<img class="hive-instagram-feed__image" src="%s" alt="%s" loading="lazy" decoding="async">', esc_url($url), esc_attr($alt));
};

/** What a tag says about its product's price: "150,00 €", "da 150,00 €" for a range of sizes. */
$price = static function (WC_Product $product): string {
    if ('' === (string) $product->get_price()) {
        return '';
    }
    $range  = $product->is_type('variable') && $product->get_variation_price('min', true) !== $product->get_variation_price('max', true);
    $amount = $product->is_type('variable') ? (float) $product->get_variation_price('min', true) : (float) wc_get_price_to_display($product);
    return ($range ? 'da ' : '') . trim(html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES, 'UTF-8'));
};

/** The published products tagged on a photo, each where it shows (in %). */
$tags = static function (array $image): array {
    $found = [];
    foreach ((array) ($image['hotspots'] ?? []) as $spot) {
        $id      = is_array($spot) ? (int) ($spot['productId'] ?? 0) : 0;
        $product = $id > 0 && function_exists('wc_get_product') ? wc_get_product($id) : null;
        if (!$product instanceof WC_Product || 'publish' !== $product->get_status()) {
            continue;
        }
        $found[] = [
            'product' => $product,
            'x'       => round(max(0.0, min(100.0, (float) ($spot['x'] ?? 50))), 2),
            'y'       => round(max(0.0, min(100.0, (float) ($spot['y'] ?? 50))), 2),
        ];
    }
    return $found;
};
?>
<section <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-instagram-feed']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <?php if ('' !== $heading || '' !== $subtitle) : ?>
        <div class="hive-instagram-feed__header">
            <?php if ('' !== $heading) : ?>
                <h2 class="hive-instagram-feed__title"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <?php if ('' !== $subtitle) : ?>
                <p class="hive-instagram-feed__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($images) : ?>
        <ul class="hive-instagram-feed__grid" style="--hive-columns:<?php echo (int) $columns; ?>" role="list">
            <?php
            foreach ($images as $image) :
                $post_link = trim((string) ($image['link'] ?? ''));
                $post_link = '' !== esc_url($post_link) ? $post_link : $profile;
                $picture   = $photo($image);
                $spots     = '' !== $picture ? $tags($image) : [];
                ?>
                <li class="hive-instagram-feed__item">
                    <?php
                    if ($spots) :
                        if ($peek) {
                            Assets::use_module('@hive/cards');
                        }
                        ?>
                        <div class="hive-instagram-feed__frame hive-instagram-feed__frame--shop">
                            <?php echo $picture; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(), or escaped above. ?>
                            <span class="hive-instagram-feed__badge" aria-hidden="true"><svg class="hive-instagram-feed__bag" viewBox="0 0 24 24" focusable="false"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0"/></svg>Acquista il look</span>
                            <?php
                            foreach ($spots as $spot) :
                                $tagged  = $spot['product'];
                                $name    = wp_strip_all_tags($tagged->get_name());
                                $amount  = $price($tagged);
                                $classes = 'hive-instagram-feed__spot'
                                    . ($spot['y'] < 35 ? ' hive-instagram-feed__spot--below' : '')
                                    . ($spot['x'] > 65 ? ' hive-instagram-feed__spot--left' : ($spot['x'] < 35 ? ' hive-instagram-feed__spot--right' : ''));
                                ?>
                                <a class="<?php echo esc_attr($classes); ?>" href="<?php echo esc_url($tagged->get_permalink()); ?>"<?php echo $peek ? ' data-hive-quick-view="' . (int) $tagged->get_id() . '"' : ''; ?> style="<?php echo esc_attr(sprintf('--hive-x:%s%%;--hive-y:%s%%', $spot['x'], $spot['y'])); ?>" aria-label="<?php echo esc_attr($name . ('' !== $amount ? ', ' . $amount : '')); ?>">
                                    <span class="hive-instagram-feed__dot" aria-hidden="true"></span>
                                    <span class="hive-instagram-feed__tag" aria-hidden="true">
                                        <strong class="hive-instagram-feed__tag-name"><?php echo esc_html($name); ?></strong>
                                        <?php if ('' !== $amount) : ?>
                                            <span class="hive-instagram-feed__tag-price"><?php echo esc_html($amount); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                            <?php if ('' !== esc_url($post_link)) : ?>
                                <a class="hive-instagram-feed__post" href="<?php echo esc_url($post_link); ?>" target="_blank" rel="noopener noreferrer" aria-label="Vedi il post su Instagram">
                                    <?php echo $glyph; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php elseif ('' !== esc_url($post_link)) : ?>
                        <a class="hive-instagram-feed__frame hive-instagram-feed__frame--link" href="<?php echo esc_url($post_link); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo '' !== $picture ? $picture : $placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(), escaped above, or fixed markup. ?>
                            <span class="hive-instagram-feed__overlay"><?php echo $glyph; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?><span class="hive-instagram-feed__overlay-text">Vedi su Instagram</span></span>
                        </a>
                    <?php else : ?>
                        <div class="hive-instagram-feed__frame">
                            <?php echo '' !== $picture ? $picture : $placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(), escaped above, or fixed markup. ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ('' !== esc_url($profile)) : ?>
        <p class="hive-instagram-feed__cta">
            <a class="hive-button hive-instagram-feed__button" href="<?php echo esc_url($profile); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo $glyph; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?>
                <?php echo esc_html('' === $follow ? 'Seguici su Instagram' : $follow); ?>
            </a>
        </p>
    <?php endif; ?>
</section>
