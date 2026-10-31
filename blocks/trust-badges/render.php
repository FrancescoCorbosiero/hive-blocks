<?php
/**
 * Badge di fiducia — server render: a strip of guarantees, an icon and a
 * label each. "Striscia" (variant default) sits on a light tint of the page;
 * "Fascia scura" (variant carousel) is a band in the buttons' colour. When
 * the badges don't fit, the strip scrolls sideways under the visitor's
 * finger: nothing moves by itself.
 *
 * @var array $attributes
 *
 * @package Hive
 */

defined('ABSPATH') || exit;

// The icons' shapes (24×24, stroked).
$icons = [
    'authentic' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
    'shipping'  => '<rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
    'returns'   => '<path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
    'secure'    => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    'support'   => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
    'quality'   => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>',
];

// A badge's label and icon: a list item edited by hand may hold anything.
$field = static fn(mixed $item, string $key): string => is_array($item) && is_scalar($item[$key] ?? null) ? trim((string) $item[$key]) : '';

$badges = [];
foreach ((array) ($attributes['badges'] ?? []) as $badge) {
    $label = $field($badge, 'title');
    if ('' !== $label) {
        $badges[] = [$icons[$field($badge, 'icon')] ?? $icons['authentic'], $label];
    }
}
if (!$badges) {
    return;
}

$dark = 'carousel' === ($attributes['variant'] ?? 'default');
?>
<div <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-badges' . ($dark ? ' hive-badges--dark' : '')]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <ul class="hive-badges__list" role="list">
        <?php foreach ($badges as [$shape, $label]) : ?>
            <li class="hive-badges__item">
                <svg class="hive-badges__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?php echo $shape; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG shapes. ?></svg>
                <span class="hive-badges__label"><?php echo esc_html($label); ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
