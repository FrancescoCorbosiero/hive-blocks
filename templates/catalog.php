<?php
/**
 * The shop's pages on a classic theme: the theme's header and footer around
 * Hive's catalog (Hive\Modules\Catalog).
 *
 * @package Hive
 */

use Hive\Shop\Catalog;

defined('ABSPATH') || exit;

get_header('shop');

echo Catalog::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Catalog::render() escapes what it prints.

get_footer('shop');
