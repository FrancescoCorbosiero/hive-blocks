<?php
/**
 * Order bumps (in place of CommerceKit's Order Bump): Hive → Offerte al
 * checkout. Each bump offers one product at the checkout, above the
 * payment methods ("Aggiungi anche…"), for any cart or for carts with a
 * product of some categories. The first bump that fits shows
 * (Core\Bumps); one click adds its product and the checkout's totals
 * refresh. After every refresh the bump is drawn again, so the next one
 * that fits takes its place.
 *
 * Classic checkout: WooCommerce's woocommerce_review_order_before_payment
 * hook, and a fragment of WooCommerce's own order review refresh. The
 * Checkout block: the bump above the block, added through WooCommerce's
 * cart store (wc/store/cart) so the block updates by itself.
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Assets;
use Hive\Core\Bumps;
use Hive\Settings;

defined('ABSPATH') || exit;

final class OrderBumps
{
    public const TYPE = 'hive_bump';

    private const NONCE = 'hive_bump_nonce';
    private const CACHE = 'hive_bumps';

    public static function register(): void
    {
        add_action('init', [self::class, 'post_type']);
        add_action('add_meta_boxes_' . self::TYPE, [self::class, 'meta_box']);
        add_action('save_post_' . self::TYPE, [self::class, 'save'], 10, 2);
        add_action('save_post_' . self::TYPE, [self::class, 'forget'], 20);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);
        add_action('woocommerce_review_order_before_payment', [self::class, 'classic']);
        add_filter('woocommerce_update_order_review_fragments', [self::class, 'fragment']);
        add_filter('render_block_woocommerce/checkout', [self::class, 'block'], 10, 1);
        foreach (['trashed_post', 'untrashed_post', 'deleted_post'] as $hook) {
            add_action($hook, [self::class, 'maybe_forget']);
        }
    }

    public static function post_type(): void
    {
        register_post_type(
            self::TYPE,
            [
                'labels'          => [
                    'name'          => 'Offerte al checkout',
                    'singular_name' => 'Offerta al checkout',
                    'add_new'       => 'Aggiungi offerta',
                    'add_new_item'  => 'Aggiungi un’offerta al checkout',
                    'edit_item'     => 'Modifica l’offerta',
                    'all_items'     => 'Offerte al checkout',
                    'menu_name'     => 'Offerte al checkout',
                    'not_found'     => 'Nessuna offerta',
                ],
                'description'     => 'Un prodotto in più proposto al checkout.',
                'public'          => false,
                'show_ui'         => true,
                'show_in_menu'    => Settings::PAGE,
                'show_in_rest'    => false,
                'supports'        => ['title', 'page-attributes'],
                'capability_type' => 'product',
                'map_meta_cap'    => true,
                'rewrite'         => false,
                'query_var'       => false,
            ]
        );
    }

    /**
     * Published bumps, in order: [id => {product, cats, headline, text, button}].
     *
     * @return array<int, array{product: int, cats: list<int>, headline: string, text: string, button: string}>
     */
    public static function all(): array
    {
        $cached = get_transient(self::CACHE);
        if (is_array($cached)) {
            return $cached;
        }
        $bumps = [];
        foreach (get_posts(['post_type' => self::TYPE, 'post_status' => 'publish', 'numberposts' => 50, 'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'], 'suppress_filters' => true]) as $post) {
            $bumps[$post->ID] = [
                'product'  => (int) get_post_meta($post->ID, '_hive_bump_product', true),
                'cats'     => array_values(array_map('intval', array_filter((array) get_post_meta($post->ID, '_hive_bump_cats', true)))),
                'headline' => (string) get_post_meta($post->ID, '_hive_bump_headline', true),
                'text'     => (string) get_post_meta($post->ID, '_hive_bump_text', true),
                'button'   => (string) get_post_meta($post->ID, '_hive_bump_button', true),
            ];
        }
        set_transient(self::CACHE, $bumps, DAY_IN_SECONDS);
        return $bumps;
    }

    public static function forget(): void
    {
        delete_transient(self::CACHE);
    }

    public static function maybe_forget(mixed $post_id): void
    {
        if (self::TYPE === get_post_type((int) $post_id)) {
            self::forget();
        }
    }

    /** The bump that fits the cart now, if any. */
    public static function current(): ?array
    {
        $cart = function_exists('WC') && WC()->cart ? WC()->cart : null;
        if (!$cart || $cart->is_empty()) {
            return null;
        }
        $bumps = self::all();
        if (!$bumps) {
            return null;
        }
        $in_cart = [];
        $cats    = [];
        foreach ($cart->get_cart() as $item) {
            foreach ([(int) ($item['product_id'] ?? 0), (int) ($item['variation_id'] ?? 0)] as $id) {
                if ($id) {
                    $in_cart[] = $id;
                }
            }
            foreach (wc_get_product_term_ids((int) ($item['product_id'] ?? 0), 'product_cat') as $cat) {
                $cats[] = (int) $cat;
                $cats   = array_merge($cats, array_map('intval', get_ancestors((int) $cat, 'product_cat', 'taxonomy')));
            }
        }
        $buyable = [];
        foreach ($bumps as $bump) {
            $product = wc_get_product($bump['product']);
            if ($product instanceof \WC_Product && $product->is_type('simple') && $product->is_purchasable() && $product->is_in_stock() && 'publish' === $product->get_status()) {
                $buyable[] = $product->get_id();
            }
        }
        $id = Bumps::pick($bumps, array_values(array_unique($in_cart)), array_values(array_unique($cats)), $buyable);
        return null === $id ? null : ['id' => $id] + $bumps[$id];
    }

    /** The bump, or its empty place (the fragment's target). */
    public static function render(): string
    {
        $bump    = self::current();
        $product = $bump ? wc_get_product($bump['product']) : null;
        if (!$bump || !$product instanceof \WC_Product) {
            return '<div class="hive-block hive-bump" data-hive-bump hidden></div>';
        }
        Assets::use_module('@hive/checkout');
        $name     = wp_strip_all_tags($product->get_name());
        $headline = '' !== $bump['headline'] ? $bump['headline'] : 'Aggiungi anche';
        $button   = '' !== $bump['button'] ? $bump['button'] : 'Aggiungi all’ordine';
        $image    = wp_get_attachment_image((int) $product->get_image_id(), 'woocommerce_gallery_thumbnail', false, ['class' => 'hive-bump__image', 'alt' => '', 'loading' => 'lazy']);
        return Assets::use_style('hive-checkout') . sprintf(
            '<div class="hive-block hive-bump" data-hive-bump="%1$d"><p class="hive-bump__eyebrow">%2$s</p><div class="hive-bump__body">%3$s<div class="hive-bump__text"><p class="hive-bump__name">%4$s</p>%5$s<p class="hive-bump__price">%6$s</p></div></div><button type="button" class="hive-button hive-bump__add" data-hive-bump-add="%1$d">%7$s</button><p class="hive-bump__status" role="status" data-hive-bump-status></p></div>',
            $product->get_id(),
            esc_html($headline),
            $image,
            esc_html($name),
            '' !== $bump['text'] ? '<p class="hive-bump__note">' . esc_html($bump['text']) . '</p>' : '',
            wp_kses_post($product->get_price_html()),
            esc_html($button)
        );
    }

    /** Classic checkout: above the payment methods. */
    public static function classic(): void
    {
        echo self::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() escapes what it prints.
    }

    /** The order review's refresh brings the bump that fits the cart now. */
    public static function fragment(mixed $fragments): mixed
    {
        if (is_array($fragments)) {
            $fragments['.hive-bump'] = self::render();
        }
        return $fragments;
    }

    /** The Checkout block: the bump above it (the block itself is drawn in the browser). */
    public static function block(mixed $content): mixed
    {
        return is_string($content) ? self::render() . $content : $content;
    }

    public static function admin_assets(string $hook): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && self::TYPE === $screen->post_type && in_array($hook, ['post.php', 'post-new.php'], true)) {
            // WooCommerce's product search field.
            wp_enqueue_script('wc-enhanced-select');
            wp_enqueue_style('woocommerce_admin_styles');
        }
    }

    public static function meta_box(): void
    {
        add_meta_box('hive-bump-offer', 'Offerta', [self::class, 'meta_box_html'], self::TYPE, 'normal', 'high');
    }

    public static function meta_box_html(\WP_Post $post): void
    {
        wp_nonce_field('hive_bump_save', self::NONCE);
        $product  = wc_get_product((int) get_post_meta($post->ID, '_hive_bump_product', true));
        $cats     = array_map('intval', array_filter((array) get_post_meta($post->ID, '_hive_bump_cats', true)));
        $field    = static fn(string $key): string => (string) get_post_meta($post->ID, $key, true);

        $chosen = $product instanceof \WC_Product ? sprintf('<option value="%d" selected>%s</option>', $product->get_id(), esc_html(wp_strip_all_tags($product->get_formatted_name()))) : '';
        echo '<table class="form-table" role="presentation"><tbody>';
        printf(
            '<tr><th scope="row"><label for="hive-bump-product">Prodotto</label></th><td><select id="hive-bump-product" class="wc-product-search" style="width:100%%;max-width:28em" name="hive_bump_product" data-placeholder="Cerca un prodotto semplice…" data-action="woocommerce_json_search_products" data-allow_clear="true">%s</select><p class="description">Un prodotto semplice, disponibile: l’offerta si nasconde quando è esaurito o già nel carrello.</p></td></tr>',
            wp_kses($chosen, ['option' => ['value' => true, 'selected' => true]])
        );
        printf('<tr><th scope="row"><label for="hive-bump-headline">Titolo</label></th><td><input id="hive-bump-headline" class="regular-text" name="hive_bump_headline" value="%s" placeholder="Aggiungi anche"></td></tr>', esc_attr($field('_hive_bump_headline')));
        printf('<tr><th scope="row"><label for="hive-bump-text">Testo</label></th><td><input id="hive-bump-text" class="large-text" name="hive_bump_text" value="%s" placeholder="Es. Proteggile dalla pioggia: spray impermeabilizzante."></td></tr>', esc_attr($field('_hive_bump_text')));
        printf('<tr><th scope="row"><label for="hive-bump-button">Pulsante</label></th><td><input id="hive-bump-button" class="regular-text" name="hive_bump_button" value="%s" placeholder="Aggiungi all’ordine"></td></tr>', esc_attr($field('_hive_bump_button')));

        echo '<tr><th scope="row">Quando</th><td><fieldset><legend class="screen-reader-text">Quando</legend><p class="description" style="margin-top:0">Nessuna categoria: per ogni carrello. Altrimenti, quando nel carrello c’è un prodotto di una di queste (o delle loro sottocategorie).</p><ul style="max-height:14em;overflow:auto;margin:.5em 0 0;padding:.4em .6em;border:1px solid #dcdcde;background:#fff">';
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']);
        foreach (is_array($terms) ? $terms : [] as $term) {
            printf(
                '<li><label><input type="checkbox" name="hive_bump_cats[]" value="%d"%s> %s</label></li>',
                (int) $term->term_id,
                checked(in_array((int) $term->term_id, $cats, true), true, false),
                esc_html($term->name)
            );
        }
        echo '</ul><p class="description">Con più offerte vale la prima che si applica, in ordine (Attributi → Ordine).</p></fieldset></td></tr></tbody></table>';
    }

    public static function save(int $post_id, \WP_Post $post): void
    {
        if (
            !isset($_POST[self::NONCE])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE])), 'hive_bump_save')
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || !current_user_can('edit_post', $post_id)
        ) {
            return;
        }
        update_post_meta($post_id, '_hive_bump_product', isset($_POST['hive_bump_product']) ? absint($_POST['hive_bump_product']) : 0);
        foreach (['headline', 'text', 'button'] as $key) {
            update_post_meta($post_id, '_hive_bump_' . $key, isset($_POST['hive_bump_' . $key]) ? sanitize_text_field(wp_unslash($_POST['hive_bump_' . $key])) : '');
        }
        $cats = isset($_POST['hive_bump_cats']) && is_array($_POST['hive_bump_cats']) ? array_values(array_filter(array_map('absint', wp_unslash($_POST['hive_bump_cats'])))) : [];
        update_post_meta($post_id, '_hive_bump_cats', $cats);
    }
}
