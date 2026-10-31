<?php
/**
 * The Store Hub's writes to the homepage (wc-gh/v1, see Routes):
 *
 *   POST /homepage/block    one block: a section's pin / exclude / fallback /
 *                           limit ("rail"), what it shows ("rail.source": a
 *                           category, brand or tag, or a shop-wide list),
 *                           and/or the block's editable fields ("attrs")
 *   POST /homepage/layout   the sections themselves: "insert" one next to a
 *                           section the Hub read, "remove" one, "arrange"
 *                           them in a new order
 *
 * Both refuse rather than guess, at every step: the page must be the one the
 * Hub read (modified time), every block named must be as the Hub read it
 * (path, name, attribute fingerprint), every delimiter is found exactly once
 * (Core\HubPage). Only those delimiters change, through wp_update_post, so
 * the page gets a revision. A block's name may come as the app knows it
 * (golden-hive/…) or as Hive's (hive/…).
 *
 * @package Hive
 */

namespace Hive\Hub;

use Hive\Core\HubNames;
use Hive\Core\HubPage;
use Hive\Core\HubRail;
use Hive\Core\RailOrder;
use Hive\Shop\RailQuery;

defined('ABSPATH') || exit;

final class Edits
{
    /** WordPress's block attribute serializer: the escaping the editor writes delimiters with. */
    private const SERIALIZE = 'serialize_block_attributes';

