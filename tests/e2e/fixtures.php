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

// slug => [title, content, page template on classic themes ('' for the default, with the sidebar)]
$hive_pages = [
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
