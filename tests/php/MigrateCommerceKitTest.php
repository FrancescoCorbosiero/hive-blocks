<?php

// Reading CommerceKit's data: which columns of a waitlist table matter,
// which order bumps its settings hold, which post types are size guides.

use Hive\Core\Migrate\CommerceKit;

check(
    CommerceKit::waitlist_columns(['id', 'email', 'product_id', 'created', 'mail_sent']),
    ['email' => 'email', 'products' => ['product_id'], 'sent' => 'mail_sent'],
    'a waitlist table: email, product, sent'
);
check(
    CommerceKit::waitlist_columns(['ID', 'User_Email', 'product_id', 'parent_id', 'variation_id']),
    ['email' => 'User_Email', 'products' => ['variation_id', 'product_id', 'parent_id'], 'sent' => null],
    'the variation, then the product, then the parent; names as they are written'
);
check(CommerceKit::waitlist_columns(['id', 'product_id', 'created']), null, 'no email column: not a waitlist');
check(CommerceKit::waitlist_columns(['id', 'email', 'created']), null, 'no product column: not a waitlist');

check(CommerceKit::already_sent('1', 'mail_sent'), true, 'mail_sent 1');
check(CommerceKit::already_sent('0', 'mail_sent'), false, 'mail_sent 0');
check(CommerceKit::already_sent(null, 'sent_at'), false, 'sent_at empty');
check(CommerceKit::already_sent('0000-00-00 00:00:00', 'sent_at'), false, 'sent_at zero date');
check(CommerceKit::already_sent('2026-05-01 10:00:00', 'sent_at'), true, 'sent_at a date');
check(CommerceKit::already_sent('pending', 'status'), false, 'status pending');
check(CommerceKit::already_sent('Sent', 'status'), true, 'status sent');

check(
    CommerceKit::bumps(['order_bump' => 1, 'order_bump_product' => '812', 'order_bump_title' => 'Aggiungi anche', 'order_bump_button_text' => 'Aggiungi', 'order_bump_description' => 'Spray impermeabilizzante', 'sticky_atc' => 1]),
    [['product' => 812, 'headline' => 'Aggiungi anche', 'text' => 'Spray impermeabilizzante', 'button' => 'Aggiungi']],
    'one bump, single values'
);
check(
    CommerceKit::bumps(['multiple_obp' => 1, 'multiple_obp_product' => ['812', '0', '815'], 'multiple_obp_title' => ['Calzini', 'Niente', 'Spray']]),
    [['product' => 812, 'headline' => 'Calzini', 'text' => '', 'button' => ''], ['product' => 815, 'headline' => 'Spray', 'text' => '', 'button' => '']],
    'several bumps as lists, an empty product skipped'
);
check(CommerceKit::bumps(['ajax_search' => 1, 'waitlist' => 1]), [], 'no bump settings');

check(CommerceKit::guide_types(['post', 'ckit_size_guide', 'hive_size_guide', 'product', 'size_chart']), ['ckit_size_guide', 'size_chart'], 'size-guide post types, not Hive’s own');
check(CommerceKit::is_commercekit('wp_commercekit_waitlist'), true, 'a CommerceKit table');
check(CommerceKit::is_commercekit('commercekit_attribute_swatches'), true, 'a CommerceKit meta key');
check(CommerceKit::is_commercekit('wp_woocommerce_sessions'), false, 'not CommerceKit');
