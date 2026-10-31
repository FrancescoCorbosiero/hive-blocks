<?php
/**
 * Sezione con shortcode — server render: a section around a shortcode's
 * output (a product rail, a WooCommerce list…) with an eyebrow, a title and
 * a button, on the page's background, a light band or a dark one.
 *
 * The Store Hub's Vetrina edits these sections in place: eyebrow, title,
 * backgroundColor (white, gray, black), buttonText, buttonUrl and hidden,
 * the names, values and defaults golden-hive-blocks used (the bridge reads
 * the defaults from the block registry). hidden switches a section off
 * ('yes' as golden-hive-blocks wrote it, or true): it stays in the page with
 * its shortcode, and the site shows nothing. Neither does a shortcode that
 * prints nothing (a rail with no products left): no heading over an empty
 * space. The shortcode goes through do_shortcode() as written, unquoted
 * attributes included ([hive_product_rail type=recent limit=4]).
 *
 * Pages are cached: the shortcode should print the same for every visitor.
 *
 * @var array $attributes
 *
 * @package Hive
 */

defined('ABSPATH') || exit;

$shortcode = trim((string) ($attributes['shortcode'] ?? ''));
if ('' === $shortcode || !empty($attributes['hidden'])) {
    return;
}

$output = do_shortcode($shortcode);
if ('' === trim($output)) {
    return;
}

$eyebrow    = trim((string) ($attributes['eyebrow'] ?? ''));
$heading    = trim((string) ($attributes['title'] ?? ''));
$label      = trim((string) ($attributes['buttonText'] ?? ''));
$target     = trim((string) ($attributes['buttonUrl'] ?? ''));
$background = (string) ($attributes['backgroundColor'] ?? 'white');
$classes    = 'hive-block hive-shortcode-wrapper';
if (in_array($background, ['gray', 'black'], true)) {
    $classes .= ' hive-shortcode-wrapper--' . $background;
}
?>
<section <?php echo get_block_wrapper_attributes(['class' => $classes]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <?php if ('' !== $eyebrow || '' !== $heading) : ?>
        <div class="hive-shortcode-wrapper__header">
            <?php if ('' !== $eyebrow) : ?>
                <p class="hive-shortcode-wrapper__eyebrow"><?php echo esc_html($eyebrow); ?></p>
            <?php endif; ?>
            <?php if ('' !== $heading) : ?>
                <h2 class="hive-shortcode-wrapper__title"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="hive-shortcode-wrapper__content">
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- as in post content: WordPress filters block attributes on save for users without unfiltered_html, and each shortcode's plugin escapes what it prints.
        echo $output;
        ?>
    </div>
    <?php if ('' !== $label && '' !== esc_url($target)) : ?>
        <p class="hive-shortcode-wrapper__cta">
            <a class="hive-button hive-shortcode-wrapper__button" href="<?php echo esc_url($target); ?>"><?php echo esc_html($label); ?></a>
        </p>
    <?php endif; ?>
</section>
