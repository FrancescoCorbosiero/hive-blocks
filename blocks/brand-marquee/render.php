<?php
/**
 * Brand in scorrimento — server render. The brands typed in the block (a
 * logo, a name, a link) or else the store's own (Hive\Shop\Showcase): the
 * chosen ones, or every brand with products, with its logo and its page.
 *
 * The loop is CSS only (style.css): two identical tracks, each long enough
 * to cover the screen (a short list is repeated, Core\Marquee), sliding by
 * their own width; it stops under the mouse and, for reduced motion, shows
 * the list once, still. Only the first copy of each brand reaches screen
 * readers and the keyboard.
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Core\Marquee;
use Hive\Shop\Showcase;

defined('ABSPATH') || exit;

$items = [];
foreach ((array) ($attributes['brands'] ?? []) as $typed) {
    if (!is_array($typed)) {
        continue;
    }
    $item = [
        'name' => trim(wp_strip_all_tags((string) ($typed['name'] ?? ''))),
        'logo' => (int) ($typed['logoId'] ?? 0),
        'url'  => trim((string) ($typed['url'] ?? '')),
    ];
    if ('' !== $item['name'] || $item['logo']) {
        $items[] = $item;
    }
}
if (!$items && function_exists('wc_get_product')) {
    $items = Showcase::brands((string) ($attributes['brand'] ?? ''));
}
if (!$items) {
    return;
}

$copies  = Marquee::copies(count($items));
$seconds = Marquee::duration(count($items) * $copies, (int) ($attributes['speed'] ?? 50));
$heading = trim((string) ($attributes['title'] ?? ''));
$classes = 'hive-block hive-brand-marquee' . ('right' === ($attributes['direction'] ?? 'left') ? ' hive-brand-marquee--reverse' : '');

// Each brand drawn once: a logo (or its name), a link when it has one.
$drawn = [];
foreach ($items as $item) {
    $logo = $item['logo'] ? wp_get_attachment_image(
        $item['logo'],
        'medium',
        false,
        [
            'class'    => 'hive-brand-marquee__logo',
            'alt'      => $item['name'],
            'loading'  => 'lazy',
            'decoding' => 'async',
            'sizes'    => '10rem',
        ]
    ) : '';
    $inner   = '' !== $logo ? $logo : '<span class="hive-brand-marquee__name">' . esc_html($item['name']) . '</span>';
    $drawn[] = [
        'inner' => $inner,
        'url'   => $item['url'],
    ];
}

/** One track: the list $copies times; only the first copy of the first track is for everyone. */
$track = static function (bool $clone) use ($drawn, $copies): string {
    $html = sprintf('<ul class="hive-brand-marquee__track" role="list"%s>', $clone ? ' aria-hidden="true"' : '');
    for ($copy = 0; $copy < $copies; $copy++) {
        $hidden = $clone || $copy > 0;
        foreach ($drawn as $item) {
            $html .= sprintf('<li class="hive-brand-marquee__item"%s>', $hidden ? ' aria-hidden="true"' : '');
            $html .= '' !== $item['url']
                ? sprintf('<a class="hive-brand-marquee__brand" href="%s"%s>%s</a>', esc_url($item['url']), $hidden ? ' tabindex="-1"' : '', $item['inner'])
                : '<span class="hive-brand-marquee__brand">' . $item['inner'] . '</span>';
            $html .= '</li>';
        }
    }
    return $html . '</ul>';
};
?>
<section <?php echo get_block_wrapper_attributes(['class' => $classes, 'style' => '--hive-marquee-duration:' . $seconds . 's;']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <?php if ('' !== $heading) : ?>
        <h2 class="hive-brand-marquee__title"><?php echo esc_html($heading); ?></h2>
    <?php endif; ?>
    <div class="hive-brand-marquee__viewport">
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from escaped parts (esc_html, esc_url, wp_get_attachment_image).
        echo $track(false) . $track(true);
        ?>
    </div>
</section>
