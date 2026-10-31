<?php
/**
 * E2E fixture page for the store and trust blocks (tests/e2e/blocks-b.spec.mjs),
 * each with its default attributes. See tests/e2e/fixtures.php.
 */

return [
    'blocchi-b' => [
        'Blocchi B',
        '<!-- wp:hive/contact-info /-->'
            . "\n\n" . '<!-- wp:hive/store-visit /-->'
            . "\n\n" . '<!-- wp:hive/map-embed /-->'
            . "\n\n" . '<!-- wp:hive/trust-badges /-->'
            . "\n\n" . '<!-- wp:hive/authenticity-guarantee /-->'
            . "\n\n" . '<!-- wp:hive/legit-check /-->'
            . "\n\n" . '<!-- wp:hive/faq-schema /-->'
            . "\n\n" . '<!-- wp:hive/trustpilot-widget /-->',
    ],
];
