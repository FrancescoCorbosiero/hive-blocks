<?php
/**
 * Newsletter — the signup form (Hive\Shop\Newsletter answers it). With
 * "askSize", up to three sizes (and, with "askBrands", some brands) for the
 * size alerts, confirmed by email (Hive\Shop\Alerts).
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Modules;
use Hive\Shop\Alerts;
use Hive\Shop\Newsletter;

defined('ABSPATH') || exit;

$hive_title = trim((string) ($attributes['title'] ?? ''));
if ('' === $hive_title || !class_exists('WooCommerce')) {
    return;
}
$hive_text        = trim((string) ($attributes['text'] ?? ''));
$hive_placeholder = trim((string) ($attributes['placeholder'] ?? '')) ?: 'La tua email';
$hive_button      = trim((string) ($attributes['buttonText'] ?? '')) ?: 'Iscriviti';
$hive_disclaimer  = trim((string) ($attributes['disclaimer'] ?? ''));
$hive_sizes       = !empty($attributes['askSize']) && Modules::enabled('alerts') ? array_values(Alerts::size_options()) : [];
$hive_brands      = $hive_sizes && !empty($attributes['askBrands']) ? Newsletter::brand_options() : [];
$hive_size_label  = trim((string) ($attributes['sizeLabel'] ?? '')) ?: 'Avvisami quando arriva la mia taglia';
$hive_uid         = wp_unique_id('hive-newsletter-');

$hive_alerts = '';
if ($hive_sizes) {
    $hive_chips = '';
    foreach ($hive_sizes as $hive_option) {
        $hive_chips .= sprintf('<label class="hive-newsletter__chip"><input type="checkbox" name="sizes[]" value="%s"><span>%s</span></label>', esc_attr($hive_option['key']), esc_html($hive_option['label']));
    }
    $hive_brand_chips = '';
    foreach ($hive_brands as $hive_brand) {
        $hive_brand_chips .= sprintf('<label class="hive-newsletter__chip"><input type="checkbox" name="brands[]" value="%s"><span>%s</span></label>', esc_attr($hive_brand['slug']), esc_html($hive_brand['name']));
    }
    $hive_alerts = sprintf(
        '<details class="hive-newsletter__alerts"><summary class="hive-newsletter__alerts-title">%s <span>(facoltativo)</span></summary><p class="hive-newsletter__hint">Scegli fino a 3 taglie: ti scriviamo solo quando arriva un paio disponibile. Confermi dalla tua email e smetti quando vuoi.</p><div class="hive-newsletter__chips" role="group" aria-label="Taglie per gli avvisi" data-hive-newsletter-sizes>%s</div>%s</details>',
        esc_html($hive_size_label),
        $hive_chips,
        '' !== $hive_brand_chips ? '<p class="hive-newsletter__hint">Solo per questi marchi (facoltativo):</p><div class="hive-newsletter__chips" role="group" aria-label="Marchi per gli avvisi">' . $hive_brand_chips . '</div>' : ''
    );
}

printf(
    '<section %1$s><h2 class="hive-newsletter__title">%2$s</h2>%3$s<form class="hive-newsletter__form" method="post" action="%4$s" data-hive-newsletter novalidate><div class="hive-newsletter__row"><label class="hive-visually-hidden" for="%5$s">Il tuo indirizzo email</label><input class="hive-newsletter__email" id="%5$s" type="email" name="email" placeholder="%6$s" autocomplete="email" inputmode="email" required><button type="submit" class="hive-button hive-newsletter__submit">%7$s</button></div><input class="hive-newsletter__trap" type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true">%8$s<p class="hive-newsletter__status" role="status" data-hive-newsletter-status></p></form>%9$s</section>',
    get_block_wrapper_attributes(['class' => 'hive-block hive-newsletter']), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress escapes the wrapper's attributes.
    esc_html($hive_title),
    '' !== $hive_text ? '<p class="hive-newsletter__text">' . esc_html($hive_text) . '</p>' : '',
    esc_url(Newsletter::endpoint()),
    esc_attr($hive_uid),
    esc_attr($hive_placeholder),
    esc_html($hive_button),
    $hive_alerts, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
    '' !== $hive_disclaimer ? '<p class="hive-newsletter__disclaimer">' . esc_html($hive_disclaimer) . '</p>' : ''
);
