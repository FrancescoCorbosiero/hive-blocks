<?php
/**
 * Social proof from real orders, never typed in:
 *
 *   "Venduto 3 volte negli ultimi 30 giorni" on the product page (Hive →
 *   Pagina prodotto), counted in WooCommerce's order lookup tables, from two
 *   sales up; nothing where the tables are missing
 *   the feed of the latest paid orders for the "Qualcuno ha appena
 *   acquistato" toast (a content block): GET /wp-json/hive/v1/social-proof,
 *   a public read the CDN can keep for five minutes. One line per order:
 *   the product (name, photo, link), the shipping city (Core\City) and when
 *   it was paid; never a name, an email or a total. The browser works out
 *   "2 ore fa" from the time, so it stays true on a cached page
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Core\City;

defined('ABSPATH') || exit;

final class SocialProof
{
    private const MAX_DAYS  = 30;
    private const MAX_ITEMS = 20;
    private const GEN       = 'hive_orders_gen';

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('woocommerce_order_status_changed', [self::class, 'bump']);
    }

    /** Any status change (paid, completed, refunded, cancelled) refreshes the feed. */
    public static function bump(): void
    {
        update_option(self::GEN, (int) get_option(self::GEN, 0) + 1, true);
    }

    public static function routes(): void
    {
        register_rest_route(
            ProductData::NAMESPACE,
            '/social-proof',
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [self::class, 'respond'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'days'  => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_DAYS, 'default' => 14],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_ITEMS, 'default' => 10],
                    'city'  => ['type' => 'boolean', 'default' => true],
                ],
            ]
        );
    }

    public static function respond(\WP_REST_Request $request): \WP_REST_Response
    {
        $items    = apply_filters('hive_social_proof_enabled', true) ? self::feed((int) $request['days'], (int) $request['limit'], (bool) $request['city']) : [];
        $response = new \WP_REST_Response(['items' => $items]);
        $response->header('Cache-Control', 'public, max-age=300, s-maxage=300');
        return $response;
    }

    /**
     * The latest paid orders, newest first.
     *
     * @return list<array{product: string, image: string, url: string, city: string, ts: int}>
     */
    public static function feed(int $days, int $limit, bool $with_city): array
    {
        $days   = max(1, min(self::MAX_DAYS, $days));
        $limit  = max(1, min(self::MAX_ITEMS, $limit));
        $key    = 'hive_sp_' . md5((int) get_option(self::GEN, 0) . '|' . $days . '|' . $limit . '|' . (int) $with_city);
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }
        $items  = [];
        $orders = wc_get_orders(
            [
                'type'         => 'shop_order',
                'status'       => ['wc-processing', 'wc-completed'],
                'date_created' => '>' . (time() - $days * DAY_IN_SECONDS),
                'orderby'      => 'date',
                'order'        => 'DESC',
                // Some orders hold nothing to show (a deleted product).
                'limit'        => $limit * 2,
            ]
        );
        foreach (is_array($orders) ? $orders : [] as $order) {
            if (count($items) >= $limit) {
                break;
            }
            $item = $order instanceof \WC_Order ? self::item($order, $with_city) : null;
            if ($item) {
                $items[] = $item;
            }
        }
        set_transient($key, $items, 10 * MINUTE_IN_SECONDS);
        return $items;
    }

    /** One order: its first product that still has a public page. */
    private static function item(\WC_Order $order, bool $with_city): ?array
    {
        foreach ($order->get_items() as $line) {
            if (!$line instanceof \WC_Order_Item_Product) {
                continue;
            }
            $product = $line->get_product();
            $parent  = $product instanceof \WC_Product && $product->is_type('variation') ? wc_get_product($product->get_parent_id()) : $product;
            if (!$parent instanceof \WC_Product || 'publish' !== $parent->get_status() || 'hidden' === $parent->get_catalog_visibility()) {
                continue;
            }
            $paid = $order->get_date_paid() ?: $order->get_date_created();
            return [
                'product' => wp_strip_all_tags($parent->get_name()),
                'image'   => (string) (wp_get_attachment_image_url((int) $parent->get_image_id(), 'thumbnail') ?: ''),
                'url'     => $parent->get_permalink(),
                'city'    => $with_city ? City::name((string) ($order->get_shipping_city() ?: $order->get_billing_city())) : '',
                'ts'      => $paid ? $paid->getTimestamp() : 0,
            ];
        }
        return null;
    }

    /** Paid orders of a product (any size) in the last $days days; 0 without WooCommerce's lookup tables. */
    public static function sold_recently(int $product_id, int $days = 30): int
    {
        $key    = 'hive_sold_' . $product_id . '_' . $days;
        $cached = get_transient($key);
        if (false !== $cached) {
            return (int) $cached;
        }
        if (!self::lookup_ready()) {
            return 0;
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- WooCommerce's analytics tables have no API for this count; cached above.
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT l.order_id) FROM {$wpdb->prefix}wc_order_product_lookup l INNER JOIN {$wpdb->prefix}wc_order_stats s ON s.order_id = l.order_id WHERE l.product_id = %d AND s.status IN ('wc-processing', 'wc-completed') AND s.date_created >= %s",
                $product_id,
                // date_created is the site's local time.
                wp_date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS)
            )
        );
        set_transient($key, $count, 6 * HOUR_IN_SECONDS);
        return $count;
    }

    private static function lookup_ready(): bool
    {
        static $ready = null;
        if (null !== $ready) {
            return $ready;
        }
        $cached = get_transient('hive_lookup_ready');
        if (false !== $cached) {
            return $ready = ('yes' === $cached);
        }
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery -- a one-off check, cached for a day.
        $ready = null !== $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'wc_order_product_lookup'))
            && null !== $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'wc_order_stats'));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery
        set_transient('hive_lookup_ready', $ready ? 'yes' : 'no', DAY_IN_SECONDS);
        return $ready;
    }

    /** The product page's line, from two sales up. */
    public static function line(\WC_Product $product): string
    {
        $days  = 30;
        $count = self::sold_recently($product->get_id(), $days);
        if ($count < (int) apply_filters('hive_sold_count_min', 2)) {
            return '';
        }
        return sprintf(
            '<p class="hive-product__sold"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>%s</p>',
            esc_html(sprintf('Venduto %d volte negli ultimi %d giorni', $count, $days))
        );
    }
}
