<?php
/**
 * The start of a WooCommerce product list: Hive's grid instead of
 * ul.products (Hive\Modules\Cards). The columns follow WooCommerce's own
 * setting for the list.
 *
 * @package Hive
 */

use Hive\Assets;

defined('ABSPATH') || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a stylesheet tag WordPress builds escaped.
echo Assets::use_style('hive-shop');
printf('<ul class="hive-block hive-grid" style="--hive-grid-cols:%d" role="list">', (int) max(1, min(6, (int) wc_get_loop_prop('columns', 4))));
