<?php
/**
 * Rail di prodotti — server render, through Hive\Shop\Rail (the same as the
 * [hive_product_rail] shortcode).
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Shop\Rail;

defined('ABSPATH') || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rail::render() escapes what it prints.
echo Rail::render($attributes, static fn(array $extra): string => get_block_wrapper_attributes($extra));
