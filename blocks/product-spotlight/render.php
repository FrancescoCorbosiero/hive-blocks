<?php
/**
 * Prodotto in evidenza — server render: one product in the foreground, the
 * one chosen in the block or else the newest featured one that can be bought
 * (Hive\Shop\Spotlight). Photo, brand, name, price, a few typed details and
 * the product card's button: data-hive-add for @hive/cards (block.json's
 * view module), which adds a simple product to the cart or opens Quick Add
 * to choose the size.
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Shop\Card;
use Hive\Shop\Spotlight;
use Hive\Shop\Store;

defined('ABSPATH') || exit;

$product = function_exists('wc_get_product') ? Spotlight::product((int) ($attributes['productId'] ?? 0)) : null;
if (!$product instanceof WC_Product) {
    return;
}

$url     = $product->get_permalink();
$name    = wp_strip_all_tags($product->get_name());
$heading = trim((string) ($attributes['title'] ?? ''));
$heading = '' !== $heading ? $heading : $name;
$eyebrow = trim((string) ($attributes['eyebrow'] ?? ''));
$text    = trim((string) ($attributes['description'] ?? ''));
$text    = '' !== $text ? $text : wp_trim_words(wp_strip_all_tags((string) $product->get_short_description()), 32, '…');
$brand   = Store::brand($product);
// The brand line only when the name doesn't already start with it.
if ('' !== $brand && 0 === stripos($heading, $brand)) {
    $brand = '';
}
$details = array_filter(
    [
        'Condizione' => trim((string) ($attributes['condition'] ?? '')),
        'Taglia'     => trim((string) ($attributes['size'] ?? '')),
    ],
    'strlen'
);
$verified = trim((string) ($attributes['authenticity'] ?? ''));
$state    = Card::state($product);
$badges   = Card::badge_list($product, $state);
$price    = $product->get_price_html();

$image_id    = (int) ($attributes['imageUrlId'] ?? 0);
$image_id    = $image_id ? $image_id : (int) $product->get_image_id();
$image_attrs = [
    'class'    => 'hive-product-spotlight__image',
    'alt'      => '',
    'loading'  => 'lazy',
    'decoding' => 'async',
    'sizes'    => '(max-width: 767px) 100vw, 50vw',
];
$image = $image_id ? wp_get_attachment_image($image_id, 'woocommerce_single', false, $image_attrs) : '';
$image = '' !== $image ? $image : wc_placeholder_img('woocommerce_single', $image_attrs);
?>
<section <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-product-spotlight is-' . $state['kind']]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?> data-product-id="<?php echo (int) $product->get_id(); ?>">
    <div class="hive-product-spotlight__inner">
        <div class="hive-product-spotlight__media">
            <a class="hive-product-spotlight__photo" href="<?php echo esc_url($url); ?>" tabindex="-1" aria-hidden="true"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by wp_get_attachment_image(). ?></a>
            <?php if ($badges || '' !== $verified) : ?>
                <p class="hive-product-spotlight__badges">
                    <?php foreach ($badges as $badge_class => $badge_text) : ?>
                        <span class="hive-product-spotlight__badge <?php echo esc_attr((string) $badge_class); ?>"><?php echo esc_html((string) $badge_text); ?></span>
                    <?php endforeach; ?>
                    <?php if ('' !== $verified) : ?>
                        <span class="hive-product-spotlight__badge is-verified"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg><?php echo esc_html($verified); ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="hive-product-spotlight__body">
            <?php if ('' !== $eyebrow) : ?>
                <p class="hive-product-spotlight__eyebrow"><?php echo esc_html($eyebrow); ?></p>
            <?php endif; ?>
            <?php if ('' !== $brand) : ?>
                <p class="hive-product-spotlight__brand"><?php echo esc_html($brand); ?></p>
            <?php endif; ?>
            <h2 class="hive-product-spotlight__title"><a class="hive-product-spotlight__link" href="<?php echo esc_url($url); ?>"><?php echo esc_html($heading); ?></a></h2>
            <?php if ('' !== $text) : ?>
                <p class="hive-product-spotlight__text"><?php echo esc_html($text); ?></p>
            <?php endif; ?>
            <?php if ($details) : ?>
                <dl class="hive-product-spotlight__details">
                    <?php foreach ($details as $label => $value) : ?>
                        <div class="hive-product-spotlight__detail">
                            <dt class="hive-product-spotlight__label"><?php echo esc_html($label); ?></dt>
                            <dd class="hive-product-spotlight__value"><?php echo esc_html($value); ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
            <?php if ('' !== $price) : ?>
                <div class="hive-product-spotlight__price"><?php echo wp_kses_post($price); ?></div>
            <?php endif; ?>
            <div class="hive-product-spotlight__actions">
                <?php echo Spotlight::button($product, 'hive-product-spotlight__add', trim((string) ($attributes['buttonText'] ?? ''))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Spotlight::button() escapes what it prints. ?>
            </div>
        </div>
    </div>
</section>
