<?php

// Firing a WooCommerce hook on Hive's own page: without WooCommerce's defaults Hive draws itself, without the theme.

use Hive\Core\HookFilter;

final class HiveHookFilterFixture
{
    public static function structured(): void
    {
    }
    public function method(): void
    {
    }
}

$hive_callbacks = [
    5  => ['woocommerce_template_single_title' => ['function' => 'woocommerce_template_single_title', 'accepted_args' => 1]],
    10 => [
        'woocommerce_template_single_price' => ['function' => 'woocommerce_template_single_price', 'accepted_args' => 1],
        'theme_badge'                       => ['function' => 'theme_badge', 'accepted_args' => 1],
    ],
    25 => ['plugin_countdown' => ['function' => 'plugin_countdown', 'accepted_args' => 1]],
    60 => ['abc123generate' => ['function' => [new HiveHookFilterFixture(), 'method'], 'accepted_args' => 1]],
];
$hive_files = static fn($function): string => HookFilter::name($function) === 'theme_badge' ? '/www/wp-content/themes/shop/inc/badge.php' : '/www/wp-content/plugins/x/x.php';
$hive_skip  = ['woocommerce_template_single_title', 'woocommerce_template_single_price'];
$hive_dirs  = ['/www/wp-content/themes/shop/'];

check(
    HookFilter::excluded($hive_callbacks, $hive_skip, $hive_files, $hive_dirs),
    [[5, 'woocommerce_template_single_title'], [10, 'woocommerce_template_single_price'], [10, 'theme_badge']],
    "WooCommerce's defaults and the theme's callbacks are left out; plugins stay"
);
check(
    HookFilter::excluded($hive_callbacks, [], $hive_files, [], 30),
    [[5, 'woocommerce_template_single_title'], [10, 'woocommerce_template_single_price'], [10, 'theme_badge'], [25, 'plugin_countdown']],
    'from priority 30 on: the part after the add-to-cart form'
);
check(
    HookFilter::excluded($hive_callbacks, $hive_skip, $hive_files, $hive_dirs, null, 29),
    [[5, 'woocommerce_template_single_title'], [10, 'woocommerce_template_single_price'], [10, 'theme_badge'], [60, 'abc123generate']],
    'up to priority 29: the part before the form'
);
check(HookFilter::name('\\my_function'), 'my_function', 'a function');
check(HookFilter::name(['HiveHookFilterFixture', 'structured']), 'HiveHookFilterFixture::structured', 'a static method');
check(HookFilter::name([new HiveHookFilterFixture(), 'method']), 'HiveHookFilterFixture::method', 'an object method');
check(HookFilter::name(static function (): void {}), '', 'a closure has no name');
check(HookFilter::excluded([10 => ['x' => ['function' => 'x']]], [], static fn(): string => '', ['/themes/']), [], 'an unknown file is not the theme');
