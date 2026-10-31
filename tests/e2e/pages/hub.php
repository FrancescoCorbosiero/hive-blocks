<?php
/**
 * The Store Hub's page (tests/e2e/hub.spec.mjs): one section as the homepage
 * holds them, a self-closing hive/shortcode-wrapper with the rail in its
 * shortcode attribute. The rail's attributes are unquoted on purpose: quotes
 * would need a backslash escape in the block's JSON, and wp_insert_post()
 * strips backslashes from the content it is given.
 */

return [
    'vetrina' => ['Vetrina', '<!-- wp:hive/shortcode-wrapper {"title":"Novità","shortcode":"[hive_product_rail type=recent limit=4]"} /-->'],
];
