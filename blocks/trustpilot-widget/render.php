<?php
/**
 * Trustpilot — server render: the store's rating as the block states it
 * (label, five stars rounded down to the half, the score, the number of
 * reviews), linked to the store's Trustpilot page when there is one.
 * Nothing loads from Trustpilot: no script, no frame, no cookie.
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Core\StarRating;

defined('ABSPATH') || exit;

$rating      = $attributes['rating'] ?? 4.8;
$label       = trim((string) ($attributes['label'] ?? ''));
$url         = trim((string) ($attributes['trustpilotUrl'] ?? ''));
$score       = StarRating::score($rating);
$count       = StarRating::count($attributes['reviewCount'] ?? 0);
$star        = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>';
$wrapper_tag = '' !== $url ? 'a' : 'div';
?>
<div <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-trustpilot']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <<?php echo esc_attr($wrapper_tag); ?> class="hive-trustpilot__inner"<?php echo '' !== $url ? ' href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer"' : ''; ?>>
        <?php if ('' !== $label) : ?>
            <span class="hive-trustpilot__label"><?php echo esc_html($label); ?></span>
        <?php endif; ?>
        <span class="hive-trustpilot__stars" role="img" aria-label="<?php echo esc_attr($score . ' su 5'); ?>">
            <?php foreach (StarRating::stars($rating) as $state) : ?>
                <span class="hive-trustpilot__star is-<?php echo esc_attr($state); ?>"><?php echo $star; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
            <?php endforeach; ?>
        </span>
        <span class="hive-trustpilot__score" aria-hidden="true"><?php echo esc_html($score); ?> / 5</span>
        <span class="hive-trustpilot__count">Basato su <strong><?php echo esc_html($count); ?></strong> recensioni</span>
        <span class="hive-trustpilot__brand"><?php echo $star; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>Trustpilot</span>
    </<?php echo esc_attr($wrapper_tag); ?>>
</div>
