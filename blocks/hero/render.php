<?php
/**
 * Hero — server render. Three layouts, offered as block variations:
 *
 *   cover  Copertina: an image, or a video with the image as its poster,
 *          behind the text, and an optional cut-out picture (a product) as
 *          a static layer between the two.
 *   split  Diviso: the image on one side, edge to edge; the text on the other.
 *   about  Chi siamo: the same two columns with the image framed, and the
 *          values list.
 *
 * The output is the same for every visitor. A video never loads with the
 * page: view.js, enqueued only for a hero with a video, starts it once the
 * page has loaded and the hero is in view, unless the visitor prefers
 * reduced motion or saves data; its button pauses it, or plays it on demand.
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Assets;
use Hive\Content\Media;
use Hive\Core\Hero;

defined('ABSPATH') || exit;

$hero     = Hero::hero($attributes);
$cover    = 'cover' === $hero['layout'];
$position = 'object-position:' . $hero['position'];
$priority = $hero['eager'] ? ['loading' => 'eager', 'fetchpriority' => 'high'] : ['loading' => 'lazy'];

// The background (alt left empty: the text says it all), or the side picture (the library's alt).
$image = Media::image(
    $hero['image']['id'],
    $hero['image']['url'],
    [
        'class'    => 'hive-hero__image',
        'decoding' => 'async',
        'sizes'    => $cover ? '100vw' : '(min-width: 768px) 50vw, 100vw',
        'style'    => $position,
    ] + $priority + ($cover ? ['alt' => ''] : [])
);
$video      = $hero['video'];
$foreground = Media::image(
    $hero['foreground']['id'],
    $hero['foreground']['url'],
    [
        'class'    => 'hive-hero__foreground',
        'decoding' => 'async',
        'loading'  => $hero['eager'] ? 'eager' : 'lazy',
        'sizes'    => '(min-width: 768px) 60vw, 90vw',
    ]
);
$has_media = '' !== $image || '' !== $video;

if ('' === $hero['title'] && !$has_media) {
    return;
}

if ('' !== $video) {
    $module = Assets::build_info(__DIR__ . '/build/view.js');
    wp_enqueue_script_module('hive-hero-view-script-module', plugins_url('build/view.js', __FILE__), $module['dependencies'], $module['version']);
}

$classes = sprintf('hive-block hive-hero hive-hero--%s hive-hero--h-%s', $hero['layout'], $hero['height']);
if (!$cover && 'right' === $hero['side']) {
    $classes .= ' hive-hero--media-right';
}
if ($hero['dark']) {
    $classes .= ' hive-hero--dark';
}
if ($has_media) {
    $classes .= ' has-media';
}
if ($hero['scroll']) {
    $classes .= ' has-scroll';
}
$wrapper = ['class' => $classes];
if ($cover && $has_media) {
    $wrapper['style'] = '--hive-hero-overlay:' . $hero['overlay'] . ';';
}
$heading = $hero['heading'];
$words   = $hero['accent'] ?? [$hero['title'], '', ''];
?>
<section <?php echo get_block_wrapper_attributes($wrapper); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <?php if ($has_media) : ?>
        <div class="hive-hero__media">
            <?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media::image() escapes. ?>
            <?php if ('' !== $video) : ?>
                <video class="hive-hero__video" data-hive-hero-video data-src="<?php echo esc_url($video); ?>" style="<?php echo esc_attr($position); ?>" muted loop playsinline preload="none" aria-hidden="true"></video>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($cover && '' !== $foreground) : ?>
        <div class="hive-hero__layer"><?php echo $foreground; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media::image() escapes. ?></div>
    <?php endif; ?>
    <div class="hive-hero__body">
        <div class="hive-hero__inner">
            <?php if ('' !== $hero['badge']) : ?>
                <p class="hive-hero__badge"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><?php echo esc_html($hero['badge']); ?></p>
            <?php endif; ?>
            <?php if ('' !== $hero['eyebrow']) : ?>
                <p class="hive-hero__eyebrow"><?php echo esc_html($hero['eyebrow']); ?></p>
            <?php endif; ?>
            <?php if ('' !== $hero['title']) : ?>
                <<?php echo tag_escape($heading); ?> class="hive-hero__title"><?php echo esc_html($words[0]); ?><?php if ('' !== $words[1]) : ?><span class="hive-hero__accent"><?php echo esc_html($words[1]); ?></span><?php echo esc_html($words[2]); ?><?php endif; ?></<?php echo tag_escape($heading); ?>>
            <?php endif; ?>
            <?php if ('' !== $hero['subtitle']) : ?>
                <p class="hive-hero__subtitle"><?php echo esc_html($hero['subtitle']); ?></p>
            <?php endif; ?>
            <?php if ($hero['values']) : ?>
                <ul class="hive-hero__values" role="list">
                    <?php foreach ($hero['values'] as $value) : ?>
                        <li class="hive-hero__value">
                            <span class="hive-hero__value-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M20 6 9 17l-5-5"/></svg></span>
                            <span class="hive-hero__value-body">
                                <strong class="hive-hero__value-title"><?php echo esc_html($value['title']); ?></strong>
                                <?php if ('' !== $value['text']) : ?>
                                    <span class="hive-hero__value-text"><?php echo esc_html($value['text']); ?></span>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($hero['buttons']) : ?>
                <div class="hive-hero__actions">
                    <?php foreach ($hero['buttons'] as $button) : ?>
                        <a class="hive-button hive-hero__button<?php echo $button['outline'] ? ' hive-hero__button--outline' : ''; ?>" href="<?php echo esc_url($button['url']); ?>"><?php echo esc_html($button['text']); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if ('' !== $video) : ?>
        <button type="button" class="hive-hero__toggle" data-hive-hero-toggle aria-label="Riproduci il video" hidden>
            <svg class="hive-hero__play" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z"/></svg>
            <svg class="hive-hero__pause" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z"/></svg>
        </button>
    <?php endif; ?>
    <?php if ($hero['scroll']) : ?>
        <div class="hive-hero__scroll" aria-hidden="true">
            <span>Scorri</span>
            <svg viewBox="0 0 24 24" focusable="false"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
        </div>
    <?php endif; ?>
</section>
