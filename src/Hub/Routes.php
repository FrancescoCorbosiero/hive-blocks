<?php
/**
 * The Store Hub bridge: the REST API the Hub app's "Vetrina" edits the
 * homepage through, wc-gh/v1, as golden-hive-blocks served it
 * (includes/hub-rails.php). The app isn't changing, so neither is the
 * contract: routes, methods, parameters, response shapes, error codes,
 * features and API version are the same. What changed is underneath: the
 * page holds Hive's blocks and [hive_product_rail], reported to the app
 * under their old names (Core\HubNames); rails are Shop\RailQuery's.
 *
 *   GET  /capabilities       what the bridge can do
 *   GET  /homepage           the page's blocks; its sections with their products
 *   GET  /rail               one section: every product, hidden ones with why
 *   GET  /products           cards for up to 100 products
 *   POST /homepage/block     one block: a section's order, source, fields
 *   GET  /homepage/history   a section's earlier states, from the revisions
 *   POST /homepage/layout    add, remove, arrange sections
 *
 * WooCommerce authenticates any "wc-*" namespace with its REST API keys, so
 * the Hub uses the consumer key it already holds. Every route requires
 * manage_woocommerce. The writes are in Edits.
 *
 * @package Hive
 */

namespace Hive\Hub;

use Hive\Core\HubNames;
use Hive\Core\HubPage;
use Hive\Core\RailOrder;
use Hive\Shop\RailQuery;

defined('ABSPATH') || exit;

final class Routes
{
    /** REST namespace of the bridge. */
    public const NAMESPACE = 'wc-gh/v1';

    /** Bumped when a response shape changes incompatibly. */
    public const API = 1;

    /**
     * The bridge as /capabilities names it: the golden-hive-blocks release
     * whose wc-gh/v1 this one reproduces. The app reads these; Hive's own
     * version is not part of the contract.
     */
    public const PLUGIN         = 'golden-hive-blocks';
    public const PLUGIN_VERSION = '5.15.0';

    /** What this bridge can do: the Hub offers only that. */
    public const FEATURES = [
        'homepage', 'rail', 'rail-visible', 'products', 'block-write', 'history',
        'rail-pin', 'rail-exclude', 'rail-fallback', 'rail-limit', 'block-fields', 'archive-follow',
        'rail-source', 'rail-hidden', 'layout-insert', 'layout-remove', 'layout-arrange',
    ];

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void
    {
        // golden-hive-blocks still active answers wc-gh/v1 itself (its routes come first): this one stays out.
        if (function_exists('ghb_hub_register_routes')) {
            return;
        }
        $read  = ['methods' => \WP_REST_Server::READABLE, 'permission_callback' => [self::class, 'permission']];
        $write = ['methods' => \WP_REST_Server::EDITABLE, 'permission_callback' => [self::class, 'permission']];

        register_rest_route(self::NAMESPACE, '/capabilities', $read + ['callback' => [self::class, 'capabilities']]);
        register_rest_route(
            self::NAMESPACE,
            '/homepage',
            $read + [
                'callback' => [self::class, 'homepage'],
                'args'     => ['page_id' => ['type' => 'integer', 'required' => false]],
            ]
        );
        register_rest_route(
            self::NAMESPACE,
            '/rail',
            $read + [
                'callback' => [self::class, 'rail'],
                'args'     => [
                    'page_id'  => ['type' => 'integer', 'required' => false],
                    // Either the block path read from /homepage, or the section's
                    // stable key ("category:saldi-sneakers-outlet#0").
                    'path'     => ['type' => 'string', 'required' => false],
                    'key'      => ['type' => 'string', 'required' => false],
                    'offset'   => ['type' => 'integer', 'required' => false, 'default' => 0, 'minimum' => 0],
                    'count'    => ['type' => 'integer', 'required' => false, 'default' => 60, 'minimum' => 1, 'maximum' => 200],
                    // Preview another automatic order without saving it.
                    'fallback' => ['type' => 'string', 'required' => false, 'enum' => RailOrder::FALLBACKS],
                ],
            ]
        );
        register_rest_route(
            self::NAMESPACE,
            '/products',
            $read + [
                'callback' => [self::class, 'products'],
                'args'     => ['ids' => ['type' => 'string', 'required' => true]],
            ]
        );
        register_rest_route(self::NAMESPACE, '/homepage/block', $write + ['callback' => [Edits::class, 'block']]);
        register_rest_route(
            self::NAMESPACE,
            '/homepage/history',
            $read + [
                'callback' => [self::class, 'history'],
                'args'     => [
                    'page_id' => ['type' => 'integer', 'required' => false],
                    'key'     => ['type' => 'string', 'required' => true],
                ],
            ]
        );
        register_rest_route(self::NAMESPACE, '/homepage/layout', $write + ['callback' => [Edits::class, 'layout']]);
    }

