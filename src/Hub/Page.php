<?php
/**
 * The page the Store Hub's Vetrina edits: found, saved back as a revision,
 * and the caches that showed it emptied.
 *
 * @package Hive
 */

namespace Hive\Hub;

use Hive\Cache;
use Hive\Core\HubPage;
use Hive\Shop\Store;

defined('ABSPATH') || exit;

final class Page
{
    /** The page the Vetrina edits: the given id, else the static front page. */
    public static function get(mixed $page_id): \WP_Post|\WP_Error
    {
        $page_id = is_scalar($page_id) ? (int) $page_id : 0;
        if ($page_id <= 0) {
            $page_id = (int) get_option('page_on_front');
        }
        if ($page_id <= 0) {
            return new \WP_Error('ghb_no_front_page', 'Il sito non ha una homepage statica (Impostazioni → Lettura).', ['status' => 404]);
        }
        $page = get_post($page_id);
        if (!$page instanceof \WP_Post || 'page' !== $page->post_type) {
            return new \WP_Error('ghb_page_not_found', 'Pagina non trovata.', ['status' => 404]);
        }
        return $page;
    }

    /**
     * Save the page's content as edited here. It is written back exactly as
     * given (every change in it was made and checked by the bridge), so kses
     * must not re-filter the page's own markup (a <style> block included) for
     * a user without unfiltered_html. A normal wp_update_post, so the page
     * gets a revision; then the catalog generation moves on (the rails'
     * cached lists with it) and the page caches are emptied.
     *
     * @return \WP_Post|\WP_Error The page as saved.
     */
    public static function save(\WP_Post $page, string $content): \WP_Post|\WP_Error
    {
        $kses = false !== has_filter('content_save_pre', 'wp_filter_post_kses');
        if ($kses) {
            kses_remove_filters();
        }
        // wp_update_post() unslashes its input: slash it, or every \u0022 escape in
        // the block attributes would lose its backslash and break the page.
        $result = wp_update_post(['ID' => $page->ID, 'post_content' => wp_slash($content)], true);
        if ($kses) {
            kses_init_filters();
        }
        if (is_wp_error($result)) {
            return new \WP_Error('ghb_write_failed', $result->get_error_message(), ['status' => 500]);
        }

        Store::bump();
        self::purge((int) $page->ID);
        $saved = get_post($page->ID);
        return $saved instanceof \WP_Post ? $saved : new \WP_Error('ghb_write_failed', 'Pagina non trovata dopo il salvataggio.', ['status' => 500]);
    }

    /** A section's key in the page as saved, by the place it was written at. */
    public static function key_at(\WP_Post $page, string $path): ?string
    {
        foreach (HubPage::rails(parse_blocks($page->post_content)) as $key => $entry) {
            if ($entry['path'] === $path) {
                return $key;
            }
        }
        return null;
    }

    /**
     * After a write the sections change on the page and on the category
     * pages that follow them: every page cache is emptied (Cache), and a site
     * can follow on hive_hub_page_published for just this page.
     */
    public static function purge(int $page_id): void
    {
        clean_post_cache($page_id);
        Cache::purge_pages();
        do_action('hive_hub_page_published', $page_id);
    }
}
