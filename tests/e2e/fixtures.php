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

/** A plain-colour PNG, written without an image library (the product photos). */
function hive_e2e_png(string $file, array $rgb, int $size = 600): void
{
    $row   = "\0" . str_repeat(chr($rgb[0]) . chr($rgb[1]) . chr($rgb[2]), $size);
    $chunk = static fn(string $type, string $body): string => pack('N', strlen($body)) . $type . $body . pack('N', crc32($type . $body));
    file_put_contents($file, "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 2, 0, 0, 0)) . $chunk('IDAT', (string) gzcompress(str_repeat($row, $size), 9)) . $chunk('IEND', ''));
}

require_once ABSPATH . 'wp-admin/includes/image.php';
$hive_upload = wp_upload_dir();
$hive_photos = [];
foreach ([[236, 236, 236], [29, 78, 216], [185, 28, 28]] as $hive_index => $hive_rgb) {
    $hive_file = $hive_upload['path'] . '/hive-dunk-' . $hive_index . '.png';
    hive_e2e_png($hive_file, $hive_rgb);
    $hive_photo = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'Dunk ' . $hive_index, 'post_status' => 'inherit'], $hive_file);
    wp_update_attachment_metadata($hive_photo, wp_generate_attachment_metadata($hive_photo, $hive_file));
    $hive_photos[] = $hive_photo;
}

// A pair in three sizes and three photos: 40 on sale with 2 left, 41 sold out, 42 at full price.
$hive_dunk = new WC_Product_Variable();
$hive_dunk->set_name('Nike Dunk Low Panda');
$hive_dunk->set_slug('nike-dunk-low-panda');
$hive_dunk->set_status('publish');
$hive_dunk->set_category_ids([$hive_category]);
$hive_dunk->set_short_description('La Dunk più amata.');
$hive_dunk->set_description(str_repeat('<p>La Dunk Low nasce sui campi da basket nel 1985 e diventa un’icona dello skate: pelle liscia bianca e nera, suola in gomma, il profilo basso che si porta con tutto.</p>', 8));
$hive_dunk->set_image_id($hive_photos[0]);
$hive_dunk->set_gallery_image_ids([$hive_photos[1], $hive_photos[2]]);
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
    if ('40' === $hive_size) {
        $hive_variation->set_manage_stock(true);
        $hive_variation->set_stock_quantity(2);
    }
    $hive_variation->set_status('publish');
    $hive_variation->save();
}
WC_Product_Variable::sync($hive_dunk_id);

// A size guide for the Sneakers category.
$hive_guide = wp_insert_post(
    [
        'post_type'    => 'hive_size_guide',
        'post_status'  => 'publish',
        'post_title'   => 'Guida taglie sneakers',
        'post_content' => '<!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th>EU</th><th>US</th><th>CM</th></tr></thead><tbody><tr><td>40</td><td>7</td><td>25</td></tr><tr><td>41</td><td>8</td><td>26</td></tr><tr><td>42</td><td>8,5</td><td>26,5</td></tr></tbody></table></figure><!-- /wp:table -->',
    ]
);
update_post_meta($hive_guide, '_hive_guide_cats', [$hive_category]);
delete_transient('hive_size_guides');

// The model attribute (pa_modello): the Panda's other colorway.
$hive_model_attribute = wc_attribute_taxonomy_id_by_name('modello') ?: wc_create_attribute(['name' => 'Modello', 'slug' => 'modello', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false]);
if (!taxonomy_exists('pa_modello')) {
    register_taxonomy('pa_modello', ['product'], ['hierarchical' => false, 'public' => false, 'rewrite' => false]);
}
$hive_model      = term_exists('Dunk Low', 'pa_modello') ?: wp_insert_term('Dunk Low', 'pa_modello');
$hive_model_attr = new WC_Product_Attribute();
$hive_model_attr->set_id($hive_model_attribute);
$hive_model_attr->set_name('pa_modello');
$hive_model_attr->set_options([(int) $hive_model['term_id']]);
$hive_model_attr->set_visible(true);
$hive_model_attr->set_variation(false);
$hive_dunk = wc_get_product($hive_dunk_id);
$hive_dunk->set_attributes(array_merge($hive_dunk->get_attributes(), ['pa_modello' => $hive_model_attr]));
$hive_dunk->save();

$hive_fog = new WC_Product_Simple();
$hive_fog->set_name('Nike Dunk Low Grey Fog');
$hive_fog->set_slug('nike-dunk-low-grey-fog');
$hive_fog->set_status('publish');
$hive_fog->set_category_ids([$hive_category]);
$hive_fog->set_regular_price('170');
$hive_fog->set_attributes(['pa_modello' => $hive_model_attr]);
$hive_fog->save();

// A drop a week away, not for sale until then.
$hive_drop = new WC_Product_Simple();
$hive_drop->set_name('Jordan 4 in arrivo');
$hive_drop->set_slug('jordan-4-in-arrivo');
$hive_drop->set_status('publish');
$hive_drop->set_regular_price('250');
$hive_drop->update_meta_data('_hive_drop_ts', time() + 7 * DAY_IN_SECONDS);
$hive_drop->update_meta_data('_hive_drop_lock', 'yes');
$hive_drop->save();

// Settings the product page's extras need: a WhatsApp number, the delivery estimate.
update_option('hive_settings', array_merge((array) get_option('hive_settings', []), ['whatsapp' => '393331234567', 'delivery' => true]));

// A simple product, and a sold-out one.
$hive_simple_ids = [];
foreach ([['Calzini Hive', 'calzini-hive', '15', 'instock'], ['Felpa esaurita', 'felpa-esaurita', '89', 'outofstock']] as [$hive_name, $hive_slug, $hive_price, $hive_stock]) {
    $hive_simple = new WC_Product_Simple();
    $hive_simple->set_name($hive_name);
    $hive_simple->set_slug($hive_slug);
    $hive_simple->set_status('publish');
    $hive_simple->set_category_ids([$hive_category]);
    $hive_simple->set_regular_price($hive_price);
    $hive_simple->set_stock_status($hive_stock);
    $hive_simple_ids[$hive_slug] = $hive_simple->save();
}

// The checkout: the classic one (as on the store), bank transfer with an account, an order bump
// offering the socks to carts with sneakers.
wp_update_post(['ID' => wc_get_page_id('checkout'), 'post_content' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->']);
update_option('woocommerce_bacs_settings', ['enabled' => 'yes', 'title' => 'Bonifico bancario', 'description' => 'Paga con un bonifico.', 'instructions' => '']);
update_option('woocommerce_bacs_accounts', [['account_name' => 'ResellPiacenza', 'account_number' => '', 'bank_name' => 'Banca di Piacenza', 'sort_code' => '', 'iban' => 'IT60X0542811101000000123456', 'bic' => 'BCITITMM']]);
wp_insert_post(
    [
        'post_type'   => 'hive_bump',
        'post_status' => 'publish',
        'post_title'  => 'Calzini con le sneakers',
        'meta_input'  => [
            '_hive_bump_product'  => $hive_simple_ids['calzini-hive'],
            '_hive_bump_cats'     => [$hive_category],
            '_hive_bump_headline' => 'Aggiungi anche',
            '_hive_bump_text'     => 'Il paio giusto per le tue sneakers.',
        ],
    ]
);
delete_transient('hive_bumps');

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
