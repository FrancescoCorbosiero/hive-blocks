<?php
/**
 * "Nella tua taglia": a rail of the shopper's own size (block hive/size-rail,
 * shortcode [hive_size_rail]).
 *
 * The page stays the same for everyone: it carries the frame and a choice
 * of sizes, and the browser fills the rail for the size it remembers
 * (picked here, on a product page or in the newsletter) from
 * GET /wp-json/hive/v1/size-rail?size=42&v=…, a read the CDN can keep per
 * size. Products with that size in stock, newest first, every card naming
 * it ("Taglia 42 · 180 €") and opening Quick View and Quick Add on it. A
 * size with nothing in stock offers the size alert instead.
 *
 * Sizes are grouped across the store's spellings ("36 2/3" and "36-2-3"
 * are one choice): Core\Sizes::group_options().
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Assets;
use Hive\Core\Sizes as SizeRules;
use Hive\Modules;

defined('ABSPATH') || exit;

final class SizeRail
{
    public const DEFAULTS = [
        'title'   => 'Nella tua taglia',
        'text'    => 'Scegli la tua taglia: ti mostriamo solo i paia disponibili nella tua misura.',
        'limit'   => 12,
        'columns' => 4,
    ];

    public static function register(): void
    {
        add_shortcode('hive_size_rail', [self::class, 'shortcode']);
        add_action('rest_api_init', [self::class, 'routes']);
        add_filter('script_module_data_@hive/size-rail', [self::class, 'module_data']);
    }

    /** @param array<string, mixed> $data */
    public static function module_data(array $data): array
    {
        return [
            'api'    => rest_url(ProductData::NAMESPACE . '/'),
            'alerts' => Modules::enabled('alerts') ? \WC_AJAX::get_endpoint(Alerts::AJAX) : '',
        ] + $data;
    }

    /**
     * The sizes some product carries, grouped and sorted; cached per catalog generation.
     *
     * @return list<array{key: string, label: string, slugs: list<string>, count: int}>
     */
    public static function options(): array
    {
        $key    = 'hive_size_options_' . Store::generation();
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }
        $terms = get_terms(['taxonomy' => Store::size_attribute(), 'hide_empty' => true]);
        $rows  = [];
        foreach (is_array($terms) ? $terms : [] as $term) {
            $rows[] = ['name' => $term->name, 'slug' => $term->slug, 'count' => $term->count];
        }
        $options = SizeRules::group_options($rows);
        set_transient($key, $options, DAY_IN_SECONDS);
        return $options;
    }

    public static function shortcode(mixed $atts): string
    {
        return self::render(is_array($atts) ? $atts : []);
    }

    /**
     * The frame and the size choice; the rail comes from the browser.
     *
     * @param callable|null $wrapper fn(array{class: string}): string, a block's wrapper attributes.
     */
    public static function render(array $atts, ?callable $wrapper = null): string
    {
        $atts    = array_merge(self::DEFAULTS, array_intersect_key($atts, self::DEFAULTS));
        $options = self::options();
        if (!$options) {
            return '';
        }
        $prelude = Assets::use_style('hive-shop') . Assets::use_style('hive-size-rail');
        Assets::use_module('@hive/size-rail');
        Assets::use_module('@hive/cards');
        Assets::use_module('@hive/rail');

        $chips = '';
        foreach ($options as $option) {
            $chips .= sprintf('<button type="button" class="hive-size-rail__chip" data-key="%s" aria-pressed="false">%s</button>', esc_attr($option['key']), esc_html($option['label']));
        }
        $classes    = 'hive-block hive-size-rail';
        $attributes = null === $wrapper ? sprintf('class="%s"', esc_attr($classes)) : (string) $wrapper(['class' => $classes]);
        return $prelude . sprintf(
            '<section %1$s data-hive-size-rail data-limit="%2$d" data-columns="%3$d" data-v="%4$d"><div class="hive-size-rail__head"><h2 class="hive-size-rail__title">%5$s <span class="hive-size-rail__current" data-hive-size-current hidden></span></h2><button type="button" class="hive-size-rail__change" data-hive-size-change hidden>Cambia taglia</button></div><div class="hive-size-rail__picker" data-hive-size-picker>%6$s<div class="hive-size-rail__chips" role="group" aria-label="La tua taglia">%7$s</div></div><div class="hive-size-rail__body" data-hive-size-body aria-live="polite"></div></section>',
            $attributes,
            max(1, min(24, (int) $atts['limit'])),
            max(1, min(8, (int) $atts['columns'])),
            Store::generation(),
            esc_html((string) $atts['title']),
            '' !== trim((string) $atts['text']) ? '<p class="hive-size-rail__text">' . esc_html((string) $atts['text']) . '</p>' : '',
            $chips
        );
    }

    public static function routes(): void
    {
        register_rest_route(
            ProductData::NAMESPACE,
            '/size-rail',
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [self::class, 'respond'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'size'    => ['type' => 'string', 'required' => true, 'pattern' => '^[a-z0-9]{1,20}$'],
                    'limit'   => ['type' => 'integer', 'minimum' => 1, 'maximum' => 24, 'default' => 12],
                    'columns' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 8, 'default' => 4],
                    'v'       => ['type' => 'integer', 'minimum' => 0],
                ],
            ]
        );
    }

    /** GET hive/v1/size-rail: the rail for one size, the same for everyone. */
    public static function respond(\WP_REST_Request $request): \WP_REST_Response
    {
        $key    = SizeRules::key((string) $request['size']);
        $option = null;
        foreach (self::options() as $candidate) {
            if ($candidate['key'] === $key) {
                $option = $candidate;
            }
        }
        if (null === $option) {
            $response = new \WP_REST_Response(['code' => 'hive_size_not_found', 'message' => 'Taglia non trovata'], 404);
            $response->header('Cache-Control', 'public, max-age=60, s-maxage=300');
            return $response;
        }
        $ids  = array_slice(self::product_ids($option['slugs']), 0, (int) $request['limit']);
        $html = $ids ? Rail::render(
            ['ids' => implode(',', $ids), 'limit' => count($ids), 'columns' => (int) $request['columns'], 'show_all' => false],
            null,
            // Every card names this size, and Quick View and Quick Add open on it.
            ['size_filter' => implode(',', $option['slugs'])]
        ) : '';
        $response = new \WP_REST_Response(['key' => $option['key'], 'label' => $option['label'], 'html' => $html, 'alerts' => Modules::enabled('alerts')]);
        $response->header('Cache-Control', null !== $request->get_param('v') ? 'public, max-age=600, s-maxage=3600, stale-while-revalidate=86400' : 'public, max-age=60, s-maxage=300');
        return $response;
    }

    /**
     * Products with one of these size slugs in stock, newest first: a
     * variation in stock, or a simple product carrying the size. Drops not
     * out yet are left out. Cached per catalog generation.
     *
     * @param list<string> $slugs
     * @return list<int>
     */
    public static function product_ids(array $slugs): array
    {
        $slugs = array_values(array_filter(array_map('strtolower', $slugs)));
        if (!$slugs) {
            return [];
        }
        $key    = 'hive_size_ids_' . md5(implode(',', $slugs) . '|' . Store::generation());
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }
        global $wpdb;
        $taxonomy = Store::size_attribute();
        $in       = implode(',', array_fill(0, count($slugs), '%s'));
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one query over the variations' meta (WooCommerce has no API for "in stock in these sizes"); the IN list is placeholders only; cached above.
        $parents = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT v.post_parent FROM {$wpdb->posts} v INNER JOIN {$wpdb->posts} p ON p.ID = v.post_parent AND p.post_status = 'publish' INNER JOIN {$wpdb->postmeta} a ON a.post_id = v.ID AND a.meta_key = %s AND LOWER(a.meta_value) IN ($in) INNER JOIN {$wpdb->postmeta} s ON s.post_id = v.ID AND s.meta_key = '_stock_status' AND s.meta_value = 'instock' WHERE v.post_type = 'product_variation' AND v.post_status = 'publish'",
                array_merge(['attribute_' . $taxonomy], $slugs)
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $simple = get_posts(
            [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'posts_per_page' => 200,
                'no_found_rows'  => true,
                'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery
                    'relation' => 'AND',
                    ['taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'simple'],
                    ['taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $slugs],
                ],
                'meta_query'     => [['key' => '_stock_status', 'value' => 'instock']], // phpcs:ignore WordPress.DB.SlowDBQuery
            ]
        );
        $ids = array_values(array_unique(array_map('intval', array_merge((array) $parents, $simple))));
        // A drop not out yet isn't "available in your size".
        $ids = array_values(array_filter($ids, static fn(int $id): bool => !(Modules::enabled('drops') && Drops::locked($id))));
        rsort($ids);
        set_transient($key, $ids, 10 * MINUTE_IN_SECONDS);
        return $ids;
    }
}
