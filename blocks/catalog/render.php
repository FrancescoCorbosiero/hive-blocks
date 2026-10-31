<?php
/**
 * Pagina del negozio — the shop's page in a block theme's template
 * (Hive\Modules\Catalog puts it there; Hive\Shop\Catalog draws it).
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Shop\Catalog;

defined('ABSPATH') || exit;

if (!function_exists('is_shop') || !(is_shop() || is_product_taxonomy())) {
    return;
}

echo Catalog::render(!empty($attributes['canvas'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Catalog::render() escapes what it prints.