    public static function block(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $page = Page::get($request->get_param('page_id'));
        if (is_wp_error($page)) {
            return $page;
        }
        if (Routes::text($request, 'expected_modified_gmt') !== $page->post_modified_gmt) {
            return self::stale_page($page);
        }

        $path   = Routes::text($request, 'path');
        $blocks = parse_blocks($page->post_content);
        $block  = HubPage::block_at_path($blocks, $path);
        if (!$block || !is_string($block['blockName'] ?? null) || HubNames::block_from_app(Routes::text($request, 'block_name')) !== $block['blockName']) {
            return new \WP_Error('ghb_block_moved', 'La sezione non è più in quella posizione: ricarica.', ['status' => 409]);
        }
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        if (Routes::text($request, 'expected_attrs_hash') !== HubPage::attrs_hash($attrs)) {
            return new \WP_Error('ghb_block_changed', 'La sezione è stata modificata nel frattempo: ricarica.', ['status' => 409]);
        }
        $rail_input  = $request->get_param('rail');
        $field_input = $request->get_param('attrs');
        if (null === $rail_input && null === $field_input) {
            return new \WP_Error('ghb_bad_input', 'Niente da modificare.', ['status' => 400]);
        }

        $name      = $block['blockName'];
        $rail      = HubRail::from_block($block);
        $new_attrs = $attrs;
        $source    = null;

        if (null !== $rail_input) {
            if (null === $rail) {
                return new \WP_Error('ghb_not_a_rail', 'Questo blocco non è una sezione prodotti.', ['status' => 422]);
            }
            $values = self::rail_values($rail_input);
            if (is_wp_error($values)) {
                return $values;
            }
            // A new source replaces what the rail showed, a fixed list too (which
            // otherwise only WordPress's editor changes).
            if (is_array($rail_input) && array_key_exists('source', $rail_input)) {
                $source = Rails::checked_source($rail_input['source']);
                if (is_wp_error($source)) {
                    return $source;
                }
            } elseif (!empty($rail['atts']['ids'])) {
                return new \WP_Error('ghb_fixed_list', 'Questa sezione mostra una lista fissa di prodotti.', ['status' => 422]);
            }
            $base          = $source ? HubRail::set_source($rail['shortcode'], $source) : $rail['shortcode'];
            $new_shortcode = null === $base ? null : HubRail::set_atts($base, $values);
            if (null === $new_shortcode) {
                return new \WP_Error('ghb_unwritable', 'Lo shortcode della sezione non si può riscrivere in sicurezza.', ['status' => 422]);
            }
            $new_attrs['shortcode'] = $new_shortcode;
        }

        if (null !== $field_input) {
            $specs = Fields::specs();
            if (!is_array($field_input)) {
                return new \WP_Error('ghb_bad_input', 'I campi vanno inviati come oggetto.', ['status' => 400]);
            }
            if (empty($specs[$name]) || !is_array($specs[$name])) {
                return new \WP_Error('ghb_no_fields', 'Questo blocco non ha campi modificabili dalla Vetrina.', ['status' => 422]);
            }
            $applied = Fields::apply($name, $new_attrs, $field_input, $specs[$name]);
            if (!$applied['ok']) {
                return Fields::error($applied, $specs[$name]);
            }
            $new_attrs = $applied['attrs'];
        }

        // Pointed elsewhere, the section's button follows, unless this write sets it.
        $followed = null;
        if ($source && null !== $rail && !(is_array($field_input) && array_key_exists('buttonUrl', $field_input))) {
            $button   = isset($new_attrs['buttonUrl']) && is_scalar($new_attrs['buttonUrl']) ? (string) $new_attrs['buttonUrl'] : '';
            $followed = Rails::followed_button($button, Rails::terms(RailQuery::normalize($rail['atts'])), $source);
            if (null !== $followed) {
                $new_attrs['buttonUrl'] = $followed;
            }
        }

        $dry_run  = (bool) $request->get_param('dry_run');
        $after    = isset($new_attrs['shortcode']) && is_scalar($new_attrs['shortcode']) ? (string) $new_attrs['shortcode'] : '';
        $response = [
            'dry_run' => $dry_run,
            'changed' => $new_attrs !== $attrs,
            'path'    => $path,
            'before'  => $rail ? HubNames::shortcode_to_app($rail['shortcode']) : '',
            'after'   => $rail ? HubNames::shortcode_to_app($after) : '',
            'fields'  => (object) (Fields::of(['blockName' => $name, 'attrs' => $new_attrs]) ?? []),
        ];
        if (null !== $followed) {
            $response['button_url'] = $followed;
        }

        $replaced = HubPage::replace_block_attrs($page->post_content, $name, $attrs, $new_attrs, self::SERIALIZE);
        if (!$replaced['ok']) {
            return 'ambiguous' === $replaced['error'] ? self::not_unique() : self::not_found();
        }

        if ($dry_run || !$response['changed']) {
            $response['modified_gmt'] = $page->post_modified_gmt;
            $response['attrs_hash']   = HubPage::attrs_hash($attrs);
            return new \WP_REST_Response($response);
        }

        $page = Page::save($page, $replaced['content']);
        if (is_wp_error($page)) {
            return $page;
        }
        $response['modified_gmt'] = $page->post_modified_gmt;
        $response['attrs_hash']   = HubPage::attrs_hash($new_attrs);
        $response['rendered']     = $rail ? RailQuery::ids(RailQuery::normalize(HubRail::parse_shortcode($after)['atts'] ?? []), true) : [];
        if ($rail) {
            // What the section is called now: a new source is a new key.
            $response['key'] = Page::key_at($page, $path);
        }
        return new \WP_REST_Response($response);
    }

