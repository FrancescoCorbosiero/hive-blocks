<?php
/**
 * Calendario dei drop — server render: the products with a drop date
 * (Drops::upcoming()), in release order: the coming ones first in line for
 * a place, then, when the block asks, those out in the last 7 days. Each
 * row: the date, the photo, the name, the price, the status and the actions
 * ("Ricordamelo" before, "Acquista ora" after).
 *
 * The status ("Esce tra 3 giorni", "Disponibile ora") is worked out in the
 * browser (@hive/drop, block.json's view module), which also swaps the
 * actions at the release: a cached page never calls a drop that is out
 * "in arrivo".
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Modules;
use Hive\Shop\Drops;

defined('ABSPATH') || exit;

if (!function_exists('wc_get_product') || !Modules::enabled('drops')) {
    return;
}

$limit = max(1, min(12, (int) ($attributes['limit'] ?? 6)));
$now   = time();
$drops = Drops::upcoming($limit);
// The drops out in the last 7 days fill the places the coming ones leave, the latest of them.
if (!empty($attributes['showReleased']) && count($drops) < $limit) {
    $out   = array_values(array_filter(Drops::upcoming(24, 7), static fn(WC_Product $product): bool => Drops::ts($product->get_id()) <= $now));
    $drops = array_merge(array_slice($out, -($limit - count($drops))), $drops);
}
$empty_text = trim((string) ($attributes['emptyText'] ?? ''));
if (!$drops && '' === $empty_text) {
    return;
}

$eyebrow  = trim((string) ($attributes['eyebrow'] ?? ''));
$heading  = trim((string) ($attributes['title'] ?? ''));
$subtitle = trim((string) ($attributes['subtitle'] ?? ''));
?>
<section <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-drop-calendar']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <?php if ('' !== $eyebrow || '' !== $heading || '' !== $subtitle) : ?>
        <div class="hive-drop-calendar__head">
            <?php if ('' !== $eyebrow) : ?>
                <p class="hive-drop-calendar__eyebrow"><?php echo esc_html($eyebrow); ?></p>
            <?php endif; ?>
            <?php if ('' !== $heading) : ?>
                <h2 class="hive-drop-calendar__title"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <?php if ('' !== $subtitle) : ?>
                <p class="hive-drop-calendar__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$drops) : ?>
        <p class="hive-drop-calendar__empty"><?php echo esc_html($empty_text); ?></p>
    <?php else : ?>
        <ol class="hive-drop-calendar__list" role="list">
            <?php
            foreach ($drops as $product) :
                $ts       = Drops::ts($product->get_id());
                $released = $ts <= $now;
                $url      = $product->get_permalink();
                $event    = Drops::event($product);
                $image_id = (int) $product->get_image_id();
                $image    = $image_id ? wp_get_attachment_image($image_id, 'woocommerce_thumbnail', false, ['class' => 'hive-drop-calendar__image', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '7rem']) : '';
                $price    = $product->get_price_html();
                ?>
                <li class="hive-drop-calendar__item<?php echo $released ? ' is-released' : ''; ?>" data-hive-drop="<?php echo (int) $ts; ?>">
                    <p class="hive-drop-calendar__date" aria-hidden="true">
                        <span class="hive-drop-calendar__day"><?php echo esc_html(wp_date('j', $ts)); ?></span>
                        <span class="hive-drop-calendar__month"><?php echo esc_html(wp_date('M', $ts)); ?></span>
                        <span class="hive-drop-calendar__time"><?php echo esc_html(wp_date('D · H:i', $ts)); ?></span>
                    </p>
                    <a class="hive-drop-calendar__media" href="<?php echo esc_url($url); ?>" tabindex="-1" aria-hidden="true">
                        <?php
                        echo '' !== $image ? $image : wc_placeholder_img('woocommerce_thumbnail', ['class' => 'hive-drop-calendar__image', 'alt' => '']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by WordPress and WooCommerce.
                        ?>
                    </a>
                    <div class="hive-drop-calendar__body">
                        <h3 class="hive-drop-calendar__name"><a class="hive-drop-calendar__link" href="<?php echo esc_url($url); ?>"><?php echo esc_html(wp_strip_all_tags($product->get_name())); ?></a></h3>
                        <p class="hive-drop-calendar__when"><?php echo esc_html(Drops::long_date($ts)); ?></p>
                        <?php if ('' !== $price) : ?>
                            <p class="hive-drop-calendar__price"><?php echo wp_kses_post($price); ?></p>
                        <?php endif; ?>
                        <p class="hive-drop-calendar__status" data-hive-drop-status><?php echo $released ? 'Disponibile ora' : 'In arrivo'; ?></p>
                    </div>
                    <div class="hive-drop-calendar__actions">
                        <div class="hive-drop-calendar__before" data-hive-drop-before<?php echo $released ? ' hidden' : ''; ?>>
                            <?php echo Drops::remind($event, Drops::ics_url($event, $product->get_id())); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Drops::remind() escapes what it prints. ?>
                        </div>
                        <div class="hive-drop-calendar__after" data-hive-drop-after<?php echo $released ? '' : ' hidden'; ?>>
                            <a class="hive-button hive-drop-calendar__buy" href="<?php echo esc_url($url); ?>">Acquista ora</a>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
