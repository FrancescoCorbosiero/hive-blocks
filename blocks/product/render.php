<?php
/**
 * Pagina prodotto — the product page in a block theme's template
 * (Hive\Modules\ProductPage puts it there; Hive\Shop\ProductPage draws it).
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Shop\ProductPage;

defined('ABSPATH') || exit;

if (!function_exists('is_product') || !is_product()) {
    return;
}

echo ProductPage::render(!empty($attributes['canvas'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ProductPage::render() escapes what it prints.
