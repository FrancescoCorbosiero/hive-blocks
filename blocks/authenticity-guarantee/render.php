<?php
/**
 * Garanzia di autenticità — server render: the seal (under it the store's
 * name, unless the block gives another), then the promise, the steps of
 * the check as a numbered list, and the button when it has a link.
 *
 * @var array $attributes
 *
 * @package Hive
 */

defined('ABSPATH') || exit;

$text = static fn(string $key): string => trim((string) ($attributes[$key] ?? ''));

$heading = $text('title');
if ('' === $heading) {
    return;
}

$eyebrow     = $text('eyebrow');
$description = $text('description');
$badge_text  = $text('badgeText');
$badge_sub   = '' !== $text('badgeSub') ? $text('badgeSub') : trim(wp_strip_all_tags((string) get_bloginfo('name')));
$button_text = $text('buttonText');
$button_url  = $text('buttonUrl');

// A step's text: a list item edited by hand may hold anything.
$field = static fn(mixed $item, string $key): string => is_array($item) && is_scalar($item[$key] ?? null) ? trim((string) $item[$key]) : '';

$steps = [];
foreach ((array) ($attributes['steps'] ?? []) as $step) {
    $step_title = $field($step, 'title');
    $step_text  = $field($step, 'text');
    if ('' !== $step_title || '' !== $step_text) {
        $steps[] = [$step_title, $step_text];
    }
}
?>
<section <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-authenticity']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <div class="hive-authenticity__inner">
        <div class="hive-authenticity__seal">
            <span class="hive-authenticity__shield">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
            </span>
            <?php if ('' !== $badge_text) : ?>
                <span class="hive-authenticity__badge"><?php echo esc_html($badge_text); ?></span>
            <?php endif; ?>
            <?php if ('' !== $badge_sub) : ?>
                <span class="hive-authenticity__signature"><?php echo esc_html($badge_sub); ?></span>
            <?php endif; ?>
        </div>

        <div class="hive-authenticity__content">
            <?php if ('' !== $eyebrow) : ?>
                <p class="hive-authenticity__eyebrow"><?php echo esc_html($eyebrow); ?></p>
            <?php endif; ?>
            <h2 class="hive-authenticity__title"><?php echo esc_html($heading); ?></h2>
            <?php if ('' !== $description) : ?>
                <p class="hive-authenticity__description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>

            <?php if ($steps) : ?>
                <ol class="hive-authenticity__steps" role="list">
                    <?php foreach ($steps as $index => [$step_title, $step_text]) : ?>
                        <li class="hive-authenticity__step">
                            <span class="hive-authenticity__number" aria-hidden="true"><?php echo (int) $index + 1; ?></span>
                            <div class="hive-authenticity__step-body">
                                <?php if ('' !== $step_title) : ?>
                                    <h3 class="hive-authenticity__step-title"><?php echo esc_html($step_title); ?></h3>
                                <?php endif; ?>
                                <?php if ('' !== $step_text) : ?>
                                    <p class="hive-authenticity__step-text"><?php echo esc_html($step_text); ?></p>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <?php if ('' !== $button_text && '' !== $button_url && '#' !== $button_url) : ?>
                <a class="hive-button hive-authenticity__button" href="<?php echo esc_url($button_url); ?>"><?php echo esc_html($button_text); ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>
