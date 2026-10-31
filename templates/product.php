<?php
/**
 * The product page on a classic theme: the theme's header and footer around
 * Hive's page (Hive\Modules\ProductPage).
 *
 * @package Hive
 */

use Hive\Shop\ProductPage;

defined('ABSPATH') || exit;

get_header('shop');

while (have_posts()) {
    the_post();
    echo ProductPage::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ProductPage::render() escapes what it prints.
}

get_footer('shop');
