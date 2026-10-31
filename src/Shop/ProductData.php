<?php
/**
 * A product's data for Quick View and Quick Add, as a public, cacheable REST
 * read: GET /wp-json/hive/v1/product/{id}?v={generation}.
 *
 * The URL carries the catalog generation (Store::generation()), which moves
 * whenever products change, so the answer can be cached for long by the
 * browser and the CDN: a change makes new URLs instead of stale ones. Pages
 * learn the generation from the @hive/shop module data.
 *
 * Cart writes don't go through here: they use WooCommerce's own
 * wc-ajax=add_to_cart (see assets/modules/shop.js).
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Settings;

defined('ABSPATH') || exit;

final class ProductData
{
    public const NAMESPACE = 'hive/v1';

    /** Long cache for a versioned URL: a product change moves the version. */
    private const CACHE_VERSIONED = 'public, max-age=3600, s-maxage=86400, stale-while-revalidate=604800';

    /** Short cache without a version. */
    private const CACHE_PLAIN = 'public, max-age=60, s-maxage=300';

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
        add_filter('script_module_data_@hive/shop', [self::class, 'module_data']);
    }

    public static function routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/product/(?P<id>\d+)',
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [self::class, 'respond'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
                    'v'  => ['type' => 'integer', 'minimum' => 0],
                ],
            ]
        );
    }

    /** What the shop's script modules need to know about this site. */
    public static function module_data(array $data): array
    {
        return [
            'api'      => rest_url(self::NAMESPACE . '/'),
            'v'        => Store::generation(),
            'cart'     => class_exists('WC_AJAX') ? \WC_AJAX::get_endpoint('add_to_cart') : '',
            'cartUrl'  => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
            'whatsapp' => (string) Settings::get('whatsapp'),
        ] + $data;
    }

    public static function respond(\WP_REST_Request $request): \WP_REST_Response
    {
        $product = wc_get_product((int) $request['id']);
        if (!self::is_public($product)) {
            $response = new \WP_REST_Response(['code' => 'hive_product_not_found', 'message' => 'Prodotto non trovato'], 404);
            $response->header('Cache-Control', self::CACHE_PLAIN);
            return $response;
        }
        $response = new \WP_REST_Response(self::payload($product));
        $response->header('Cache-Control', null !== $request->get_param('v') ? self::CACHE_VERSIONED : self::CACHE_PLAIN);
        return $response;
    }

    /** A product anyone may see: published, not hidden from the catalog, not a variation. */
    public static function is_public(mixed $product): bool
    {
        return $product instanceof \WC_Product
            && !$product->is_type('variation')
            && 'publish' === $product->get_status()
            && 'hidden' !== $product->get_catalog_visibility()
            && '' === (string) get_post_field('post_password', $product->get_id());
    }

    /** @return array<string, mixed> */
    public static function payload(\WC_Product $product): array
    {
        [$choices, $variations] = self::choices($product);
        $categories             = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);

        $data = [
            'id'          => $product->get_id(),
            'type'        => $product->get_type(),
            'title'       => wp_strip_all_tags($product->get_name()),
            'url'         => $product->get_permalink(),
            'brand'       => Store::brand($product),
            'categories'  => is_array($categories) ? array_map(static fn($name) => html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8'), $categories) : [],
            'sku'         => (string) $product->get_sku(),
            'price_html'  => $product->get_price_html(),
            'summary'     => (string) apply_filters('woocommerce_short_description', $product->get_short_description()),
            'images'      => self::images($product),
            'attributes'  => self::attributes($product),
            'in_stock'    => $product->is_in_stock(),
            'purchasable' => $product->is_purchasable() && $product->is_in_stock(),
            'state'       => Card::state($product),
            'choices'     => $choices,
            'variations'  => $variations,
        ];
        return (array) apply_filters('hive_product_data', $data, $product);
    }

    /**
     * Main image and gallery (up to 5): src and srcset in the single-product
     * size, a small thumbnail, the alt text and the size.
     *
     * @return list<array{src: string, srcset: string, thumb: string, alt: string, width: int, height: int}>
     */
    private static function images(\WC_Product $product): array
    {
        $ids    = array_slice(array_values(array_unique(array_filter(array_merge([(int) $product->get_image_id()], array_map('intval', $product->get_gallery_image_ids()))))), 0, 5);
        $images = [];
        foreach ($ids as $id) {
            $full = wp_get_attachment_image_src($id, 'woocommerce_single');
            if (!$full) {
                continue;
            }
            $images[] = [
                'src'    => $full[0],
                'srcset' => (string) wp_get_attachment_image_srcset($id, 'woocommerce_single'),
                'thumb'  => (string) wp_get_attachment_image_url($id, 'woocommerce_gallery_thumbnail'),
                'alt'    => trim((string) get_post_meta($id, '_wp_attachment_image_alt', true)),
                'width'  => (int) $full[1],
                'height' => (int) $full[2],
            ];
        }
        if (!$images) {
            $images[] = ['src' => wc_placeholder_img_src('woocommerce_single'), 'srcset' => '', 'thumb' => '', 'alt' => '', 'width' => 0, 'height' => 0];
        }
        return $images;
    }

    /**
     * The visible attributes that aren't a choice to make (material,
     * colorway…), as label and value.
     *
     * @return list<array{label: string, value: string}>
     */
    private static function attributes(\WC_Product $product): array
    {
        $out = [];
        foreach ($product->get_attributes() as $attribute) {
            if (!$attribute instanceof \WC_Product_Attribute || !$attribute->get_visible() || $attribute->get_variation()) {
                continue;
            }
            $value = $product->get_attribute($attribute->get_name());
            if ('' !== $value) {
                $out[] = ['label' => wc_attribute_label($attribute->get_name(), $product), 'value' => $value];
            }
        }
        return $out;
    }

    /**
     * What a variable product asks to choose, and the variations that can be
     * bought. The size attribute keeps one variation per physical size
     * (Sizes::rows()) and lists every size, sold-out ones included, in size
     * order; other attributes keep WooCommerce's options.
     *
     *   choices     [{name: 'pa_taglia', label: 'Taglia', options: [{value, label}]}]
     *   variations  [{id, attributes: {pa_taglia: '42'}, in_stock, price_html, price_text, max_qty, image}]
     *
     * @return array{0: list<array>, 1: list<array>}
     */
    private static function choices(\WC_Product $product): array
    {
        if (!$product instanceof \WC_Product_Variable) {
            return [[], []];
        }
        $size = Store::size_attribute();
        $rows = Sizes::rows($product);
        $kept = array_fill_keys(array_column($rows, 'variation_id'), true);

        $choices = [];
        foreach ($product->get_variation_attributes() as $name => $options) {
            if ($name === $size && $rows) {
                $values = [];
                foreach ($rows as $row) {
                    $values[$row['value']] = ['value' => $row['value'], 'label' => $row['label']];
                }
                $values = array_values($values);
            } else {
                $values = [];
                foreach ((array) $options as $option) {
                    $term     = taxonomy_exists($name) ? get_term_by('slug', (string) $option, $name) : false;
                    $values[] = ['value' => (string) $option, 'label' => $term instanceof \WP_Term ? $term->name : (string) $option];
                }
            }
            $choices[] = ['name' => sanitize_title($name), 'label' => wc_attribute_label($name, $product), 'options' => $values];
        }

        $variations = [];
        foreach ($product->get_available_variations('objects') as $variation) {
            if (!$variation instanceof \WC_Product_Variation) {
                continue;
            }
            $id = $variation->get_id();
            if ($rows && $variation->get_attribute($size) !== '' && !isset($kept[$id])) {
                continue; // a duplicate of a size another variation stands for
            }
            $attributes = [];
            foreach ($variation->get_variation_attributes() as $key => $value) {
                $attributes[substr($key, strlen('attribute_'))] = (string) $value;
            }
            $max          = (int) $variation->get_max_purchase_quantity();
            $variations[] = [
                'id'         => $id,
                'attributes' => $attributes,
                'in_stock'   => $variation->is_in_stock() && $variation->is_purchasable(),
                'price_html' => $variation->get_price_html(),
                'price_text' => '' !== $variation->get_price() ? Sizes::plain_price($variation) : '',
                'max_qty'    => $max > 0 ? $max : -1,
                'image'      => (string) wp_get_attachment_image_url((int) $variation->get_image_id(), 'woocommerce_thumbnail'),
            ];
        }
        return [$choices, $variations];
    }
}
