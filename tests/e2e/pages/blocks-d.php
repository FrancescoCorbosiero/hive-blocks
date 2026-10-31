<?php
/**
 * E2E page for the social and utility blocks (tests/e2e/blocks-d.spec.mjs).
 * Default attributes, except where the default prints nothing: the social
 * links and the shortcode, set as on the store's homepage. Two sections the
 * Store Hub switched off ('yes' as golden-hive-blocks wrote it, or true)
 * must print nothing. No backslashes in here: wp_insert_post() unslashes the
 * content, hence the shortcode's unquoted attributes.
 */

return [
    'blocchi-d' => [
        'Blocchi D',
        '<!-- wp:hive/social-buttons {"instagram":"https://www.instagram.com/resellpiacenza/","tiktok":"https://www.tiktok.com/@resellpiacenza"} /-->'
        . '<!-- wp:hive/whatsapp-button /-->'
        . '<!-- wp:hive/instagram-feed /-->'
        . '<!-- wp:hive/size-chart-modal /-->'
        . '<!-- wp:hive/shortcode-wrapper {"eyebrow":"Selezione","title":"Novità","shortcode":"[hive_product_rail type=recent limit=4]","buttonText":"Esplora il negozio","buttonUrl":"/?post_type=product"} /-->'
        . '<!-- wp:hive/shortcode-wrapper {"title":"Sezione spenta","shortcode":"[hive_product_rail type=recent limit=4]","hidden":"yes"} /-->'
        . '<!-- wp:hive/shortcode-wrapper {"title":"Sezione spenta dallo Store Hub","shortcode":"[hive_product_rail type=recent limit=4]","hidden":true} /-->',
    ],
];
