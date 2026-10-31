<?php
/**
 * E2E fixtures, created once when the e2e WordPress boots (tests/e2e/serve.mjs).
 * The specs reach the pages by slug (?pagename=…), whatever the permalinks.
 */

wp_set_current_user(1);

// WooCommerce: no setup wizard, no redirect to it on the first admin page.
delete_transient('_wc_activation_redirect');
update_option('woocommerce_onboarding_profile', ['skipped' => true]);

$hive_classic = !wp_is_block_theme();

// Prices in euro, written the Italian way, as on the store.
update_option('woocommerce_currency', 'EUR');
update_option('woocommerce_currency_pos', 'right_space');
update_option('woocommerce_price_thousand_sep', '.');
update_option('woocommerce_price_decimal_sep', ',');
update_option('woocommerce_price_num_decimals', '2');

// The size attribute (pa_taglia), registered for this request too: WooCommerce does it on the next one.
$hive_attribute = wc_attribute_taxonomy_id_by_name('taglia') ?: wc_create_attribute(['name' => 'Taglia', 'slug' => 'taglia', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false]);
if (!taxonomy_exists('pa_taglia')) {
    register_taxonomy('pa_taglia', ['product', 'product_variation'], ['hierarchical' => false, 'public' => false, 'rewrite' => false]);
}
$hive_sizes = [];
foreach (['40', '41', '42'] as $hive_size) {
    $hive_term                = term_exists($hive_size, 'pa_taglia') ?: wp_insert_term($hive_size, 'pa_taglia');
    $hive_sizes[$hive_size] = (int) $hive_term['term_id'];
}
$hive_category = wp_insert_term('Sneakers', 'product_cat');
$hive_category = is_wp_error($hive_category) ? 0 : (int) $hive_category['term_id'];

// A pair in three sizes: 40 on sale, 41 sold out, 42 at full price.
$hive_dunk = new WC_Product_Variable();
$hive_dunk->set_name('Nike Dunk Low Panda');
$hive_dunk->set_slug('nike-dunk-low-panda');
$hive_dunk->set_status('publish');
$hive_dunk->set_category_ids([$hive_category]);
$hive_dunk->set_short_description('La Dunk più amata.');
$hive_size_attribute = new WC_Product_Attribute();
$hive_size_attribute->set_id($hive_attribute);
$hive_size_attribute->set_name('pa_taglia');
$hive_size_attribute->set_options(array_values($hive_sizes));
$hive_size_attribute->set_visible(true);
$hive_size_attribute->set_variation(true);
$hive_dunk->set_attributes([$hive_size_attribute]);
$hive_dunk_id = $hive_dunk->save();
foreach ([['40', '180', '150', 'instock'], ['41', '160', '', 'outofstock'], ['42', '180', '', 'instock']] as [$hive_size, $hive_regular, $hive_sale, $hive_stock]) {
    $hive_variation = new WC_Product_Variation();
    $hive_variation->set_parent_id($hive_dunk_id);
    $hive_variation->set_attributes(['pa_taglia' => $hive_size]);
    $hive_variation->set_regular_price($hive_regular);
    $hive_variation->set_sale_price($hive_sale);
    $hive_variation->set_stock_status($hive_stock);
    $hive_variation->set_status('publish');
    $hive_variation->save();
}
WC_Product_Variable::sync($hive_dunk_id);

// A simple product, and a sold-out one.
foreach ([['Calzini Hive', 'calzini-hive', '15', 'instock'], ['Felpa esaurita', 'felpa-esaurita', '89', 'outofstock']] as [$hive_name, $hive_slug, $hive_price, $hive_stock]) {
    $hive_simple = new WC_Product_Simple();
    $hive_simple->set_name($hive_name);
    $hive_simple->set_slug($hive_slug);
    $hive_simple->set_status('publish');
    $hive_simple->set_category_ids([$hive_category]);
    $hive_simple->set_regular_price($hive_price);
    $hive_simple->set_stock_status($hive_stock);
    $hive_simple->save();
}

// slug => [title, content, page template on classic themes ('' for the default, with the sidebar)]
$hive_pages = [
    'rail'        => ['Rail', '<!-- wp:hive/product-rail {"title":"Novità"} /-->', 'template-fullwidth.php'],
    'orari'       => ['Orari', '<!-- wp:hive/store-hours /-->', 'template-fullwidth.php'],
    'senza-hive'  => ['Senza Hive', '<!-- wp:paragraph --><p>Una pagina senza blocchi Hive.</p><!-- /wp:paragraph -->', 'template-fullwidth.php'],
    'widget-hive' => ['Widget Hive', '<!-- wp:paragraph --><p>Il blocco Hive è solo nella barra laterale.</p><!-- /wp:paragraph -->', ''],
];

foreach ($hive_pages as $hive_slug => [$hive_title, $hive_content, $hive_template]) {
    $hive_id = wp_insert_post(
        [
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_name'    => $hive_slug,
            'post_title'   => $hive_title,
            'post_content' => $hive_content,
        ],
        true
    );
    // Full width (no sidebar) where the theme has it, so only widget-hive shows the widget below.
    if ($hive_classic && '' !== $hive_template && !is_wp_error($hive_id) && is_file(get_template_directory() . '/' . $hive_template)) {
        update_post_meta($hive_id, '_wp_page_template', $hive_template);
    }
}

// Classic themes: a Hive block in the first sidebar, rendered after <head>.
if ($hive_classic) {
    update_option('widget_block', [2 => ['content' => '<!-- wp:hive/store-hours {"title":"Orari (widget)"} /-->'], '_multiwidget' => 1]);
    $hive_sidebars              = (array) get_option('sidebars_widgets', []);
    $hive_sidebars['sidebar-1'] = ['block-2'];
    update_option('sidebars_widgets', $hive_sidebars);
}
