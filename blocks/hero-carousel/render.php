<?php
/**
 * Carosello — server render. The slides sit side by side in a track that
 * scrolls with CSS scroll snapping, so swiping works before any script;
 * view.js adds the arrows, the dots and autoplay. Only the first slide's
 * image loads with the page (eager, high priority); the others are lazy,
 * and view.js asks for the next one once the page has loaded.
 *
 * The controls come first in the markup, so the button that stops autoplay
 * is the first thing the keyboard reaches; they sit over the slides' foot.
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Content\Media;
use Hive\Core\Hero;

defined('ABSPATH') || exit;

$carousel = Hero::carousel($attributes);
$slides   = $carousel['slides'];
if (!$slides) {
    return;
}

$count    = count($slides);
$many     = $count > 1;
$autoplay = $many ? $carousel['autoplay'] : 0;
$prefix   = wp_unique_id('hive-carousel-');
$wrapper  = [
    'class'                => 'hive-block hive-hero-carousel' . ($carousel['centered'] ? ' hive-hero-carousel--centered' : ''),
    'aria-roledescription' => 'carosello',
    'aria-label'           => 'In evidenza',
];
if ($many) {
    $wrapper['data-hive-carousel'] = (string) $autoplay;
}
$controls = $many && ($carousel['dots'] || $carousel['arrows'] || $autoplay > 0);
?>
<section <?php echo get_block_wrapper_attributes($wrapper); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <?php if ($controls) : ?>
        <div class="hive-hero-carousel__controls" data-hive-carousel-controls hidden>
            <?php if ($autoplay > 0) : ?>
                <button type="button" class="hive-hero-carousel__toggle is-on" data-hive-carousel-toggle aria-label="Ferma lo scorrimento automatico" hidden>
                    <svg class="hive-hero-carousel__pause" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z"/></svg>
                    <svg class="hive-hero-carousel__play" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z"/></svg>
                </button>
            <?php endif; ?>
            <?php if ($carousel['dots']) : ?>
                <div class="hive-hero-carousel__dots">
                    <?php foreach (array_keys($slides) as $index) : ?>
                        <button type="button" class="hive-hero-carousel__dot" data-hive-carousel-go="<?php echo (int) $index; ?>" aria-controls="<?php echo esc_attr($prefix . '-' . ($index + 1)); ?>" aria-label="<?php echo esc_attr(sprintf('Vai alla slide %d', $index + 1)); ?>"<?php echo 0 === $index ? ' aria-current="true"' : ''; ?>></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($carousel['arrows']) : ?>
                <div class="hive-hero-carousel__arrows">
                    <button type="button" class="hive-hero-carousel__arrow" data-hive-carousel-step="-1" aria-label="Slide precedente"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m14.5 6-6 6 6 6"/></svg></button>
                    <button type="button" class="hive-hero-carousel__arrow" data-hive-carousel-step="1" aria-label="Slide successiva"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9.5 6 6 6-6 6"/></svg></button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="hive-hero-carousel__track" data-hive-carousel-track>
        <?php
        foreach ($slides as $index => $slide) :
            // Behind a title the picture is a backdrop (empty alt); alone, it keeps the library's alt.
            $image = Media::image(
                $slide['image']['id'],
                $slide['image']['url'],
                [
                    'class'    => 'hive-hero-carousel__image',
                    'decoding' => 'async',
                    'sizes'    => '100vw',
                    'style'    => 'object-position:' . $slide['position'],
                ]
                + (0 === $index ? ['loading' => 'eager', 'fetchpriority' => 'high'] : ['loading' => 'lazy'])
                + ('' !== $slide['title'] ? ['alt' => ''] : [])
            );
            $texts = '' !== $slide['eyebrow'] || '' !== $slide['title'] || '' !== $slide['subtitle'] || $slide['button'];
            ?>
            <div class="hive-hero-carousel__slide" id="<?php echo esc_attr($prefix . '-' . ($index + 1)); ?>" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr(sprintf('%d di %d', $index + 1, $count)); ?>">
                <?php if ('' !== $image) : ?>
                    <div class="hive-hero-carousel__media"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Media::image() escapes. ?></div>
                <?php endif; ?>
                <?php if ($texts) : ?>
                    <div class="hive-hero-carousel__content">
                        <?php if ('' !== $slide['eyebrow']) : ?>
                            <p class="hive-hero-carousel__eyebrow"><?php echo esc_html($slide['eyebrow']); ?></p>
                        <?php endif; ?>
                        <?php if ('' !== $slide['title']) : ?>
                            <h2 class="hive-hero-carousel__title"><?php echo esc_html($slide['title']); ?></h2>
                        <?php endif; ?>
                        <?php if ('' !== $slide['subtitle']) : ?>
                            <p class="hive-hero-carousel__subtitle"><?php echo esc_html($slide['subtitle']); ?></p>
                        <?php endif; ?>
                        <?php if ($slide['button']) : ?>
                            <p class="hive-hero-carousel__cta"><a class="hive-button hive-hero-carousel__button" href="<?php echo esc_url($slide['button']['url']); ?>"><?php echo esc_html($slide['button']['text']); ?></a></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
