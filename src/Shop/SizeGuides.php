<?php
/**
 * Size guides (in place of CommerceKit's Size Guides): Hive → Guide alle
 * taglie, each one written in the block editor (a table, a picture, notes).
 *
 * Where a guide shows: its own box on the edit screen lists categories
 * (their subcategories included) and brands, or marks it the default; a
 * product can pick a guide, or none, in Product data → General. Which guide
 * wins is Core\SizeGuidePick.
 *
 * On the product page: "Guida alle taglie" next to the sizes opens the
 * guide in a dialog (@hive/product). Its content is in the page in a
 * <template>, inert until opened: no request, and the page stays the same
 * for every visitor.
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Cache;
use Hive\Core\SizeGuidePick;
use Hive\Settings;

defined('ABSPATH') || exit;

final class SizeGuides
{
    public const TYPE         = 'hive_size_guide';
    public const PRODUCT_META = '_hive_size_guide';

    private const META_CATS    = '_hive_guide_cats';
    private const META_BRANDS  = '_hive_guide_brands';
    private const META_DEFAULT = '_hive_guide_default';
    private const NONCE        = 'hive_size_guide_nonce';
    private const CACHE        = 'hive_size_guides';

    private const ICON = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 8.5 8.5 3 21 15.5 15.5 21zm2.8 0 1.4 1.4 1.1-1.1 1.1 1.1-1.1 1.1 1.4 1.4 1.1-1.1 1.1 1.1-1.1 1.1 1.4 1.4 1.1-1.1 1.1 1.1-1.1 1.1 1.4 1.4 1.1-1.1 1.1 1.1-1.1 1.1 1.4 1.4 2.7-2.7L8.5 5.8z"/></svg>';

    public static function register(): void
    {
        add_action('init', [self::class, 'post_type']);
        add_action('add_meta_boxes_' . self::TYPE, [self::class, 'meta_box']);
        add_action('save_post_' . self::TYPE, [self::class, 'save'], 10, 2);
        add_action('save_post_' . self::TYPE, [self::class, 'changed'], 20);
        foreach (['trashed_post', 'untrashed_post', 'deleted_post'] as $hook) {
            add_action($hook, [self::class, 'maybe_changed']);
        }
        add_action('woocommerce_product_options_general_product_data', [self::class, 'product_field']);
        add_action('woocommerce_admin_process_product_object', [self::class, 'save_product']);
    }

    public static function post_type(): void
    {
        register_post_type(
            self::TYPE,
            [
                'labels'          => [
                    'name'               => 'Guide alle taglie',
                    'singular_name'      => 'Guida alle taglie',
                    'add_new'            => 'Aggiungi guida',
                    'add_new_item'       => 'Aggiungi una guida alle taglie',
                    'edit_item'          => 'Modifica la guida alle taglie',
                    'new_item'           => 'Nuova guida alle taglie',
                    'view_item'          => 'Vedi la guida',
                    'search_items'       => 'Cerca tra le guide',
                    'not_found'          => 'Nessuna guida alle taglie',
                    'not_found_in_trash' => 'Nessuna guida nel cestino',
                    'all_items'          => 'Guide alle taglie',
                    'menu_name'          => 'Guide alle taglie',
                ],
                'description'     => 'Le guide alle taglie della pagina del prodotto.',
                'public'          => false,
                'show_ui'         => true,
                'show_in_menu'    => Settings::PAGE,
                'show_in_rest'    => true,
                'supports'        => ['title', 'editor', 'revisions', 'page-attributes'],
                'capability_type' => 'product',
                'map_meta_cap'    => true,
                'rewrite'         => false,
                'query_var'       => false,
            ]
        );
    }

    /** The guide that shows on a product's page, if any. */
    public static function for_product(\WC_Product $product): ?\WP_Post
    {
        $guides = self::rules();
        if (!$guides) {
            return null;
        }
        $depths = [];
        foreach ($product->get_category_ids() as $cat) {
            $ancestors              = get_ancestors((int) $cat, 'product_cat', 'taxonomy');
            $depths[(int) $cat]     = count($ancestors);
            foreach ($ancestors as $index => $ancestor) {
                $depths[(int) $ancestor] = max($depths[(int) $ancestor] ?? 0, count($ancestors) - 1 - $index);
            }
        }
        $brands = [];
        foreach (Store::brand_taxonomies() as $taxonomy) {
            $brands = array_merge($brands, array_map('intval', wc_get_product_term_ids($product->get_id(), $taxonomy)));
        }
        $id = SizeGuidePick::pick($guides, $depths, $brands, (string) $product->get_meta(self::PRODUCT_META));
        $post = $id ? get_post($id) : null;
        return $post instanceof \WP_Post && 'publish' === $post->post_status ? $post : null;
    }

    /** The button that opens a guide. */
    public static function button(\WP_Post $guide): string
    {
        return sprintf(
            '<button type="button" class="hive-guide-button" data-hive-size-guide="%d" aria-haspopup="dialog">%s<span>Guida alle taglie</span></button>',
            $guide->ID,
            self::ICON
        );
    }

    /** The guide itself, inert in the page until the dialog opens. */
    public static function template(\WP_Post $guide): string
    {
        $content = (string) $guide->post_content;
        $content = has_blocks($content) ? do_blocks($content) : wpautop($content);
        $content = wp_filter_content_tags(do_shortcode(shortcode_unautop($content)));
        return sprintf(
            '<template id="hive-size-guide-%d" data-title="%s">%s</template>',
            $guide->ID,
            esc_attr(get_the_title($guide)),
            $content
        );
    }

    /**
     * Published guides and where they show, in order (menu order, then the
     * newest): [id => [cats, brands, default]], cached until a guide changes.
     *
     * @return array<int, array{cats: list<int>, brands: list<int>, default: bool}>
     */
    public static function rules(): array
    {
        $cached = get_transient(self::CACHE);
        if (is_array($cached)) {
            return $cached;
        }
        $rules = [];
        $posts = get_posts(
            [
                'post_type'        => self::TYPE,
                'post_status'      => 'publish',
                'numberposts'      => 200,
                'orderby'          => ['menu_order' => 'ASC', 'date' => 'DESC'],
                'suppress_filters' => true,
            ]
        );
        foreach ($posts as $post) {
            $rules[$post->ID] = [
                'cats'    => self::ids(get_post_meta($post->ID, self::META_CATS, true)),
                'brands'  => self::ids(get_post_meta($post->ID, self::META_BRANDS, true)),
                'default' => (bool) get_post_meta($post->ID, self::META_DEFAULT, true),
            ];
        }
        set_transient(self::CACHE, $rules, DAY_IN_SECONDS);
        return $rules;
    }

    /**
     * A guide changed. Product pages show the published ones, so when one
     * is or was published, the cached pages go too (not for a draft's
     * autosave).
     */
    public static function changed(mixed $post_id): void
    {
        $post_id = (int) $post_id;
        $before  = get_transient(self::CACHE);
        delete_transient(self::CACHE);
        if ('publish' === get_post_status($post_id) || !is_array($before) || isset($before[$post_id])) {
            Cache::purge_pages();
        }
    }

    public static function maybe_changed(mixed $post_id): void
    {
        if (self::TYPE === get_post_type((int) $post_id)) {
            self::changed($post_id);
        }
    }

    public static function meta_box(): void
    {
        add_meta_box('hive-size-guide-where', 'Dove si vede', [self::class, 'meta_box_html'], self::TYPE, 'side');
    }

    public static function meta_box_html(\WP_Post $post): void
    {
        wp_nonce_field('hive_size_guide_save', self::NONCE);
        $cats   = self::ids(get_post_meta($post->ID, self::META_CATS, true));
        $brands = self::ids(get_post_meta($post->ID, self::META_BRANDS, true));

        printf(
            '<p><label><input type="checkbox" name="hive_guide_default" value="1"%s> %s</label></p>',
            checked((bool) get_post_meta($post->ID, self::META_DEFAULT, true), true, false),
            'Guida predefinita: per i prodotti a cui nessun’altra guida si applica'
        );

        echo '<p><strong>Categorie</strong> <span class="description">(anche le sottocategorie)</span></p>';
        self::checklist('hive_guide_cats', self::category_rows(), $cats);

        $brand_rows = [];
        foreach (Store::brand_taxonomies() as $taxonomy) {
            $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name']);
            foreach (is_array($terms) ? $terms : [] as $term) {
                $brand_rows[] = [(int) $term->term_id, $term->name, 0];
            }
        }
        if ($brand_rows) {
            echo '<p><strong>Marche</strong></p>';
            self::checklist('hive_guide_brands', $brand_rows, $brands);
        }
        echo '<p class="description">Con categorie e marche insieme, la guida vale per i prodotti che hanno entrambe. Un prodotto può anche sceglierne una in Dati prodotto → Generale. Se più guide si applicano vince la più precisa, poi quella con l’ordine più basso (Attributi → Ordine).</p>';
    }

    public static function save(int $post_id, \WP_Post $post): void
    {
        if (
            !isset($_POST[self::NONCE])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE])), 'hive_size_guide_save')
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || !current_user_can('edit_post', $post_id)
        ) {
            return;
        }
        $cats   = isset($_POST['hive_guide_cats']) ? self::ids(wp_unslash($_POST['hive_guide_cats'])) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- self::ids() keeps whole numbers only.
        $brands = isset($_POST['hive_guide_brands']) ? self::ids(wp_unslash($_POST['hive_guide_brands'])) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- self::ids() keeps whole numbers only.
        update_post_meta($post_id, self::META_CATS, $cats);
        update_post_meta($post_id, self::META_BRANDS, $brands);
        update_post_meta($post_id, self::META_DEFAULT, empty($_POST['hive_guide_default']) ? 0 : 1);
    }

    /** Product data → General: which guide the product shows. */
    public static function product_field(): void
    {
        global $post;
        if (!$post instanceof \WP_Post) {
            return;
        }
        $options = ['' => 'Automatica (da categorie e marche)', 'none' => 'Nessuna'];
        foreach (get_posts(['post_type' => self::TYPE, 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC']) as $guide) {
            $options[(string) $guide->ID] = get_the_title($guide);
        }
        echo '<div class="options_group">';
        woocommerce_wp_select(
            [
                'id'          => self::PRODUCT_META,
                'label'       => 'Guida alle taglie',
                'options'     => $options,
                'value'       => (string) get_post_meta($post->ID, self::PRODUCT_META, true),
                'desc_tip'    => true,
                'description' => 'La guida che «Guida alle taglie» apre sulla pagina del prodotto (Hive → Guide alle taglie).',
            ]
        );
        echo '</div>';
    }

    public static function save_product(\WC_Product $product): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checked its own nonce (woocommerce_meta_nonce) before this hook.
        $value = isset($_POST[self::PRODUCT_META]) ? sanitize_text_field(wp_unslash($_POST[self::PRODUCT_META])) : '';
        if ('none' === $value || ('' !== $value && ctype_digit($value))) {
            $product->update_meta_data(self::PRODUCT_META, $value);
        } else {
            $product->delete_meta_data(self::PRODUCT_META);
        }
    }

    /** @return list<array{0: int, 1: string, 2: int}> [id, name, depth], parents before children. */
    private static function category_rows(): array
    {
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']);
        if (!is_array($terms)) {
            return [];
        }
        $children = [];
        foreach ($terms as $term) {
            $children[(int) $term->parent][] = $term;
        }
        $rows = [];
        $walk = static function (int $parent, int $depth) use (&$walk, &$rows, $children): void {
            foreach ($children[$parent] ?? [] as $term) {
                $rows[] = [(int) $term->term_id, $term->name, $depth];
                $walk((int) $term->term_id, $depth + 1);
            }
        };
        $walk(0, 0);
        return $rows;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}> $rows
     * @param list<int> $checked
     */
    private static function checklist(string $name, array $rows, array $checked): void
    {
        echo '<ul style="max-height:14em;overflow:auto;margin:0 0 1em;padding:.4em .6em;border:1px solid #dcdcde;background:#fff">';
        foreach ($rows as [$id, $label, $depth]) {
            printf(
                '<li style="margin:0 0 .25em %dem"><label><input type="checkbox" name="%s[]" value="%d"%s> %s</label></li>',
                (int) $depth,
                esc_attr($name),
                (int) $id,
                checked(in_array($id, $checked, true), true, false),
                esc_html($label)
            );
        }
        echo '</ul>';
    }

    /** @return list<int> */
    private static function ids(mixed $value): array
    {
        $ids = [];
        foreach (is_array($value) ? $value : [] as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }
        return array_values(array_unique($ids));
    }
}
