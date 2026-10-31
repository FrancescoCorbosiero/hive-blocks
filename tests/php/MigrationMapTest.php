<?php

// The migration's block map (Hive\Migration::block_map(), its WordPress
// parts not called here): every golden-hive-blocks block has an entry, and
// every entry points at a block Hive registers. golden-hive-blocks' block
// names are listed here, so a block can't be forgotten.

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value): mixed
    {
        return $value;
    }
}
require_once dirname(__DIR__, 2) . '/src/Migration.php';

const GOLDEN_HIVE_BLOCKS = [
    'about-hero', 'authenticity-guarantee', 'brand-marquee', 'category-slider', 'contact-info', 'cta-banner', 'cta-image',
    'drop-calendar', 'drop-countdown', 'faq-schema', 'hero-carousel', 'hero-split', 'hero-video', 'instagram-feed',
    'legit-check', 'map-embed', 'newsletter', 'parallax-section', 'product-spotlight', 'promo-modal', 'shortcode-wrapper',
    'size-chart-modal', 'social-buttons', 'social-proof', 'store-hours', 'store-visit', 'trust-badges', 'trustpilot-widget',
    'whatsapp-button',
];

$hive_map = Hive\Migration::block_map();
check(
    array_values(array_filter(GOLDEN_HIVE_BLOCKS, static fn(string $name): bool => !isset($hive_map['golden-hive/' . $name]))),
    [],
    'every golden-hive-blocks block has a map entry'
);
$hive_registered = array_map(static fn(string $file): string => (string) json_decode((string) file_get_contents($file), true)['name'], glob(dirname(__DIR__, 2) . '/blocks/*/block.json') ?: []);
check(
    array_values(array_unique(array_diff(array_map(static fn(array $entry): string => (string) ($entry['name'] ?? ''), $hive_map), $hive_registered))),
    [],
    'every entry points at a block Hive registers'
);
check(
    array_values(array_filter(array_keys(Hive\Migration::shortcode_map()), static fn(string $tag): bool => 1 !== preg_match('/^[a-z0-9_]+$/', $tag))),
    [],
    'shortcode tags are plain names'
);
