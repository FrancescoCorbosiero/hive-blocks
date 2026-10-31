<?php
/**
 * Legit check — server render: for each detail the team checks, what the
 * real one looks like and what gives a fake away, side by side; then the
 * button, when it has a link.
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
$subtitle    = $text('subtitle');
$button_text = $text('buttonText');
$button_url  = $text('buttonUrl');

// A check's texts: a list item edited by hand may hold anything.
$field = static fn(mixed $item, string $key): string => is_array($item) && is_scalar($item[$key] ?? null) ? trim((string) $item[$key]) : '';

$checks = [];
foreach ((array) ($attributes['checks'] ?? []) as $check) {
    $row = [$field($check, 'area'), $field($check, 'real'), $field($check, 'fake')];
    if ('' !== implode('', $row)) {
        $checks[] = $row;
    }
}

$check_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"/></svg>';
$cross_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18M6 6l12 12"/></svg>';
?>
<section <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-legit']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <div class="hive-legit__inner">
        <div class="hive-legit__header">
            <?php if ('' !== $eyebrow) : ?>
                <p class="hive-legit__eyebrow"><?php echo esc_html($eyebrow); ?></p>
            <?php endif; ?>
            <h2 class="hive-legit__title"><?php echo esc_html($heading); ?></h2>
            <?php if ('' !== $subtitle) : ?>
                <p class="hive-legit__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($checks) : ?>
            <ul class="hive-legit__grid" role="list">
                <?php foreach ($checks as [$area, $real, $fake]) : ?>
                    <li class="hive-legit__card">
                        <?php if ('' !== $area) : ?>
                            <h3 class="hive-legit__area"><?php echo esc_html($area); ?></h3>
                        <?php endif; ?>
                        <dl class="hive-legit__sides">
                            <div class="hive-legit__side is-real">
                                <dt class="hive-legit__label"><span class="hive-legit__mark"><?php echo $check_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>Autentico</dt>
                                <dd class="hive-legit__text"><?php echo esc_html($real); ?></dd>
                            </div>
                            <div class="hive-legit__side is-fake">
                                <dt class="hive-legit__label"><span class="hive-legit__mark"><?php echo $cross_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>Falso</dt>
                                <dd class="hive-legit__text"><?php echo esc_html($fake); ?></dd>
                            </div>
                        </dl>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ('' !== $button_text && '' !== $button_url && '#' !== $button_url) : ?>
            <p class="hive-legit__cta"><a class="hive-button" href="<?php echo esc_url($button_url); ?>"><?php echo esc_html($button_text); ?></a></p>
        <?php endif; ?>
    </div>
</section>
