<?php
/**
 * The migration's rules for golden-hive-blocks' commerce blocks whose
 * attributes changed shape: typed lists that now point at the store's own
 * categories and brands, a countdown's button and colour, a slider's
 * padding. What needs WordPress (finding a category, an image's id) comes in
 * as a function, so the rules stay testable.
 *
 * Pure logic: no WordPress calls. Tested in tests/php/MigrateBlocksTest.php.
 *
 * @package Hive
 */

namespace Hive\Core\Migrate;

defined('ABSPATH') || exit;

final class Commerce
{
    /**
     * golden-hive/category-slider's typed list ({name, image, url}) as the
     * slugs of the store's categories, in order.
     *
     * @param array<int, mixed>                         $items
     * @param callable(string $name, string $url): ?string $find A category's slug, from its link or its name.
     * @return array{slugs: list<string>, missing: list<string>}
     */
    public static function category_slugs(array $items, callable $find): array
    {
        $slugs   = [];
        $missing = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            $slug = $find($name, trim((string) ($item['url'] ?? '')));
            if (is_string($slug) && '' !== $slug) {
                $slugs[] = $slug;
            } elseif ('' !== $name) {
                $missing[] = $name;
            }
        }
        return ['slugs' => array_values(array_unique($slugs)), 'missing' => $missing];
    }

    /** A category's slug in a link: /product-category/sneakers/, ?product_cat=sneakers. */
    public static function category_in_url(string $url, string $base = 'product-category'): ?string
    {
        $query = (string) parse_url($url, PHP_URL_QUERY);
        parse_str($query, $args);
        if (isset($args['product_cat']) && is_string($args['product_cat']) && '' !== $args['product_cat']) {
            return strtolower($args['product_cat']);
        }
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $base = trim($base, '/');
        if ('' !== $base && str_starts_with($path . '/', $base . '/')) {
            $parts = explode('/', substr($path, strlen($base)));
            $last  = (string) end($parts);
            return '' !== $last ? strtolower(rawurldecode($last)) : null;
        }
        return null;
    }

    /**
     * golden-hive/brand-marquee's items ({name, logo, url}) as Hive's
     * ({name, logoId, url}): the logo found in the media library by URL.
     *
     * @param array<int, mixed>               $items
     * @param callable(string $url): int $logo_id
     * @return list<array{name: string, logoId: int, url: string}>
     */
    public static function brand_items(array $items, callable $logo_id): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id   = (int) ($item['logoId'] ?? 0);
            $logo = trim((string) ($item['logo'] ?? ''));
            if (!$id && '' !== $logo) {
                $id = (int) $logo_id($logo);
            }
            $out[] = ['name' => trim((string) ($item['name'] ?? '')), 'logoId' => $id, 'url' => trim((string) ($item['url'] ?? ''))];
        }
        return $out;
    }

    /**
     * golden-hive/drop-countdown into Hive's: its button only when it went
     * somewhere ("#" was the reminder, which Hive draws itself); its colour,
     * a hex, as the colour support's background (WordPress keeps palette
     * names in backgroundColor); the demo name only without a product.
     *
     * @param array<string, mixed> $new The mapped attributes.
     * @param array<string, mixed> $old The old ones, defaults filled in.
     * @return array<string, mixed>
     */
    public static function countdown(array $new, array $old): array
    {
        $url = trim((string) ($old['buttonUrl'] ?? '#'));
        if ('' === $url || '#' === $url) {
            unset($new['buttonUrl'], $new['buttonText']);
        }
        unset($new['backgroundColor']);
        $color = (string) ($old['backgroundColor'] ?? '');
        if (1 === preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $color)) {
            $new['style']['color']['background'] = strtolower($color);
        }
        if (empty($old['productId']) && !isset($old['productName'])) {
            $new['productName'] = 'Air Jordan 1 Retro High OG';
        }
        $date = trim((string) ($old['releaseDate'] ?? ''));
        if ('' !== $date && 1 !== preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            $new[Blocks::NOTE] = sprintf('Conto alla rovescia: la data «%s» va riscritta come data (es. 2026-11-28 10:00)', $date);
        }
        return $new;
    }

    /**
     * A block's old padding numbers (pixels) as the spacing support's.
     *
     * @param array<string, mixed> $new
     * @param array<string, mixed> $old
     * @return array<string, mixed>
     */
    public static function padding(array $new, array $old): array
    {
        foreach (['paddingTop' => 'top', 'paddingBottom' => 'bottom'] as $key => $side) {
            unset($new[$key]);
            if (isset($old[$key]) && is_numeric($old[$key])) {
                $new['style']['spacing']['padding'][$side] = max(0, (int) $old[$key]) . 'px';
            }
        }
        return $new;
    }
}
