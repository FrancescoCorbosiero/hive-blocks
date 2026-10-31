<?php
/**
 * The store's own pages (pages/*.html, made for Hive from golden-hive-blocks'
 * pages/), on the e2e site as they will be on the store: they render with
 * Hive's blocks. Slashed, because wp_insert_post() unslashes and the blocks'
 * JSON keeps its escapes. See tests/e2e/store-pages.spec.mjs.
 */

$hive_store_pages = dirname(__DIR__, 3) . '/pages/';

return [
    'negozio-home'      => ['Home del negozio', wp_slash((string) file_get_contents($hive_store_pages . 'homepage.html'))],
    'negozio-chi-siamo' => ['Chi siamo', wp_slash((string) file_get_contents($hive_store_pages . 'chi-siamo.html'))],
    'negozio-contatti'  => ['Contatti', wp_slash((string) file_get_contents($hive_store_pages . 'contatti.html'))],
];
