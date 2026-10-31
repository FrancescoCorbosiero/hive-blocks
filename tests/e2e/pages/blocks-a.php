<?php
/**
 * E2E fixture page for the heroes and calls to action: each block with its
 * default attributes, then the other layouts (see tests/e2e/blocks-a.spec.mjs).
 */

return [
    'blocchi-a' => [
        'Blocchi A',
        '<!-- wp:hive/hero /-->'
        . '<!-- wp:hive/hero-carousel /-->'
        . '<!-- wp:hive/cta /-->'
        . '<!-- wp:hive/hero {"layout":"split","tone":"dark","mediaSide":"right"} /-->'
        . '<!-- wp:hive/hero {"layout":"about","values":[{"title":"Passione","text":"Ogni giorno."}]} /-->'
        . '<!-- wp:hive/cta {"layout":"split","reverse":true} /-->',
    ],
];
