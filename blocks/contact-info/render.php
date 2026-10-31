<?php
/**
 * Contatti — server render: the store's details as links (phone, email,
 * WhatsApp on the block's number or the one in Hive → Impostazioni) and,
 * beside them, the map that loads when the visitor asks (Hive\Blocks\Map).
 * Without a map but with its link, "Apri in Google Maps" joins the list.
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Blocks\Contact;
use Hive\Blocks\Map;
use Hive\Core\ContactLinks;

defined('ABSPATH') || exit;

$text = static fn(string $key): string => trim((string) ($attributes[$key] ?? ''));

$heading  = $text('title');
$subtitle = $text('subtitle');
$address  = $text('address');
$map_url  = $text('mapUrl');
$map_html = Map::facade($text('mapEmbed'), ['address' => $address, 'open' => $map_url]);

// Each detail: [icon, its HTML].
$items = [];
if ('' !== $address) {
    $items[] = ['address', '<span class="hive-contact__text">' . esc_html($address) . '</span>'];
}
if ('' !== $text('vat')) {
    $items[] = ['vat', '<span class="hive-contact__text">' . esc_html($text('vat')) . '</span>'];
}
$tel = ContactLinks::tel($text('phone'));
if ('' !== $tel) {
    $items[] = ['phone', sprintf('<a class="hive-contact__link" href="%s">%s</a>', esc_url($tel), esc_html($text('phone')))];
}
$mailto = ContactLinks::mailto($text('email'));
if ('' !== $mailto) {
    $items[] = ['email', sprintf('<a class="hive-contact__link" href="%s">%s</a>', esc_url($mailto), esc_html($text('email')))];
}
$whatsapp = empty($attributes['showWhatsApp']) ? '' : Contact::whatsapp($text('whatsapp'), $text('whatsappText'));
if ('' !== $whatsapp) {
    $items[] = ['whatsapp', sprintf('<a class="hive-contact__link" href="%s" target="_blank" rel="noopener noreferrer">Scrivici su WhatsApp</a>', esc_url($whatsapp))];
}
if ('' === $map_html && '' !== $map_url) {
    $items[] = ['map', sprintf('<a class="hive-contact__link" href="%s" target="_blank" rel="noopener noreferrer">Apri in Google Maps</a>', esc_url($map_url))];
}

if ('' === $heading && '' === $subtitle && !$items && '' === $map_html) {
    return;
}
// The details and the map side by side, from tablets up.
$split = $items && '' !== $map_html;
?>
<section <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-contact' . ($split ? ' hive-contact--split' : '')]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <div class="hive-contact__inner">
        <?php if ('' !== $heading || '' !== $subtitle) : ?>
            <div class="hive-contact__header">
                <?php if ('' !== $heading) : ?>
                    <h2 class="hive-contact__title"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ('' !== $subtitle) : ?>
                    <p class="hive-contact__subtitle"><?php echo esc_html($subtitle); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="hive-contact__body">
            <?php if ($items) : ?>
                <address class="hive-contact__details">
                    <ul class="hive-contact__list">
                        <?php foreach ($items as [$icon, $html]) : ?>
                            <li class="hive-contact__item">
                                <span class="hive-contact__icon"><?php echo Contact::icon($icon); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
                                <?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </address>
            <?php endif; ?>

            <?php if ('' !== $map_html) : ?>
                <div class="hive-contact__map">
                    <?php echo $map_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Map::facade() escapes what it prints. ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
