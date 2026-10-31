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
];
