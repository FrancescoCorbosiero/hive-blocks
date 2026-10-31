<?php
/**
 * Drops: a product's release date (module "Drop").
 *
 * Product data → General → "Drop: uscita", the date and time it goes out,
 * and "Blocca fino al drop" (on by default). Until then:
 *
 *   the product can't be bought (WooCommerce's is_purchasable, variations
 *   too), so neither a cached page nor a direct add to cart jumps the gun
 *   its card says "In arrivo · 24 ott" (hive_card_state), and so do Quick
 *   View and Quick Add
 *   its page shows the release with a live countdown (@hive/drop) and
 *   "Ricordamelo": Google Calendar, or an .ics file for Apple Calendar,
 *   Outlook and phones (Core\Ics, served at ?hive_drop_ics=ID)
 *
 * At the release a scheduled action (Action Scheduler, WP-Cron without it)
 * empties the page caches and moves the catalog generation, so cached "In
 * arrivo" pages and product data go; a product page left open reloads
 * itself; the size alerts hear about the sizes that came out.
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Assets;
use Hive\Cache;
use Hive\Core\Ics;

defined('ABSPATH') || exit;

final class Drops
{
    public const HOOK = 'hive_drop_release';
    public const META_TS   = '_hive_drop_ts';
    public const META_LOCK = '_hive_drop_lock';

    public static function register(): void
    {
        add_action('woocommerce_product_options_general_product_data', [self::class, 'fields']);
        add_action('woocommerce_admin_process_product_object', [self::class, 'save']);
        add_action(self::HOOK, [self::class, 'release']);
        add_filter('woocommerce_is_purchasable', [self::class, 'purchasable'], 20, 2);
        add_filter('woocommerce_variation_is_purchasable', [self::class, 'purchasable'], 20, 2);
        add_filter('hive_card_state', [self::class, 'card_state'], 10, 2);
        add_action('template_redirect', [self::class, 'ics'], 0);
    }

    /** UTC timestamp of a product's drop, 0 when it has none. */
    public static function ts(int $product_id): int
    {
        return (int) get_post_meta($product_id, self::META_TS, true);
    }

    /** Not out yet, and not for sale until it is. */
    public static function locked(int $product_id): bool
    {
        return self::ts($product_id) > time() && 'no' !== get_post_meta($product_id, self::META_LOCK, true);
    }

    /** "venerdì 24 ottobre · ore 10:00", in the site's time zone. */
    public static function long_date(int $ts): string
    {
        return wp_date('l j F', $ts) . ' · ore ' . wp_date('H:i', $ts);
    }

    public static function fields(): void
    {
        global $post;
        $id = $post instanceof \WP_Post ? (int) $post->ID : 0;
        $ts = $id ? self::ts($id) : 0;

        echo '<div class="options_group">';
        woocommerce_wp_text_input(
            [
                'id'          => '_hive_drop_at',
                'label'       => 'Drop: uscita',
                'type'        => 'datetime-local',
                'value'       => $ts ? wp_date('Y-m-d\TH:i', $ts) : '',
                'desc_tip'    => true,
                'description' => 'Data e ora (fuso del sito) in cui il prodotto esce. Fino ad allora la scheda dice «In arrivo» e la pagina mostra il conto alla rovescia e «Ricordamelo». Vuoto: nessun drop.',
            ]
        );
        woocommerce_wp_checkbox(
            [
                'id'          => '_hive_drop_lock',
                'label'       => 'Blocca fino al drop',
                'value'       => $id && '' !== get_post_meta($id, self::META_LOCK, true) ? (string) get_post_meta($id, self::META_LOCK, true) : 'yes',
                'description' => 'Non acquistabile prima dell’uscita (consigliato).',
            ]
        );
        echo '</div>';
    }

    public static function save(\WC_Product $product): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce checked its own nonce (woocommerce_meta_nonce) before this hook.
        $raw  = isset($_POST['_hive_drop_at']) ? sanitize_text_field(wp_unslash($_POST['_hive_drop_at'])) : '';
        $lock = isset($_POST['_hive_drop_lock']) ? 'yes' : 'no';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $ts = 0;
        if ('' !== $raw) {
            $date = date_create_immutable_from_format('Y-m-d\TH:i', $raw, wp_timezone());
            $ts   = $date ? $date->getTimestamp() : 0;
        }
        if ($ts) {
            $product->update_meta_data(self::META_TS, $ts);
        } else {
            $product->delete_meta_data(self::META_TS);
        }
        $product->update_meta_data(self::META_LOCK, $lock);
        if ($product->get_id()) {
            self::schedule($product->get_id(), $ts);
        }
    }

    /** One release action per product, at its drop time (none for a past drop or none). */
    public static function schedule(int $product_id, int $ts): void
    {
        $args = ['product_id' => $product_id];
        if (function_exists('as_unschedule_all_actions') && function_exists('as_schedule_single_action')) {
            as_unschedule_all_actions(self::HOOK, $args, 'hive');
            if ($ts > time()) {
                as_schedule_single_action($ts + 5, self::HOOK, $args, 'hive');
            }
            return;
        }
        wp_clear_scheduled_hook(self::HOOK, [$product_id]);
        if ($ts > time()) {
            wp_schedule_single_event($ts + 5, self::HOOK, [$product_id]);
        }
    }

    /** The release: cached pages and product data go, the size alerts hear about it. */
    public static function release(mixed $product_id): void
    {
        $product = wc_get_product((int) $product_id);
        if (!$product instanceof \WC_Product) {
            return;
        }
        wc_delete_product_transients($product->get_id());
        clean_post_cache($product->get_id());
        Store::bump();
        Cache::purge_pages();
        do_action('hive_drop_released', $product->get_id());
    }

    public static function purchasable(mixed $purchasable, mixed $product): mixed
    {
        if (!$purchasable || !$product instanceof \WC_Product) {
            return $purchasable;
        }
        $id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
        return self::locked($id) ? false : $purchasable;
    }

    /** A card of a drop not out yet: "In arrivo · 24 ott", nothing to press. */
    public static function card_state(mixed $state, mixed $product): mixed
    {
        if ($product instanceof \WC_Product && self::locked($product->get_id())) {
            return ['kind' => 'soon', 'label' => 'In arrivo · ' . wp_date('j M', self::ts($product->get_id()))];
        }
        return $state;
    }

    /** The calendar event of a product's drop. */
    public static function event(\WC_Product $product): array
    {
        $ts = self::ts($product->get_id());
        return [
            'uid'   => 'drop-' . $product->get_id() . '-' . $ts . '@' . wp_parse_url(home_url(), PHP_URL_HOST),
            'ts'    => $ts,
            'title' => 'Drop: ' . wp_strip_all_tags($product->get_name()),
            'url'   => $product->get_permalink(),
        ];
    }

    /**
     * The .ics link. A product's drop is found by its id; an event written in
     * a block (no product) is signed, so the endpoint never serves a calendar
     * file with someone else's words. The release time is in the URL, so a
     * changed date makes a new URL and caches can keep the file long.
     */
    public static function ics_url(array $event, int $product_id = 0): string
    {
        $args = ['hive_drop_ics' => $product_id, 'ts' => (int) $event['ts']];
        if (!$product_id) {
            $args['title'] = sanitize_text_field((string) $event['title']);
            $args['url']   = esc_url_raw((string) ($event['url'] ?? ''));
            $args['sig']   = self::signature($args['ts'], $args['title'], $args['url']);
        }
        return add_query_arg(array_map('rawurlencode', array_map('strval', $args)), home_url('/'));
    }

    private static function signature(int $ts, string $title, string $url): string
    {
        return substr(wp_hash($ts . '|' . $title . '|' . $url, 'nonce'), 0, 16);
    }

    /** ?hive_drop_ics=…: the reminder file. Public and the same for everyone, so caches may keep it. */
    public static function ics(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- a public, read-only file; a block's event is checked by its signature.
        if (!isset($_GET['hive_drop_ics'])) {
            return;
        }
        $id    = absint($_GET['hive_drop_ics']);
        $event = null;
        if ($id) {
            $product = wc_get_product($id);
            if ($product instanceof \WC_Product && 'publish' === $product->get_status() && self::ts($id)) {
                $event = self::event($product);
            }
        } else {
            $ts    = isset($_GET['ts']) ? absint($_GET['ts']) : 0;
            $title = isset($_GET['title']) && is_string($_GET['title']) ? sanitize_text_field(wp_unslash($_GET['title'])) : '';
            $url   = isset($_GET['url']) && is_string($_GET['url']) ? esc_url_raw(wp_unslash($_GET['url'])) : '';
            $sig   = isset($_GET['sig']) && is_string($_GET['sig']) ? sanitize_key(wp_unslash($_GET['sig'])) : '';
            if ($ts && '' !== $title && hash_equals(self::signature($ts, $title, $url), $sig)) {
                $event = ['uid' => 'drop-' . $ts . '-' . md5($title) . '@' . wp_parse_url(home_url(), PHP_URL_HOST), 'ts' => $ts, 'title' => $title, 'url' => $url];
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if (!$event) {
            status_header(404);
            header('Cache-Control: public, max-age=300');
            exit;
        }
        status_header(200);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="drop-' . gmdate('Ymd', (int) $event['ts']) . '.ics"');
        header('Cache-Control: public, max-age=3600, s-maxage=86400');
        echo Ics::calendar($event, time()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- an iCalendar file, built and escaped for that format by Core\Ics.
        exit;
    }

    /** "Ricordamelo": a <details> menu, so it works without JavaScript. */
    public static function remind(array $event, string $ics_url): string
    {
        return sprintf(
            '<details class="hive-remind"><summary class="hive-remind__button"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg><span>Ricordamelo</span></summary><div class="hive-remind__menu"><a href="%s" target="_blank" rel="noopener noreferrer">Google Calendar</a><a href="%s">Apple, Outlook, altro (.ics)</a></div></details>',
            esc_url(Ics::google_url($event)),
            esc_url($ics_url)
        );
    }

    /**
     * The product page's drop panel, while the drop is ahead: the date, the
     * countdown, "Ricordamelo". A locked drop says the purchase opens at
     * the release, and the page reloads then.
     */
    public static function panel(\WC_Product $product): string
    {
        $ts = self::ts($product->get_id());
        if ($ts <= time()) {
            return '';
        }
        Assets::use_module('@hive/drop');
        $locked = self::locked($product->get_id());
        $event  = self::event($product);
        $cells  = '';
        foreach (['d' => 'giorni', 'h' => 'ore', 'm' => 'min', 's' => 'sec'] as $unit => $label) {
            $cells .= sprintf('<span class="hive-drop__cell"><b data-unit="%s">00</b><small>%s</small></span>', $unit, $label);
        }
        return sprintf(
            '<div class="hive-drop" data-hive-drop="%d"%s><p class="hive-drop__head"><span class="hive-drop__eyebrow">Drop</span><span class="hive-drop__date">%s</span></p><div class="hive-drop__timer" role="timer" aria-label="Tempo all’uscita">%s</div>%s<div data-hive-drop-before>%s</div></div>',
            $ts,
            $locked ? ' data-hive-drop-reload' : '',
            esc_html(self::long_date($ts)),
            $cells,
            $locked ? '<p class="hive-drop__note" data-hive-drop-before>L’acquisto si apre all’uscita: questa pagina si aggiorna da sola.</p><p class="hive-drop__note" data-hive-drop-after hidden>È uscito: un attimo, la pagina si aggiorna…</p>' : '',
            self::remind($event, self::ics_url($event, $product->get_id()))
        );
    }

    /**
     * Products with a drop from $past_days days ago on, in date order (the
     * drop calendar block).
     *
     * @return list<\WC_Product>
     */
    public static function upcoming(int $limit, int $past_days = 0): array
    {
        $query = new \WP_Query(
            [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => max(1, min(24, $limit)),
                'no_found_rows'  => true,
                'fields'         => 'ids',
                'meta_key'       => self::META_TS, // phpcs:ignore WordPress.DB.SlowDBQuery
                'orderby'        => 'meta_value_num',
                'order'          => 'ASC',
                'meta_query'     => [['key' => self::META_TS, 'value' => time() - max(0, $past_days) * DAY_IN_SECONDS, 'compare' => '>=', 'type' => 'NUMERIC']], // phpcs:ignore WordPress.DB.SlowDBQuery
                'tax_query'      => [['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => ['exclude-from-catalog'], 'operator' => 'NOT IN']], // phpcs:ignore WordPress.DB.SlowDBQuery
            ]
        );
        return array_values(array_filter(array_map('wc_get_product', array_map('intval', $query->posts))));
    }
}