    public static function layout(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $page = Page::get($request->get_param('page_id'));
        if (is_wp_error($page)) {
            return $page;
        }
        if (Routes::text($request, 'expected_modified_gmt') !== $page->post_modified_gmt) {
            return self::stale_page($page);
        }

        $rails  = HubPage::rails(parse_blocks($page->post_content));
        $action = Routes::text($request, 'action');
        $added  = null;

        switch ($action) {
            case 'insert':
                $anchor = self::section($rails, $request->get_param('anchor'));
                if (is_wp_error($anchor)) {
                    return $anchor;
                }
                $added = self::new_section($request->get_param('rail'), $request->get_param('attrs'), $anchor['rail']['atts']);
                if (is_wp_error($added)) {
                    return $added;
                }
                $place = 'before' === $request->get_param('place') ? 'before' : 'after';
                $edit  = HubPage::insert_block($page->post_content, $anchor['block'], $place, $added, self::SERIALIZE);
                break;

            case 'remove':
                $target = self::section($rails, $request->get_param('target'));
                if (is_wp_error($target)) {
                    return $target;
                }
                // The last one stays: a new section is always placed next to another.
                if (count($rails) <= 1) {
                    return new \WP_Error('ghb_last_rail', 'È l\'ultima sezione di prodotti della homepage: si toglie dall\'editor di WordPress.', ['status' => 422]);
                }
                $edit = HubPage::remove_block($page->post_content, $target['block'], self::SERIALIZE);
                break;

            case 'arrange':
                $wanted = $request->get_param('order');
                if (!is_array($wanted) || count($wanted) !== count($rails)) {
                    return new \WP_Error('ghb_stale_page', 'Le sezioni della homepage sono cambiate nel frattempo: ricarica.', ['status' => 409]);
                }
                $slots = array_values($rails);
                $paths = array_column($slots, 'path');
                $order = [];
                foreach ($wanted as $ref) {
                    $entry = self::section($rails, $ref);
                    if (is_wp_error($entry)) {
                        return $entry;
                    }
                    $order[] = (int) array_search($entry['path'], $paths, true);
                }
                $edit = HubPage::arrange_blocks($page->post_content, array_column($slots, 'block'), $order, self::SERIALIZE);
                break;

            default:
                return new \WP_Error('ghb_bad_input', 'Azione non riconosciuta.', ['status' => 400]);
        }
        if (!$edit['ok']) {
            return self::layout_error($edit);
        }

        $dry_run  = (bool) $request->get_param('dry_run');
        $response = [
            'dry_run' => $dry_run,
            'changed' => $edit['content'] !== $page->post_content,
            'action'  => $action,
        ];
        if ($dry_run || !$response['changed']) {
            $response['modified_gmt'] = $page->post_modified_gmt;
            return new \WP_REST_Response($response);
        }

        $page = Page::save($page, $edit['content']);
        if (is_wp_error($page)) {
            return $page;
        }
        $response['modified_gmt'] = $page->post_modified_gmt;
        if ($added) {
            // The new section as the page now reads it: its key and its place.
            foreach (HubPage::rails(parse_blocks($page->post_content)) as $key => $entry) {
                if ($entry['block']['attrs'] === $added['attrs']) {
                    $response['key']  = $key;
                    $response['path'] = $entry['path'];
                    break;
                }
            }
        }
        return new \WP_REST_Response($response);
    }

    /**
     * The rail values a write sends, checked.
     *
     * @return array{pin: list<int>, exclude: list<int>, fallback: string, limit: int|null}|\WP_Error
     */
    private static function rail_values(mixed $input): array|\WP_Error
    {
        if (!is_array($input)) {
            return new \WP_Error('ghb_bad_input', 'Dati della sezione mancanti.', ['status' => 400]);
        }
        foreach (['pin', 'exclude'] as $key) {
            if (isset($input[$key]) && !is_array($input[$key])) {
                return new \WP_Error('ghb_bad_input', "{$key} deve essere una lista di id.", ['status' => 400]);
            }
            if (isset($input[$key]) && count($input[$key]) > RailOrder::MAX_IDS) {
                return new \WP_Error('ghb_too_many', "{$key}: al massimo " . RailOrder::MAX_IDS . ' prodotti.', ['status' => 400]);
            }
        }
        $fallback = $input['fallback'] ?? '';
        if (!is_scalar($fallback) || ('' !== (string) $fallback && !in_array((string) $fallback, RailOrder::FALLBACKS, true))) {
            return new \WP_Error('ghb_bad_input', 'Ordinamento non riconosciuto.', ['status' => 400]);
        }
        $limit = null;
        if (isset($input['limit'])) {
            $limit = filter_var($input['limit'], FILTER_VALIDATE_INT);
            if (false === $limit || $limit < 1 || $limit > RailOrder::MAX_IDS) {
                return new \WP_Error('ghb_bad_input', 'Numero di prodotti non valido (da 1 a ' . RailOrder::MAX_IDS . ').', ['status' => 400]);
            }
        }
        return [
            'pin'      => RailOrder::parse_ids($input['pin'] ?? []),
            'exclude'  => RailOrder::parse_ids($input['exclude'] ?? []),
            'fallback' => (string) $fallback,
            'limit'    => $limit,
        ];
    }