    public static function permission(): bool|\WP_Error
    {
        if (current_user_can('manage_woocommerce')) {
            return true;
        }
        return new \WP_Error('ghb_forbidden', 'Servono i permessi manage_woocommerce.', ['status' => rest_authorization_required_code()]);
    }

    public static function capabilities(): \WP_REST_Response
    {
        return new \WP_REST_Response(
            [
                'plugin'            => self::PLUGIN,
                'version'           => self::PLUGIN_VERSION,
                'api'               => self::API,
                'features'          => self::FEATURES,
                'rail_types'        => RailOrder::TYPES,
                'fields'            => (object) Fields::app_specs(),
                'fallbacks'         => RailOrder::FALLBACKS,
                'max_ids'           => RailOrder::MAX_IDS,
                'hide_out_of_stock' => RailQuery::hides_out_of_stock(),
                'front_page_id'     => (int) get_option('page_on_front'),
                'site_url'          => home_url('/'),
            ]
        );
    }

    /** The page's blocks in order: each section with its settings and products, every other block summarised. */
    public static function homepage(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $page = Page::get($request->get_param('page_id'));
        if (is_wp_error($page)) {
            return $page;
        }
        $blocks  = parse_blocks($page->post_content);
        $rails   = HubPage::rails($blocks);
        $by_path = [];
        foreach ($rails as $key => $entry) {
            $by_path[$entry['path']] = $key;
        }

        $items = [];
        foreach (HubPage::leaf_blocks($blocks) as [$path, $block]) {
            $name = HubNames::block_to_app((string) $block['blockName']);
            if (isset($by_path[$path])) {
                $key     = $by_path[$path];
                $items[] = [
                    'path' => $path,
                    'name' => $name,
                    'kind' => 'rail',
                    'rail' => Rails::payload($key, $path, $block, $rails[$key]['rail'], true),
                ];
                continue;
            }
            $item = [
                'path'    => $path,
                'name'    => $name,
                'kind'    => 'static',
                'summary' => HubPage::block_summary($block),
            ];
            $fields = Fields::of($block);
            if (null !== $fields) {
                // Editable from the Vetrina: its fields, and the fingerprint a write must quote back.
                $item['fields']     = $fields;
                $item['attrs_hash'] = HubPage::attrs_hash((array) $block['attrs']);
            }
            $items[] = $item;
        }

        return new \WP_REST_Response(
            [
                'page_id'      => (int) $page->ID,
                'title'        => get_the_title($page),
                'link'         => get_permalink($page),
                'edit_link'    => admin_url('post.php?post=' . $page->ID . '&action=edit'),
                'modified_gmt' => $page->post_modified_gmt,
                // With the page, so the Hub knows what it may offer without asking twice.
                'features'     => self::FEATURES,
                'blocks'       => $items,
            ]
        );
    }

