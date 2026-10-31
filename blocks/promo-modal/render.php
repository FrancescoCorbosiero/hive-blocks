<?php
/**
 * Popup promozionale — server render. The offer is a <template>: nothing
 * shows on the page and its image doesn't even load until view.js puts it
 * in a dialog (@hive/dialog), after the delay or when the visitor is about
 * to leave, once per visitor (localStorage) or once per visit, and whenever
 * a link to #modalId is clicked. It doesn't open by itself on the cart and
 * checkout pages. In the editor the offer is drawn in the canvas.
 *
 * @var array $attributes
 *
 * @package Hive
 */

defined('ABSPATH') || exit;

$heading = trim((string) ($attributes['title'] ?? ''));
if ('' === $heading) {
    return;
}

$modal      = sanitize_title((string) ($attributes['modalId'] ?? ''));
$modal      = '' !== $modal ? $modal : 'promo-popup';
$badge      = trim((string) ($attributes['badge'] ?? ''));
$text       = trim((string) ($attributes['text'] ?? ''));
$code       = trim((string) ($attributes['couponCode'] ?? ''));
$label      = trim((string) ($attributes['buttonText'] ?? ''));
$url        = trim((string) ($attributes['buttonUrl'] ?? ''));
$disclaimer = trim((string) ($attributes['disclaimer'] ?? ''));
$image_id   = (int) ($attributes['imageUrlId'] ?? 0);
$image      = $image_id ? wp_get_attachment_image(
    $image_id,
    'large',
    false,
    [
        'class'    => 'hive-promo__image',
        'alt'      => '',
        'loading'  => 'lazy',
        'decoding' => 'async',
        'sizes'    => '(max-width: 767px) 100vw, 28rem',
    ]
) : '';
$in_editor = wp_is_serving_rest_request();
$checkout  = function_exists('is_cart') && (is_cart() || is_checkout());
$config    = [
    'id'      => $modal,
    'label'   => $heading,
    'image'   => '' !== $image,
    'auto'    => (bool) ($attributes['autoShow'] ?? true) && !$checkout,
    'trigger' => 'exit' === ($attributes['trigger'] ?? 'delay') ? 'exit' : 'delay',
    'delay'   => max(0, (int) ($attributes['showDelay'] ?? 5000)),
    'once'    => (bool) ($attributes['showOnce'] ?? true),
];

ob_start();
?>
<div class="hive-promo<?php echo '' !== $image ? ' has-image' : ''; ?>">
    <?php if ('' !== $image) : ?>
        <div class="hive-promo__media"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by wp_get_attachment_image(). ?></div>
    <?php endif; ?>
    <div class="hive-promo__body">
        <?php if ('' !== $badge) : ?>
            <p class="hive-promo__badge"><?php echo esc_html($badge); ?></p>
        <?php endif; ?>
        <h2 class="hive-promo__title"><?php echo esc_html($heading); ?></h2>
        <?php if ('' !== $text) : ?>
            <p class="hive-promo__text"><?php echo esc_html($text); ?></p>
        <?php endif; ?>
        <?php if ('' !== $code) : ?>
            <p class="hive-promo__code">
                <span class="hive-promo__code-label">Codice</span>
                <code class="hive-promo__code-value"><?php echo esc_html($code); ?></code>
                <button type="button" class="hive-promo__copy" data-hive-promo-copy="<?php echo esc_attr($code); ?>">Copia</button>
            </p>
        <?php endif; ?>
        <?php if ('' !== $url && '' !== $label) : ?>
            <a class="hive-button hive-promo__button" href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
        <?php endif; ?>
        <?php if ('' !== $disclaimer) : ?>
            <p class="hive-promo__disclaimer"><?php echo esc_html($disclaimer); ?></p>
        <?php endif; ?>
    </div>
</div>
<?php
$panel = (string) ob_get_clean();
?>
<div <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-promo-modal' . ($in_editor ? ' is-preview' : '')]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?> data-hive-promo-modal="<?php echo esc_attr((string) wp_json_encode($config)); ?>">
    <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $panel is the escaped markup above.
    echo $in_editor ? $panel : '<template data-hive-promo-template>' . $panel . '</template>';
    ?>
</div>
