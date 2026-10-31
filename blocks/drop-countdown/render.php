<?php
/**
 * Conto alla rovescia del drop — server render. What it counts down to:
 *
 *   a product chosen in the block: its drop (Product data → General → Drop),
 *   its photo, price and page; the date typed in the block if it has none
 *   a date typed in the block, with a name and a link
 *   neither: the store's next drop (Drops::upcoming()), or the one just out
 *
 * The time left ticks in the browser (@hive/drop, block.json's view module),
 * so a cached page is right to the second. At the release the timer and
 * "Ricordamelo" give way to "Acquista ora", on a page cached before it too.
 * "Ricordamelo" (Google Calendar or an .ics file, Drops::remind()) comes with
 * the Drop module; a typed date's calendar file is signed (Drops::ics_url()).
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Core\DropDate;
use Hive\Modules;
use Hive\Shop\Drops;
use Hive\Shop\ProductData;

defined('ABSPATH') || exit;

$drops_on   = Modules::enabled('drops');
$product_id = (int) ($attributes['productId'] ?? 0);
$typed_date = trim((string) ($attributes['releaseDate'] ?? ''));
$product    = null;

if ($product_id && function_exists('wc_get_product')) {
    $chosen  = wc_get_product($product_id);
    $product = ProductData::is_public($chosen) ? $chosen : null;
} elseif ('' === $typed_date && $drops_on && function_exists('wc_get_product')) {
    // Nothing chosen: the next drop, or else the one out in the last day.
    $product = Drops::upcoming(1)[0] ?? Drops::upcoming(1, 1)[0] ?? null;
}

$own        = $product && $drops_on ? Drops::ts($product->get_id()) : 0;
$ts         = $own ? $own : DropDate::parse($typed_date, wp_timezone());
$typed_name = trim((string) ($attributes['productName'] ?? ''));
$name       = '' !== $typed_name ? $typed_name : ($product ? wp_strip_all_tags($product->get_name()) : '');
if (!$ts || '' === $name) {
    return;
}

$released = $ts <= time();
$target   = $product ? $product->get_permalink() : trim((string) ($attributes['buttonUrl'] ?? ''));
$eyebrow  = trim((string) ($attributes['eyebrow'] ?? ''));
$label    = trim((string) ($attributes['buttonText'] ?? ''));
$label    = '' !== $label ? $label : 'Scopri di più';
$price    = $product ? $product->get_price_html() : '';

$image_id = (int) ($attributes['productImageId'] ?? 0);
$image_id = $image_id ? $image_id : ($product ? (int) $product->get_image_id() : 0);
$image    = $image_id ? wp_get_attachment_image(
    $image_id,
    'woocommerce_single',
    false,
    [
        'class'    => 'hive-drop-countdown__image',
        'alt'      => '',
        'loading'  => 'lazy',
        'decoding' => 'async',
        'sizes'    => '(max-width: 767px) 100vw, 40vw',
    ]
) : '';

$remind = '';
if ($drops_on) {
    $event  = $own ? Drops::event($product) : ['ts' => $ts, 'title' => 'Drop: ' . $name, 'url' => $target];
    $remind = Drops::remind($event, Drops::ics_url($event, $own ? $product->get_id() : 0));
}

$units = ['d' => 'giorni', 'h' => 'ore', 'm' => 'min', 's' => 'sec'];
?>
<section <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-drop-countdown' . ($released ? ' is-released' : '')]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?> data-hive-drop="<?php echo (int) $ts; ?>">
    <div class="hive-drop-countdown__inner<?php echo '' !== $image ? ' has-image' : ''; ?>">
        <?php if ('' !== $image) : ?>
            <div class="hive-drop-countdown__media"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by wp_get_attachment_image(). ?></div>
        <?php endif; ?>
        <div class="hive-drop-countdown__body">
            <?php if ('' !== $eyebrow) : ?>
                <p class="hive-drop-countdown__eyebrow"><?php echo esc_html($eyebrow); ?></p>
            <?php endif; ?>
            <h2 class="hive-drop-countdown__title">
                <?php if ('' !== $target) : ?>
                    <a class="hive-drop-countdown__link" href="<?php echo esc_url($target); ?>"><?php echo esc_html($name); ?></a>
                <?php else : ?>
                    <?php echo esc_html($name); ?>
                <?php endif; ?>
            </h2>
            <p class="hive-drop-countdown__meta">
                <span class="hive-drop-countdown__date"><?php echo esc_html(Drops::long_date($ts)); ?></span>
                <?php if ('' !== $price) : ?>
                    <span class="hive-drop-countdown__price"><?php echo wp_kses_post($price); ?></span>
                <?php endif; ?>
            </p>
            <div class="hive-drop-countdown__timer" role="timer" aria-label="Tempo all’uscita" data-hive-drop-before<?php echo $released ? ' hidden' : ''; ?>>
                <?php foreach ($units as $unit => $unit_label) : ?>
                    <span class="hive-drop-countdown__cell"><b class="hive-drop-countdown__value" data-unit="<?php echo esc_attr($unit); ?>">00</b><small class="hive-drop-countdown__unit"><?php echo esc_html($unit_label); ?></small></span>
                <?php endforeach; ?>
            </div>
            <div class="hive-drop-countdown__actions" data-hive-drop-before<?php echo $released ? ' hidden' : ''; ?>>
                <?php
                echo $remind; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Drops::remind() escapes what it prints.
                if ('' !== $target) {
                    printf('<a class="hive-drop-countdown__more" href="%s">%s</a>', esc_url($target), esc_html($product ? 'Vedi il prodotto' : $label));
                }
                ?>
            </div>
            <div class="hive-drop-countdown__actions" data-hive-drop-after<?php echo $released ? '' : ' hidden'; ?>>
                <p class="hive-drop-countdown__out">Disponibile ora</p>
                <?php if ('' !== $target) : ?>
                    <a class="hive-button hive-drop-countdown__buy" href="<?php echo esc_url($target); ?>"><?php echo esc_html($product ? 'Acquista ora' : $label); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
