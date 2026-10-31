<?php

// Code snippets that rely on what the switch-over takes away.

use Hive\Core\Migrate\Snippets;

check(
    Snippets::concerns("add_action('init', function () { if (function_exists('ghb_size_alerts_signup')) { ghb_size_alerts_signup(1, 'a@b.it', [], []); } });"),
    ['usa golden-hive-blocks (ghb_size_alerts_signup)'],
    'a golden-hive-blocks function'
);
check(
    Snippets::concerns("remove_action('wp_footer', 'commercekit_sticky_atc'); add_filter('ckit_waitlist_text', fn() => 'x');"),
    ['usa CommerceKit (commercekit_sticky_atc, ckit_waitlist_text)'],
    'CommerceKit’s functions and filters'
);
check(
    Snippets::concerns("add_action( 'woocommerce_after_shop_loop_item_title', 'my_badge', 5 );\nadd_action(\"woocommerce_before_shop_loop\", 'x');"),
    ['aggiunge alle liste prodotti di WooCommerce (woocommerce_after_shop_loop_item_title, woocommerce_before_shop_loop), che le schede e le pagine del negozio di Hive non usano'],
    'WooCommerce’s product-list hooks'
);
check(Snippets::concerns("add_filter('woocommerce_single_product_summary', 'x'); echo do_shortcode('[hive_product_rail]');"), [], 'nothing that goes');
check(Snippets::concerns("// the golden hour\n\$ghost = 1;"), [], 'look-alike words are not names');
