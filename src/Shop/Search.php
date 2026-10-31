<?php
/**
 * Live search's read (module "Ricerca istantanea"): GET
 * /wp-json/hive/v1/search?q=…&v={generation}, the products matching a
 * query, the same for everyone who types it, so the CDN can keep the answer.
 *
 *   Relevanssi does the searching when it's active (its index, its
 *   weights; its Live Ajax Search add-on isn't needed), WordPress's own
 *   search otherwise. A product code (SKU) typed in full comes first.
 *   Single letters and digits count: sneaker names hinge on them ("air
 *   jordan 3")
 *   kept 10 minutes per query and catalog generation, for when no CDN sits
 *   in front
 *
 * @package Hive
 */

namespace Hive\Shop;

defined('ABSPATH') || exit;

final class Search
{
    private const LIMIT = 6;

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
        add_filter('relevanssi_block_one_letter_searches', '__return_false');
    }

    public static function routes(): void
    {
        register_rest_route(
            ProductData::NAMESPACE,
            '/search',
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [self::class, 'respond'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'q' => ['type' => 'string', 'required' => true, 'minLength' => 1, 'maxLength' => 80],
                    'v' => ['type' => 'integer', 'minimum' => 0],
                ],
            ]
        );
    }

    public static function respond(\WP_REST_Request $request): \WP_REST_Response
    {
        $q        = trim(sanitize_text_field((string) $request['q']));
        $response = new \WP_REST_Response(self::results($q));
        $response->header('Cache-Control', null !== $request->get_param('v') ? 'public, max-age=600, s-maxage=3600, stale-while-revalidate=86400' : 'public, max-age=60, s-maxage=300');
        return $response;
    }

    /**
     * @return array{query: string, total: int, url: string, items: list<array{id: int, name: string, brand: string, url: string, price_html: string, image: string}>}
     */
    public static function results(string $q): array
    {
        $q   = function_exists('mb_substr') ? mb_substr($q, 0, 80) : substr($q, 0, 80);
        $key = 'hive_search_' . md5(strtolower($q) . '|' . Store::generation());
        $hit = get_transient($key);
        if (is_array($hit)) {
            return $hit;
        }

        [$ids, $total] = '' === $q ? [[], 0] : self::find($q);
        $items = [];
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if (!$product instanceof \WC_Product || !$product->is_visible()) {
                continue;
            }
            $items[] = [
                'id'         => $product->get_id(),
                'name'       => wp_strip_all_tags($product->get_name()),
                'brand'      => Store::brand($product),
                'url'        => $product->get_permalink(),
                'price_html' => (string) $product->get_price_html(),
                'image'      => (string) (wp_get_attachment_image_url((int) $product->get_image_id(), 'woocommerce_gallery_thumbnail') ?: wc_placeholder_img_src('woocommerce_gallery_thumbnail')),
            ];
        }
        $result = [
            'query' => $q,
            'total' => max($total, count($items)),
            'url'   => add_query_arg(['s' => $q, 'post_type' => 'product'], home_url('/')),
            'items' => $items,
        ];
        set_transient($key, $result, 10 * MINUTE_IN_SECONDS);
        return $result;
    }

    /** @return array{0: list<int>, 1: int} The first products and how many match. */
    private static function find(string $q): array
    {
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            's'              => $q,
            'posts_per_page' => self::LIMIT,
            'tax_query'      => [['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => ['exclude-from-search'], 'operator' => 'NOT IN']], // phpcs:ignore WordPress.DB.SlowDBQuery
        ];
        $query = new \WP_Query();
        if (function_exists('relevanssi_do_query')) {
            $query->parse_query($args);
            relevanssi_do_query($query);
        } else {
            $query->query($args + ['fields' => 'ids']);
        }
        $ids = array_map(static fn($post): int => $post instanceof \WP_Post ? $post->ID : (int) $post, (array) $query->posts);

        // A product code typed in full comes first.
        $sku = 1 === preg_match('/^[A-Za-z0-9][A-Za-z0-9._\-\/]{3,}$/', $q) ? (int) wc_get_product_id_by_sku($q) : 0;
        if ($sku) {
            $parent = wp_get_post_parent_id($sku);
            $ids    = array_values(array_unique(array_merge([$parent ?: $sku], $ids)));
        }
        return [array_slice($ids, 0, self::LIMIT), (int) $query->found_posts + ($sku ? 1 : 0)];
    }
}
