<?php

use Hive\Core\BlockScan;

$block = static fn(?string $name, array $attrs = [], array $inner = []): array => [
    'blockName'   => $name,
    'attrs'       => $attrs,
    'innerBlocks' => $inner,
];

$content = [
    $block('core/group', [], [$block('hive/store-hours'), $block(null)]),
    $block('core/block', ['ref' => 7]),
    $block('core/block', ['ref' => 9]),
    $block('hive/store-hours'),
];
$patterns = [
    7 => [$block('hive/cta')],
    9 => [$block('core/block', ['ref' => 9])], // a pattern that includes itself
];
$calls  = 0;
$synced = static function (int $ref) use ($patterns, &$calls): array {
    $calls++;
    return $patterns[$ref] ?? [];
};

$names = BlockScan::names($content, $synced);
sort($names);
check($names, ['core/block', 'core/group', 'hive/cta', 'hive/store-hours'], 'nested blocks and synced patterns, each name once');
check($calls, 6, 'a pattern that includes itself is followed five levels deep, then the scan stops');
check(BlockScan::names([], $synced), [], 'no content, no blocks');

// Hidden in the editor ("Nascondi"): the block and what's in it don't show.
$hidden = ['metadata' => ['blockVisibility' => false]];
$names  = BlockScan::names(
    [
        $block('hive/newsletter', $hidden),
        $block('core/group', $hidden, [$block('hive/faq-schema')]),
        $block('core/block', ['ref' => 7] + $hidden),
        $block('hive/store-visit', ['metadata' => ['blockVisibility' => ['viewport' => ['mobile' => false]]]]),
        $block('hive/cta', ['metadata' => ['name' => 'Banner']]),
    ],
    $synced
);
sort($names);
check($names, ['hive/cta', 'hive/store-visit'], 'a hidden block, and what is in it, is not in the page; hidden on some screens only, it is');
check(BlockScan::hidden($block('hive/newsletter', $hidden)), true, 'blockVisibility false: hidden');
check(BlockScan::hidden($block('hive/newsletter', ['metadata' => ['blockVisibility' => true]])), false, 'blockVisibility true: shown');
check(BlockScan::hidden($block('hive/newsletter', ['metadata' => ['blockVisibility' => ['viewport' => ['desktop' => false]]]])), false, 'hidden on one screen size: it renders (CSS hides it)');
check(BlockScan::hidden($block('hive/newsletter')), false, 'no metadata: shown');
check(BlockScan::hidden(null), false, 'not a block');