    /**
     * A section of the page as the Hub read it ({ path, attrs_hash }), or why
     * it is not that any more.
     *
     * @return array|\WP_Error Core\HubPage::rails() entry, with its "key".
     */
    private static function section(array $rails, mixed $ref): array|\WP_Error
    {
        if (!is_array($ref)) {
            return new \WP_Error('ghb_bad_input', 'Sezione mancante.', ['status' => 400]);
        }
        $path = is_scalar($ref['path'] ?? null) ? (string) $ref['path'] : '';
        $hash = is_scalar($ref['attrs_hash'] ?? null) ? (string) $ref['attrs_hash'] : '';
        foreach ($rails as $key => $entry) {
            if ($entry['path'] !== $path) {
                continue;
            }
            if ($hash !== HubPage::attrs_hash($entry['block']['attrs'])) {
                return new \WP_Error('ghb_block_changed', 'La sezione è stata modificata nel frattempo: ricarica.', ['status' => 409]);
            }
            return $entry + ['key' => $key];
        }
        return new \WP_Error('ghb_block_moved', 'La sezione non è più in quella posizione: ricarica.', ['status' => 409]);
    }

    /**
     * A new section's block from what the Hub sends, every value checked:
     * what it shows (a term must exist in the shop), how many products, its
     * layout (the section it is placed next to, unless given), its pins,
     * exclusions and fallback, and its texts: the same fields, cleaned the
     * same way, as an edit.
     *
     * @return array{blockName: string, attrs: array}|\WP_Error
     */
    private static function new_section(mixed $rail_input, mixed $field_input, array $neighbour_atts): array|\WP_Error
    {
        if (!is_array($rail_input)) {
            return new \WP_Error('ghb_bad_input', 'Dati della sezione mancanti.', ['status' => 400]);
        }
        $source = Rails::checked_source($rail_input['source'] ?? null);
        if (is_wp_error($source)) {
            return $source;
        }
        $values = self::rail_values($rail_input);
        if (is_wp_error($values)) {
            return $values;
        }
        $limit     = $values['limit'] ?? max(1, (int) ($neighbour_atts['limit'] ?? 12));
        $layout    = isset($rail_input['layout']) && is_array($rail_input['layout']) ? $rail_input['layout'] : [];
        $shortcode = HubRail::new_shortcode($source, $limit, $neighbour_atts, $layout, $values);
        if (null === $shortcode) {
            return new \WP_Error('ghb_unwritable', 'Lo shortcode della sezione non si può scrivere in sicurezza.', ['status' => 422]);
        }
        $spec    = Fields::specs()[HubRail::BLOCK] ?? [];
        $spec    = is_array($spec) ? $spec : [];
        $applied = Fields::apply(HubRail::BLOCK, [], is_array($field_input) ? $field_input : [], $spec);
        if (!$applied['ok']) {
            return Fields::error($applied, $spec);
        }
        return ['blockName' => HubRail::BLOCK, 'attrs' => HubRail::new_attrs($shortcode, $applied['attrs'])];
    }

    /** Why a layout edit was refused, in the words the Hub shows. */
    private static function layout_error(array $edit): \WP_Error
    {
        switch ($edit['error'] ?? '') {
            case 'duplicate':
                return new \WP_Error('ghb_duplicate_section', 'Nella homepage c\'è già una sezione identica: cambia il titolo o cosa mostra.', ['status' => 422]);
            case 'ambiguous':
                return self::not_unique();
            case 'not_a_permutation':
                return new \WP_Error('ghb_bad_input', 'Ogni sezione va messa una volta sola.', ['status' => 400]);
            default:
                return self::not_found();
        }
    }

    private static function stale_page(\WP_Post $page): \WP_Error
    {
        return new \WP_Error('ghb_stale_page', 'La homepage è stata modificata nel frattempo: ricarica.', ['status' => 409, 'modified_gmt' => $page->post_modified_gmt]);
    }

    private static function not_unique(): \WP_Error
    {
        return new \WP_Error('ghb_block_not_unique', 'La stessa sezione compare più volte nella pagina: modificala dall\'editor di WordPress.', ['status' => 422]);
    }

    private static function not_found(): \WP_Error
    {
        return new \WP_Error('ghb_block_not_unique', 'La sezione non si trova nel contenuto salvato: ricarica.', ['status' => 422]);
    }
}
