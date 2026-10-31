<?php
/**
 * Cerca — the button that opens the live search (@hive/search; Hive\Shop\Search
 * answers). Without JavaScript it's a plain link to WooCommerce's search.
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Modules;

defined('ABSPATH') || exit;

if (!Modules::enabled('search')) {
    return;
}

$hive_look        = in_array($attributes['look'] ?? 'field', ['field', 'button', 'icon'], true) ? $attributes['look'] : 'field';
$hive_label       = trim((string) ($attributes['label'] ?? '')) ?: 'Cerca';
$hive_placeholder = trim((string) ($attributes['placeholder'] ?? '')) ?: 'Cerca sneakers, marchi, modelli…';
$hive_terms       = array_map('trim', explode(',', (string) ($attributes['popular'] ?? '')));
$hive_popular     = array_slice(array_values(array_filter($hive_terms, 'strlen')), 0, 8);

printf(
    '<div %1$s><a class="hive-search-block__open" href="%2$s" data-hive-search data-placeholder="%3$s" data-popular="%4$s" role="button" aria-haspopup="dialog"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="m20 20-4.35-4.35"/></svg><span class="%5$s">%6$s</span></a></div>',
    get_block_wrapper_attributes(['class' => 'hive-block hive-search-block is-' . $hive_look]), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress escapes the wrapper's attributes.
    esc_url(add_query_arg(['s' => '', 'post_type' => 'product'], home_url('/'))),
    esc_attr($hive_placeholder),
    esc_attr(implode('|', $hive_popular)),
    'icon' === $hive_look ? 'hive-visually-hidden' : 'hive-search-block__label',
    esc_html('field' === $hive_look ? $hive_placeholder : $hive_label)
);
