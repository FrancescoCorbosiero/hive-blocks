<?php

// The migration's commerce blocks whose attributes changed shape.

use Hive\Core\Migrate\Blocks;
use Hive\Core\Migrate\Commerce;

$categories = ['sneakers' => 'Sneakers', 'accessori' => 'Accessori'];
$find       = static function (string $name, string $url) use ($categories): ?string {
    $slug = Commerce::category_in_url($url);
    if (null !== $slug && isset($categories[$slug])) {
        return $slug;
    }
    $by_name = array_search($name, $categories, true);
    return false !== $by_name ? $by_name : null;
};
check(
    Commerce::category_slugs([['name' => 'Scarpe', 'url' => '/product-category/sneakers/'], ['name' => 'Accessori', 'url' => '#accessori'], ['name' => 'Limited Edition', 'url' => '#limited'], 'x'], $find),
    ['slugs' => ['sneakers', 'accessori'], 'missing' => ['Limited Edition']],
    'category slider: by link, then by name; the rest named for a person'
);
check(Commerce::category_in_url('https://a.test/product-category/uomo/sneakers/'), 'sneakers', 'a subcategory link: its own slug');
check(Commerce::category_in_url('/?product_cat=Sneakers'), 'sneakers', 'a plain link');
check(Commerce::category_in_url('/categoria/sneakers/', 'categoria'), 'sneakers', 'the store’s own category base');
check(Commerce::category_in_url('#sneakers'), null, 'an anchor is not a category');

check(
    Commerce::brand_items([['name' => 'Nike', 'logo' => 'https://a.test/nike.svg', 'url' => '/brand/nike/'], ['name' => 'Puma', 'logo' => '', 'url' => ''], 'x'], static fn(string $url): int => 'https://a.test/nike.svg' === $url ? 7 : 0),
    [['name' => 'Nike', 'logoId' => 7, 'url' => '/brand/nike/'], ['name' => 'Puma', 'logoId' => 0, 'url' => '']],
    'brand marquee: logos from the media library by URL'
);

check(
    Commerce::countdown(['buttonText' => 'Imposta Promemoria', 'buttonUrl' => '#', 'backgroundColor' => '#0A0A0A', 'productId' => 12], ['buttonUrl' => '#', 'backgroundColor' => '#0A0A0A', 'productId' => 12, 'releaseDate' => '2026-11-28T10:00']),
    ['productId' => 12, 'style' => ['color' => ['background' => '#0a0a0a']]],
    'countdown: the "#" reminder button goes (Hive has its own), the hex colour moves to the colour support'
);
check(
    Commerce::countdown(['buttonText' => 'Vai', 'buttonUrl' => '/drop/'], ['buttonUrl' => '/drop/', 'backgroundColor' => 'nero']),
    ['buttonText' => 'Vai', 'buttonUrl' => '/drop/', 'productName' => 'Air Jordan 1 Retro High OG'],
    'a real button stays; no product and no saved name: the name it showed'
);
check(
    Commerce::countdown([], ['releaseDate' => 'domani'])[Blocks::NOTE] ?? '',
    'Conto alla rovescia: la data «domani» va riscritta come data (es. 2026-11-28 10:00)',
    'a date in words is left for a person'
);

check(
    Commerce::padding(['title' => 'x', 'paddingTop' => 40], ['paddingTop' => 40, 'paddingBottom' => '0']),
    ['title' => 'x', 'style' => ['spacing' => ['padding' => ['top' => '40px', 'bottom' => '0px']]]],
    'padding numbers as the spacing support'
);

// A note set by a 'resolve' is collected, never saved.
$notes = [];
$count = [];
$out   = Blocks::rewrite(
    [['blockName' => 'golden-hive/x', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]],
    ['golden-hive/x' => ['name' => 'hive/x', 'resolve' => static fn(array $attrs): array => $attrs + [Blocks::NOTE => 'da fare']]],
    [],
    $count,
    $notes
);
check([$out[0]['attrs'], $notes], [[], ['da fare']], 'notes: collected, not saved');
