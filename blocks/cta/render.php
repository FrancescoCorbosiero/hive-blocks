<?php
/**
 * Invito all'azione — server render. Two layouts, offered as block
 * variations, both on a dark surface:
 *
 *   banner  Banner: full width, centred, an optional picture behind a
 *           gradient towards the accent colour, a thin accent glow on top.
 *   split   Immagine e testo: the picture on one side (the other with
 *           "reverse"), fading into the text's side.
 *
 * Calls to action sit lower on the page, so the picture loads lazily.
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Content\Media;
use Hive\Core\Hero;

defined('ABSPATH') || exit;

$cta = Hero::cta($attributes);
if ('' === $cta['title']) {
    return;
}

$banner = 'banner' === $cta['layout'];
$image  = Media::image(
    $cta['image']['id'],
    $cta['image']['url'],
    [
        'class'    => 'hive-cta__image',
        'loading'  => 'lazy',
        'decoding' => 'async',
        'sizes'    => $banner ? '100vw' : '(min-width: 768px) 50vw, 100vw',
    ] + ($banner ? ['alt' => ''] : [])
);

$classes = 'hive-block hive-cta hive-cta--' . $cta['layout'];
if ('' !== $image) {
    $classes .= ' has-image';
}
if ($cta['reverse']) {
    $classes .= ' hive-cta--reverse';
}
?>
<section <?php echo get_block_wrapper_attributes(['class' => $classes]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <?php if ('' !== $image) : ?>
        <div class="hive-cta__media"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media::image() escapes. ?></div>
    <?php endif; ?>
    <?php if ($cta['glow']) : ?>
        <span class="hive-cta__glow" aria-hidden="true"></span>
    <?php endif; ?>
    <div class="hive-cta__body">
        <?php if ('' !== $cta['eyebrow']) : ?>
            <p class="hive-cta__eyebrow"><?php echo esc_html($cta['eyebrow']); ?></p>
        <?php endif; ?>
        <h2 class="hive-cta__title"><?php echo esc_html($cta['title']); ?></h2>
        <?php if ('' !== $cta['text']) : ?>
            <p class="hive-cta__text"><?php echo esc_html($cta['text']); ?></p>
        <?php endif; ?>
        <?php if ($cta['button']) : ?>
            <p class="hive-cta__actions"><a class="hive-button hive-cta__button" href="<?php echo esc_url($cta['button']['url']); ?>"><?php echo esc_html($cta['button']['text']); ?></a></p>
        <?php endif; ?>
    </div>
</section>
