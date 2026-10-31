<?php
/**
 * Slider categorie — server render: the product categories chosen in the
 * block, or the top-level ones with products, each with its picture
 * (Hive\Shop\Showcase), in a row that scrolls sideways with CSS
 * scroll-snap. view.js adds the arrows on desktop when there's more to see.
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Shop\Showcase;

defined('ABSPATH') || exit;

$items = function_exists('wc_get_product') ? Showcase::categories((string) ($attributes['category'] ?? '')) : [];
if (!$items) {
    return;
}

$heading = trim((string) ($attributes['title'] ?? ''));
$arrows  = (bool) ($attributes['showNav'] ?? true);
// "4 / 5", "3/4": the pictures' proportion; anything else, the default.
$ratio = 1 === preg_match('#^\s*(\d{1,2})\s*/\s*(\d{1,2})\s*$#', (string) ($attributes['imageRatio'] ?? ''), $parts) && (int) $parts[1] > 0 && (int) $parts[2] > 0
    ? (int) $parts[1] . ' / ' . (int) $parts[2]
    : '4 / 5';
?>
<section <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-category-slider', 'style' => '--hive-category-ratio:' . $ratio . ';']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <?php if ('' !== $heading) : ?>
        <h2 class="hive-category-slider__title"><?php echo esc_html($heading); ?></h2>
    <?php endif; ?>
    <div class="hive-category-slider__viewport">
        <?php if ($arrows) : ?>
            <button type="button" class="hive-category-slider__nav" data-dir="-1" aria-label="Categorie precedenti" hidden><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m14.5 6-6 6 6 6"/></svg></button>
        <?php endif; ?>
        <ul class="hive-category-slider__track" role="list">
            <?php foreach ($items as $item) : ?>
                <li class="hive-category-slider__item">
                    <a class="hive-category-slider__link" href="<?php echo esc_url($item['url']); ?>">
                        <span class="hive-category-slider__media">
                            <?php
                            $picture = $item['image'] ? wp_get_attachment_image(
                                $item['image'],
                                'medium',
                                false,
                                [
                                    'class'    => 'hive-category-slider__image',
                                    'alt'      => '',
                                    'loading'  => 'lazy',
                                    'decoding' => 'async',
                                    'sizes'    => '(max-width: 767px) 45vw, 15rem',
                                ]
                            ) : '';
                            if ('' !== $picture) {
                                echo $picture; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by wp_get_attachment_image().
                            } else {
                                printf('<span class="hive-category-slider__initial" aria-hidden="true">%s</span>', esc_html(mb_substr($item['name'], 0, 1)));
                            }
                            ?>
                        </span>
                        <span class="hive-category-slider__name"><?php echo esc_html($item['name']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($arrows) : ?>
            <button type="button" class="hive-category-slider__nav" data-dir="1" aria-label="Categorie successive" hidden><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9.5 6 6 6-6 6"/></svg></button>
        <?php endif; ?>
    </div>
</section>
