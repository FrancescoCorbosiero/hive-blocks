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