    /** One section, by path or key: its products a page at a time, and the ones hidden from it. */
    public static function rail(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $page = Page::get($request->get_param('page_id'));
        if (is_wp_error($page)) {
            return $page;
        }
        $path   = self::text($request, 'path');
        $wanted = self::text($request, 'key');
        $found  = null;
        foreach (HubPage::rails(parse_blocks($page->post_content)) as $rail_key => $entry) {
            if (('' !== $wanted && $rail_key === $wanted) || ('' === $wanted && $entry['path'] === $path)) {
                $found = $entry + ['key' => $rail_key];
                break;
            }
        }
        if (null === $found) {
            return new \WP_Error('ghb_not_a_rail', 'Questo blocco non è una sezione prodotti.', ['status' => 404]);
        }
        $rail = $found['rail'];

        $atts     = RailQuery::normalize($rail['atts']);
        $terms    = Rails::terms($atts);
        $preview  = self::text($request, 'fallback');
        $fallback = '' !== $preview ? RailOrder::fallback($preview, $rail['atts']) : $rail['fallback'];
        $visible  = RailQuery::visible_ids(RailQuery::query_args($atts), $fallback, true);
        $ordered  = RailOrder::order($visible, $rail['pin'], $rail['exclude']);
        $offset   = (int) $request->get_param('offset');
        $count    = (int) $request->get_param('count');

        $pinned = array_fill_keys($rail['pin'], true);
        $items  = [];
        foreach (Rails::cards(array_slice($ordered, $offset, $count)) as $i => $card) {
            $card['position'] = $offset + $i + 1;
            $card['pinned']   = isset($pinned[$card['id']]);
            $items[]          = $card;
        }

        // Hidden products the Vetrina must still show: excluded members (so they
        // can be brought back) and pins the site cannot show (with the reason).
        $visible_set = array_fill_keys($visible, true);
        $hidden_ids  = [];
        foreach ($rail['exclude'] as $id) {
            if (isset($visible_set[$id])) {
                $hidden_ids[$id] = 'excluded';
            }
        }
        foreach ($rail['pin'] as $id) {
            if (!isset($visible_set[$id]) && !isset($hidden_ids[$id])) {
                $hidden_ids[$id] = Rails::hidden_reason((int) $id, $atts, $terms);
            }
        }
        $hidden = [];
        foreach (Rails::cards(array_keys($hidden_ids)) as $card) {
            $card['reason'] = $hidden_ids[$card['id']];
            $card['pinned'] = isset($pinned[$card['id']]);
            $hidden[]       = $card;
        }

        return new \WP_REST_Response(
            array_merge(
                Rails::payload($found['key'], $found['path'], $found['block'], $rail, false),
                [
                    'page_id'           => (int) $page->ID,
                    'page_link'         => get_permalink($page),
                    'modified_gmt'      => $page->post_modified_gmt,
                    'hide_out_of_stock' => RailQuery::hides_out_of_stock($atts),
                    'total'             => count($ordered),
                    'offset'            => $offset,
                    // Every visible member in automatic order (pins not applied): with it the
                    // Hub recomputes the rail after any pin or exclusion locally, by the same
                    // rule as Core\RailOrder::order().
                    'visible'           => $visible,
                    'preview_fallback'  => $fallback,
                    'items'             => $items,
                    'hidden'            => $hidden,
                ]
            )
        );
    }

    /** Cards for up to 100 products by id: what the editor needs when a product scrolls into view. */
    public static function products(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(['products' => Rails::cards(RailOrder::parse_ids(self::text($request, 'ids')))]);
    }

    /**
     * Earlier states of one section, newest first, from the page's revisions.
     * The section is found by key (what it shows), not by path, so a block
     * added above it later does not hide its history; revisions from before
     * the switch-over count too (golden-hive-blocks' names).
     */
    public static function history(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $page = Page::get($request->get_param('page_id'));
        if (is_wp_error($page)) {
            return $page;
        }
        $key    = self::text($request, 'key');
        $states = [];
        $last   = null;
        foreach (wp_get_post_revisions($page->ID, ['posts_per_page' => 30]) as $revision) {
            if (!$revision instanceof \WP_Post) {
                continue;
            }
            $rails = HubPage::rails(parse_blocks($revision->post_content), true);
            if (!isset($rails[$key])) {
                continue;
            }
            $rail  = $rails[$key]['rail'];
            $state = ['pin' => $rail['pin'], 'exclude' => $rail['exclude'], 'fallback' => $rail['fallback']];
            if ($state === $last) {
                continue; // An edit elsewhere on the page: nothing changed for this section.
            }
            $last     = $state;
            $author   = get_userdata((int) $revision->post_author);
            $states[] = array_merge(
                $state,
                [
                    'revision_id' => (int) $revision->ID,
                    'date_gmt'    => $revision->post_modified_gmt,
                    'author'      => $author instanceof \WP_User ? $author->display_name : '',
                ]
            );
        }
        return new \WP_REST_Response(['key' => $key, 'states' => $states]);
    }

    /** A request parameter as text; '' when missing or not a single value. */
    public static function text(\WP_REST_Request $request, string $name): string
    {
        $value = $request->get_param($name);
        return is_scalar($value) ? (string) $value : '';
    }
}
