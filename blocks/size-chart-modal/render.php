<?php
/**
 * Guida alle taglie — server render: a button that opens a size guide in a
 * dialog, the way "Guida alle taglie" does on the product page.
 *
 * The guide is one of Hive → Guide alle taglie (guideId), under its own
 * title. Without one, or once it's no longer published, the block shows the
 * table it always had (US, UK, EU and CM for sneakers) under its own title,
 * description and tip: pages made with the old block keep what they showed.
 *
 * The guide waits in a <template> inside the block: inert, no request, the
 * same for every visitor. view.js puts a copy in the dialog (@hive/dialog,
 * loaded on the first click). Without the button (showTrigger off), a link
 * to #<modalId> on the page opens it.
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Shop\SizeGuides;

defined('ABSPATH') || exit;

$modal_id = sanitize_title((string) ($attributes['modalId'] ?? ''));
$label    = trim((string) ($attributes['triggerText'] ?? ''));
$with_btn = (bool) ($attributes['showTrigger'] ?? true);

// The chosen guide, while it's published. Guides are written in the block
// editor, so one could hold this very block pointing at itself: inside its
// own render, that one shows the table instead of rendering forever.
$guide     = null;
$guide_id  = (int) ($attributes['guideId'] ?? 0);
$rendering = (array) ($GLOBALS['hive_size_chart_guides'] ?? []);
if ($guide_id > 0 && !in_array($guide_id, $rendering, true)) {
    $found = get_post($guide_id);
    if ($found instanceof WP_Post && SizeGuides::TYPE === $found->post_type && 'publish' === $found->post_status) {
        $guide = $found;
    }
}

$guide_html = '';
if ($guide) {
    $heading = get_the_title($guide);

    // As Hive\Shop\SizeGuides::template() renders it for the product page.
    $GLOBALS['hive_size_chart_guides'][] = $guide->ID;
    $guide_html                          = (string) $guide->post_content;
    $guide_html                          = has_blocks($guide_html) ? do_blocks($guide_html) : wpautop($guide_html);
    $guide_html                          = wp_filter_content_tags(do_shortcode(shortcode_unautop($guide_html)));
    array_pop($GLOBALS['hive_size_chart_guides']);
} else {
    $heading = trim((string) ($attributes['title'] ?? ''));
    $intro   = trim((string) ($attributes['description'] ?? ''));
}

// The old block's table: US, UK, EU, CM.
$sizes = [
    ['6', '5.5', '38.5', '24'],
    ['6.5', '6', '39', '24.5'],
    ['7', '6', '40', '25'],
    ['7.5', '6.5', '40.5', '25.5'],
    ['8', '7', '41', '26'],
    ['8.5', '7.5', '42', '26.5'],
    ['9', '8', '42.5', '27'],
    ['9.5', '8.5', '43', '27.5'],
    ['10', '9', '44', '28'],
    ['10.5', '9.5', '44.5', '28.5'],
    ['11', '10', '45', '29'],
    ['12', '11', '46', '30'],
    ['13', '12', '47.5', '31'],
];

$wrapper = [
    'class'                => 'hive-block hive-size-chart-modal',
    'data-hive-size-chart' => '' === $modal_id ? 'size-chart' : $modal_id,
];
if (!$with_btn) {
    // Only the guide, for the links to #<modalId>: nothing to see in the page.
    $wrapper['hidden'] = 'hidden';
}
?>
<div <?php echo get_block_wrapper_attributes($wrapper); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <?php if ($with_btn) : ?>
        <button type="button" class="hive-size-chart-modal__button" data-hive-size-chart-open aria-haspopup="dialog">
            <svg class="hive-size-chart-modal__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 8.5 8.5 3 21 15.5 15.5 21zm2.8 0 1.4 1.4 1.1-1.1 1.1 1.1-1.1 1.1 1.4 1.4 1.1-1.1 1.1 1.1-1.1 1.1 1.4 1.4 1.1-1.1 1.1 1.1-1.1 1.1 1.4 1.4 1.1-1.1 1.1 1.1-1.1 1.1 1.4 1.4 2.7-2.7L8.5 5.8z"/></svg>
            <span class="hive-size-chart-modal__label"><?php echo esc_html('' === $label ? 'Guida Taglie' : $label); ?></span>
        </button>
    <?php endif; ?>
    <template data-hive-size-chart-content data-title="<?php echo esc_attr('' === $heading ? 'Guida alle taglie' : $heading); ?>">
        <?php if ($guide) : ?>
            <?php echo $guide_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the guide's own content, rendered as WordPress renders a post's. ?>
        <?php else : ?>
            <?php if ('' !== $intro) : ?>
                <p><?php echo esc_html($intro); ?></p>
            <?php endif; ?>
            <figure>
                <table>
                    <thead>
                        <tr><th>US</th><th>UK</th><th>EU</th><th>CM</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sizes as $row) : ?>
                            <tr><?php foreach ($row as $cell) : ?><td><?php echo esc_html($cell); ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </figure>
            <p><strong>Consiglio:</strong> Se sei tra due taglie, ti consigliamo di scegliere la taglia più grande.</p>
        <?php endif; ?>
    </template>
</div>
