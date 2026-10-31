<?php
/**
 * Nella tua taglia — the frame and the size choice; the rail comes from the
 * browser (Hive\Shop\SizeRail).
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Shop\SizeRail;

defined('ABSPATH') || exit;

if (!class_exists('WooCommerce')) {
    return;
}

echo SizeRail::render($attributes, static fn(array $extra): string => get_block_wrapper_attributes($extra)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SizeRail::render() escapes what it prints.
