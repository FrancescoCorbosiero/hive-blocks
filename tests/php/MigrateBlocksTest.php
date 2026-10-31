<?php

// The migration's block and shortcode rewrite.

use Hive\Core\Migrate\Blocks;
use Hive\Core\Migrate\Shortcodes;

$hive_shortcodes = ['gh_product_rail' => 'hive_product_rail', 'gh_bank_details' => 'hive_bank_details', 'bfl_filters' => ''];

check(Shortcodes::rename('[gh_product_rail category="saldi" pin="1,2"]', $hive_shortcodes), '[hive_product_rail category="saldi" pin="1,2"]', 'a shortcode renamed, its attributes kept');
check(Shortcodes::rename('<p>[gh_bank_details]</p>', $hive_shortcodes), '<p>[hive_bank_details]</p>', 'inside HTML');
check(Shortcodes::rename('[gh_product_rails x] [gh_product_rail]', $hive_shortcodes), '[gh_product_rails x] [hive_product_rail]', 'only the whole tag');
check(Shortcodes::rename('a [bfl_filters] b', $hive_shortcodes), 'a  b', 'a shortcode with no Hive counterpart goes');
check(Shortcodes::rename('a [bfl_filters x="1"]inside[/bfl_filters] b', $hive_shortcodes), 'a  b', 'with what it wrapped');
check(Shortcodes::tags_in('x [gh_product_rail] [bfl_filters]', $hive_shortcodes), ['gh_product_rail', 'bfl_filters'], 'which old tags a text has');

$hive_map = [
    'golden-hive/hero-split'          => ['name' => 'hive/hero', 'set' => ['layout' => 'split'], 'attrs' => ['subtitle' => 'text'], 'drop' => ['theme']],
    'golden-hive/shortcode-wrapper'   => ['name' => 'hive/shortcode-wrapper'],
    'golden-hive/parallax-section'    => ['name' => 'hive/hero', 'set' => ['layout' => 'cover'], 'map' => ['height' => ['tall' => 'full']]],
    'golden-hive/gone'                => ['remove' => true],
];
$hive_blocks = [
    ['blockName' => 'golden-hive/hero-split', 'attrs' => ['title' => 'Drop', 'subtitle' => 'Ogni venerdì', 'theme' => 'dark'], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []],
    [
        'blockName'    => 'core/group',
        'attrs'        => [],
        'innerBlocks'  => [
            ['blockName' => 'golden-hive/shortcode-wrapper', 'attrs' => ['title' => 'Saldi', 'shortcode' => '[gh_product_rail category="saldi"]'], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []],
            ['blockName' => 'golden-hive/gone', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []],
        ],
        'innerHTML'    => '<div class="wp-block-group"></div>',
        'innerContent' => ['<div class="wp-block-group">', null, null, '</div>'],
    ],
    ['blockName' => 'golden-hive/parallax-section', 'attrs' => ['height' => 'tall'], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []],
    ['blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>[gh_bank_details]</p>', 'innerContent' => ['<p>[gh_bank_details]</p>']],
];
$hive_count = [];
$hive_out   = Blocks::rewrite($hive_blocks, $hive_map, $hive_shortcodes, $hive_count);

check([$hive_out[0]['blockName'], $hive_out[0]['attrs']], ['hive/hero', ['title' => 'Drop', 'text' => 'Ogni venerdì', 'layout' => 'split']], 'renamed, an attribute renamed, one dropped, the layout set');
check($hive_out[1]['innerBlocks'][0]['blockName'], 'hive/shortcode-wrapper', 'inner blocks rewritten');
check($hive_out[1]['innerBlocks'][0]['attrs']['shortcode'], '[hive_product_rail category="saldi"]', 'the shortcode in an attribute renamed');
check(count($hive_out[1]['innerBlocks']), 1, 'a block with no counterpart removed');
check($hive_out[2]['attrs'], ['height' => 'full', 'layout' => 'cover'], 'a value mapped');
check($hive_out[3]['innerContent'], ['<p>[hive_bank_details]</p>'], 'shortcodes in saved HTML renamed');
check($hive_count, ['golden-hive/hero-split' => 1, 'golden-hive/shortcode-wrapper' => 1, 'golden-hive/gone' => 1, 'golden-hive/parallax-section' => 1], 'what was rewritten, counted');
check(Blocks::names_in('<!-- wp:golden-hive/hero-split {"a":1} /--><!-- wp:core/paragraph --><!-- wp:golden-hive/hero-split /-->'), ['golden-hive/hero-split'], 'the old blocks in a content');
