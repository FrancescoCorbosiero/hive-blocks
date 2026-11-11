<?php
/**
 * E2E fixture page for the commerce and marketing blocks (step 7, batch C),
 * each with its default attributes: they draw themselves from the store in
 * tests/e2e/fixtures.php. See tests/e2e/blocks-c.spec.mjs.
 */

return [
    'blocchi-c' => [
        'Blocchi C',
        '<!-- wp:hive/category-slider /-->'
        . '<!-- wp:hive/brand-marquee /-->'
        . '<!-- wp:hive/product-spotlight /-->'
        . '<!-- wp:hive/drop-countdown /-->'
        . '<!-- wp:hive/drop-calendar /-->'
        . '<!-- wp:hive/social-proof /-->'
        . '<!-- wp:hive/promo-modal /-->',
    ],
    // Typed in the block, as on the store's homepage: tiles (picture by URL, name, link) and logos by URL.
    'blocchi-c-riquadri' => [
        'Blocchi C riquadri',
        '<!-- wp:hive/category-slider {"title":"","imageRatio":"9 / 16","items":[{"name":"Asics","image":"/wp-content/plugins/hive-blocks/tests/browser/fixtures/sneaker.svg","url":"/marchio/asics"},{"name":"Nocta","image":"/wp-content/plugins/hive-blocks/tests/browser/fixtures/sneaker-2.svg","url":"/marchio/nike/nike-nocta/"}]} /-->'
        . '<!-- wp:hive/brand-marquee {"title":"SHOP BY BRAND","brands":[{"name":"Nike","logo":"/wp-content/plugins/hive-blocks/tests/browser/fixtures/sneaker.svg","url":"/marchio/nike"},{"name":"Jordan","logo":"","url":"/marchio/jordan"}]} /-->',
    ],
];
