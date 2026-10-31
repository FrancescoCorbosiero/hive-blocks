<?php
/**
 * The migration's e2e helpers (tests/e2e/migration.spec.mjs): a store as
 * golden-hive-blocks and CommerceKit left it, and what became of it.
 *
 *   POST /hive-e2e/v1/legacy   writes the old data (once)
 *   GET  /hive-e2e/v1/legacy   what the migration made of it
 */

defined('ABSPATH') || exit;

add_action(
    'rest_api_init',
    static function (): void {
        register_rest_route(
            'hive-e2e/v1',
            '/legacy',
            [
                'methods'             => 'POST',
                'permission_callback' => '__return_true',
                'callback'            => 'hive_e2e_legacy_seed',
            ]
        );
        register_rest_route(
            'hive-e2e/v1',
            '/legacy',
            [
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => 'hive_e2e_legacy_state',
            ]
        );
    }
);

function hive_e2e_legacy_seed(): array
{
    global $wpdb;
    $ids = get_option('hive_e2e_legacy');
    if (is_array($ids)) {
        return $ids;
    }
    wp_set_current_user(1);

    // A page built with golden-hive-blocks: a block, the old shortcodes, a block Hive doesn't have.
    $page = wp_insert_post(
        [
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_name'    => 'golden-hive',
            'post_title'   => 'Pagina golden-hive',
            'post_content' => "<!-- wp:golden-hive/store-hours {\"title\":\"Orari di prima\"} /-->\n\n"
                . "<!-- wp:shortcode -->\n[bestsellers limit=\"4\" title=\"I più venduti\"]\n<!-- /wp:shortcode -->\n\n"
                . "<!-- wp:paragraph -->\n<p>Filtri: [gh_filters]</p>\n<!-- /wp:paragraph -->\n\n"
                . "<!-- wp:golden-hive/old-thing /-->",
        ]
    );

    // A drop not out yet, on a draft (it stays out of the shop's lists), with a size to wait for.
    $drop = new WC_Product_Variable();
    $drop->set_name('Vecchio drop');
    $drop->set_status('draft');
    $size = new WC_Product_Attribute();
    $size->set_id(wc_attribute_taxonomy_id_by_name('taglia'));
    $size->set_name('pa_taglia');
    $size->set_options([(int) get_term_by('slug', '41', 'pa_taglia')->term_id]);
    $size->set_visible(true);
    $size->set_variation(true);
    $drop->set_attributes([$size]);
    $drop->update_meta_data('_ghb_drop_ts', time() + 3 * DAY_IN_SECONDS);
    $drop->update_meta_data('_ghb_drop_lock', 'yes');
    $drop_id   = $drop->save();
    $variation = new WC_Product_Variation();
    $variation->set_parent_id($drop_id);
    $variation->set_attributes(['pa_taglia' => '41']);
    $variation->set_regular_price('200');
    $variation->set_stock_status('outofstock');
    $variation_id = $variation->save();

    // A newsletter subscriber with confirmed size alerts.
    $person = wp_insert_post(['post_type' => 'ghb_subscriber', 'post_status' => 'private', 'post_title' => 'legacy@example.com']);
    foreach (['_ghb_email' => 'legacy@example.com', '_ghb_alerts' => 'on', '_ghb_sizes' => ['42'], '_ghb_token' => 'LegacyToken123', '_ghb_confirmed_at' => time() - DAY_IN_SECONDS] as $key => $value) {
        update_post_meta($person, $key, $value);
    }

    // golden-hive-blocks' settings: one Hive has at its default, one the store already set in Hive.
    update_option('ghb_design_shape', 'sharp');
    update_option('ghb_whatsapp_number', '390000000000');

    // CommerceKit: its waitlist table, a size guide, an order bump in its settings.
    $table = $wpdb->prefix . 'commercekit_waitlist';
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$table} (id bigint(20) NOT NULL AUTO_INCREMENT, email varchar(200) NOT NULL, product_id bigint(20) NOT NULL, created datetime NULL, mail_sent tinyint(1) NOT NULL DEFAULT 0, PRIMARY KEY (id))"); // phpcs:ignore
    foreach ([['wait@example.com', $variation_id, 0], ['told@example.com', $variation_id, 1], ['wait@example.com', 999999, 0]] as [$email, $product, $sent]) {
        $wpdb->insert($table, ['email' => $email, 'product_id' => $product, 'created' => current_time('mysql'), 'mail_sent' => $sent]); // phpcs:ignore
    }
    $guide = wp_insert_post(
        [
            'post_type'    => 'ckit_size_guide',
            'post_status'  => 'publish',
            'post_title'   => 'Guida Nike',
            'post_content' => '<table><tr><th>EU</th><th>US</th></tr><tr><td>42</td><td>8,5</td></tr></table>',
            'meta_input'   => ['ckit_size_guide_cats' => [1]],
        ]
    );
    $socks = get_page_by_path('calzini-hive', OBJECT, 'product');
    update_option('commercekit', ['order_bump' => 1, 'order_bump_product' => $socks ? $socks->ID : 0, 'order_bump_title' => 'Aggiungi i calzini']);

    $ids = ['page' => $page, 'drop' => $drop_id, 'variation' => $variation_id, 'person' => $person, 'guide' => $guide, 'socks' => $socks ? $socks->ID : 0];
    update_option('hive_e2e_legacy', $ids, false);
    return $ids;
}

function hive_e2e_legacy_state(): array
{
    $ids     = (array) get_option('hive_e2e_legacy', []);
    $drop    = (int) ($ids['drop'] ?? 0);
    $drafts  = static fn(string $type) => array_map(
        static fn(WP_Post $post) => ['title' => $post->post_title, 'status' => $post->post_status, 'from' => get_post_meta($post->ID, '_hive_imported_from', true), 'product' => (int) get_post_meta($post->ID, '_hive_bump_product', true)],
        get_posts(['post_type' => $type, 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_hive_imported_from']) // phpcs:ignore
    );
    $settings = (array) get_option('hive_settings', []);
    return [
        'ids'      => $ids,
        'content'  => (string) get_post_field('post_content', (int) ($ids['page'] ?? 0)),
        'drop'     => [
            'ts'        => (int) get_post_meta($drop, '_hive_drop_ts', true),
            'lock'      => (string) get_post_meta($drop, '_hive_drop_lock', true),
            'scheduled' => function_exists('as_next_scheduled_action') && (bool) as_next_scheduled_action(Hive\Shop\Drops::HOOK, ['product_id' => $drop], 'hive'),
        ],
        'person'   => get_post_type((int) ($ids['person'] ?? 0)),
        'guides'   => $drafts('hive_size_guide'),
        'bumps'    => $drafts('hive_bump'),
        'settings' => ['shape' => $settings['shape'] ?? null, 'whatsapp' => $settings['whatsapp'] ?? null],
    ];
}
