<?php
/**
 * The switch-over from golden-hive-blocks and CommerceKit: Hive → Migrazione
 * (or `wp hive migrate`), a dry run first, then for real. Every part is
 * safe to run twice: what's done already is left as it is.
 *
 *   content   pages, posts, templates, template parts, synced patterns and
 *             block widgets: golden-hive/* blocks into hive/* ones
 *             (Core\Migrate\Blocks, the map below), golden-hive-blocks'
 *             shortcodes into Hive's. Pages keep a revision of before.
 *   settings  golden-hive-blocks' options into Hive → Impostazioni
 *             (Core\Migrate\Settings)
 *   drops     products' _ghb_drop_ts / _ghb_drop_lock into Hive's, with
 *             their release scheduled
 *   people    ghb_subscriber posts (newsletter, size alerts) into Hive →
 *             Iscritti, their confirmations, sizes and tokens kept, so the
 *             links in emails already sent still work
 *   CommerceKit  what can be found of its size guides, waitlist and order
 *             bumps, imported as drafts or entries to check (its storage
 *             isn't documented: the dry run says what it found)
 *
 * @package Hive
 */

namespace Hive;

use Hive\Core\Migrate\Blocks as BlockRules;
use Hive\Core\Migrate\Settings as SettingsRules;
use Hive\Core\Migrate\Shortcodes;
use Hive\Shop\Drops;

defined('ABSPATH') || exit;

final class Migration
{
    public const PAGE = 'hive-migration';

    /** Post types whose content holds blocks. */
    private const CONTENT_TYPES = ['page', 'post', 'wp_block', 'wp_template', 'wp_template_part', 'product'];

    /** Old subscriber meta => Hive's. */
    private const PEOPLE_META = [
        '_ghb_email'           => '_hive_email',
        '_ghb_alerts'          => '_hive_alerts',
        '_ghb_sizes'           => '_hive_sizes',
        '_ghb_sizes_pending'   => '_hive_sizes_pending',
        '_ghb_brands'          => '_hive_brands',
        '_ghb_brands_pending'  => '_hive_brands_pending',
        '_ghb_token'           => '_hive_token',
        '_ghb_alerted'         => '_hive_told',
        '_ghb_alert_sent_at'   => '_hive_sent_at',
        '_ghb_confirm_sent_at' => '_hive_confirm_sent_at',
        '_ghb_confirmed_at'    => '_hive_confirmed_at',
    ];

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 20);
        add_action('admin_post_hive_migrate', [self::class, 'handle']);
        // Emails golden-hive-blocks sent link to admin-ajax.php?action=ghb_alerts.
        add_action('wp_ajax_nopriv_ghb_alerts', [self::class, 'old_link'], 1);
        add_action('wp_ajax_ghb_alerts', [self::class, 'old_link'], 1);
        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::add_command('hive migrate', [self::class, 'cli']);
        }
    }

    /**
     * Once the people are in Hive, the confirm and stop links of emails sent
     * before answer through Hive's (their tokens came along). 307 keeps the
     * method: a mail provider's one-click POST stays a POST.
     */
    public static function old_link(): void
    {
        if (!get_option('hive_migrated_at')) {
            // golden-hive-blocks still has them.
            return;
        }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token in the link is the proof; Hive checks it.
        $do    = isset($_GET['do']) ? sanitize_key(wp_unslash($_GET['do'])) : '';
        $token = isset($_GET['t']) && is_string($_GET['t']) ? (string) preg_replace('/[^A-Za-z0-9]/', '', sanitize_text_field(wp_unslash($_GET['t']))) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        wp_safe_redirect(Shop\Alerts::link_url($do, $token), 307);
        exit;
    }

    /**
     * golden-hive-blocks' blocks and what they become in Hive (see
     * Core\Migrate\Blocks for the entries). Filter: hive_migration_block_map.
     *
     * @return array<string, array>
     */
    public static function block_map(): array
    {
        $map = [];
        foreach (self::SAME_NAME as $name) {
            $map['golden-hive/' . $name] = ['name' => 'hive/' . $name];
        }
        $map = array_merge($map, self::CHANGED, self::MERGED);
        // A map picture given by its URL alone: its id in the media library, if it's there.
        $map['golden-hive/store-visit']['resolve'] = static function (array $attrs, array $old): array {
            if (empty($attrs['mapImageId']) && !empty($old['mapImage']) && is_string($old['mapImage'])) {
                $id = attachment_url_to_postid($old['mapImage']);
                if ($id) {
                    $attrs['mapImageId'] = $id;
                }
            }
            return $attrs;
        };
        return (array) apply_filters('hive_migration_block_map', $map);
    }

    /** Blocks ported one to one: same name under hive/, same attributes. */
    private const SAME_NAME = [
        'store-hours', 'newsletter', 'shortcode-wrapper', 'whatsapp-button', 'social-buttons', 'instagram-feed', 'size-chart-modal',
        'trust-badges', 'authenticity-guarantee', 'legit-check', 'faq-schema', 'trustpilot-widget',
        'product-spotlight', 'category-slider', 'brand-marquee', 'drop-countdown', 'drop-calendar', 'social-proof', 'promo-modal',
        'hero-carousel',
    ];

    /** Blocks ported with some attributes gone (Hive does it another way). */
    private const CHANGED = [
        // The map always waits for a click: no switch.
        'golden-hive/contact-info' => ['name' => 'hive/contact-info', 'drop' => ['clickToLoad']],
        'golden-hive/map-embed'    => ['name' => 'hive/map-embed', 'drop' => ['clickToLoad']],
        // The picture is the media library's (mapImageId), with its sizes.
        'golden-hive/store-visit'  => ['name' => 'hive/store-visit', 'drop' => ['mapImage']],
    ];

    /** Blocks merged into one Hive block: renamed, attributes mapped, a layout set. */
    private const MERGED = [];

    /** golden-hive-blocks' shortcodes and Hive's ('' removes: the shop's pages have their own filters). */
    public static function shortcode_map(): array
    {
        return (array) apply_filters(
            'hive_migration_shortcode_map',
            [
                'gh_product_rail'   => 'hive_product_rail',
                'carousel_section'  => 'hive_product_rail',
                'product_carousel'  => 'hive_product_rail',
                'bestsellers'       => 'hive_product_rail type="best_selling" title="Best Sellers"',
                'new_arrivals'      => 'hive_product_rail type="recent" title="Nuovi Arrivi"',
                'on_sale'           => 'hive_product_rail type="sale" title="In Saldo"',
                'featured_products' => 'hive_product_rail type="featured" title="In Evidenza"',
                'gh_size_rail'      => 'hive_size_rail',
                'gh_bank_details'   => 'hive_bank_details',
                'gh_filters'        => '',
                'bfl_filters'       => '',
            ]
        );
    }

    public static function menu(): void
    {
        add_submenu_page(Settings::PAGE, 'Migrazione da golden-hive-blocks', 'Migrazione', 'manage_options', self::PAGE, [self::class, 'page']);
    }

    /** Hive → Migrazione: what would change (or what changed), and the two buttons. */
    public static function page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $report = get_transient('hive_migration_report_' . get_current_user_id());
        delete_transient('hive_migration_report_' . get_current_user_id());

        echo '<div class="wrap"><h1>Migrazione da golden-hive-blocks e CommerceKit</h1>';
        echo '<p>Porta in Hive i blocchi delle pagine, le impostazioni, i drop, gli iscritti e quanto si trova di CommerceKit. Prima prova: nessuna modifica, solo l’elenco di cosa cambierebbe. Poi migra: le pagine tengono una revisione di prima. Si può ripetere: ciò che è già fatto resta com’è.</p>';
        echo '<p><strong>Su staging prima che sul negozio.</strong> Dopo la migrazione disattiva golden-hive-blocks e CommerceKit (vedi SWITCH-OVER.md).</p>';
        foreach (['dry' => ['Prova (nessuna modifica)', 'secondary'], 'run' => ['Migra', 'primary']] as $mode => [$label, $class]) {
            printf(
                '<form method="post" action="%s" style="display:inline-block;margin-right:8px"><input type="hidden" name="action" value="hive_migrate"><input type="hidden" name="mode" value="%s">%s<button type="submit" class="button button-%s"%s>%s</button></form>',
                esc_url(admin_url('admin-post.php')),
                esc_attr($mode),
                wp_nonce_field('hive_migrate', '_wpnonce', true, false), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress's own nonce field.
                esc_attr($class),
                'run' === $mode ? ' onclick="return confirm(\'Migrare adesso? Le pagine tengono una revisione di prima.\')"' : '',
                esc_html($label)
            );
        }
        if (is_array($report)) {
            printf('<h2>%s</h2>', esc_html($report['dry'] ? 'Prova: cosa cambierebbe' : 'Fatto: cosa è cambiato'));
            echo '<ul class="hive-migration-report" style="list-style:disc;padding-left:20px">';
            foreach ((array) $report['lines'] as $line) {
                echo '<li>' . esc_html((string) $line) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }

    public static function handle(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Non hai i permessi per farlo.', 403);
        }
        check_admin_referer('hive_migrate');
        $dry = !isset($_POST['mode']) || 'run' !== sanitize_key(wp_unslash($_POST['mode']));
        set_transient('hive_migration_report_' . get_current_user_id(), ['dry' => $dry, 'lines' => self::run($dry)], HOUR_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE));
        exit;
    }

    /**
     * Brings golden-hive-blocks' and CommerceKit's data into Hive.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Only say what would change.
     *
     * ## EXAMPLES
     *
     *     wp hive migrate --dry-run
     *     wp hive migrate
     *
     * @param array $args
     * @param array $assoc
     */
    public static function cli(array $args, array $assoc): void
    {
        $dry = !empty($assoc['dry-run']);
        foreach (self::run($dry) as $line) {
            \WP_CLI::log($line);
        }
        \WP_CLI::success($dry ? 'Prova finita: nessuna modifica.' : 'Migrazione finita.');
    }

    /**
     * Every part, in order.
     *
     * @return list<string> What changed, or would change, in words.
     */
    public static function run(bool $dry): array
    {
        $lines = array_merge(
            self::content($dry),
            self::widgets($dry),
            self::settings($dry),
            self::drops($dry),
            self::people($dry),
            CommerceKitImport::run($dry),
            self::custom_css($dry)
        );
        if (!$dry) {
            Shop\Store::bump();
            Cache::purge_pages();
            update_option('hive_migrated_at', time(), false);
        }
        return $lines ?: ['Niente da migrare: è già tutto in Hive.'];
    }

    /** @return list<string> */
    private static function content(bool $dry): array
    {
        global $wpdb;
        $map   = self::block_map();
        $codes = self::shortcode_map();
        // Every post whose content mentions an old block or shortcode, in any status but trash.
        $like  = ['%' . $wpdb->esc_like('<!-- wp:golden-hive/') . '%'];
        foreach (array_keys($codes) as $tag) {
            $like[] = '%' . $wpdb->esc_like('[' . $tag) . '%';
        }
        $types = self::CONTENT_TYPES;
        $where = implode(' OR ', array_fill(0, count($like), 'post_content LIKE %s'));
        $in    = implode(',', array_fill(0, count($types), '%s'));
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- a one-time scan; placeholders only.
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_status <> 'trash' AND post_type IN ($in) AND ($where)", array_merge($types, $like)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $lines = [];
        foreach (array_map('intval', $ids) as $id) {
            $post = get_post($id);
            if (!$post instanceof \WP_Post) {
                continue;
            }
            $count   = [];
            $content = serialize_blocks(BlockRules::rewrite(parse_blocks($post->post_content), $map, $codes, $count));
            $left    = BlockRules::names_in($content);
            $title   = get_the_title($post) ?: '(senza titolo)';
            if ($content === $post->post_content) {
                if ($left) {
                    // Done already, but blocks with no Hive counterpart are still there.
                    $lines[] = sprintf('«%s» (%s, #%d): restano da fare a mano: %s', $title, $post->post_type, $id, implode(', ', $left));
                }
                continue;
            }
            $summary = [];
            foreach ($count as $name => $n) {
                $summary[] = $n . '× ' . $name . (isset($map[$name]['name']) ? ' → ' . $map[$name]['name'] : ' (tolto)');
            }
            foreach (Shortcodes::tags_in($post->post_content, $codes) as $tag) {
                $summary[] = '[' . $tag . ']' . ('' === $codes[$tag] ? ' (tolto)' : ' → [' . strtok($codes[$tag], ' ') . ']');
            }
            $lines[] = sprintf('%s «%s» (%s, #%d): %s%s', $dry ? 'Cambierebbe' : 'Cambiato', $title, $post->post_type, $id, implode(', ', $summary), $left ? '. Restano da fare a mano: ' . implode(', ', $left) : '');
            if (!$dry) {
                // wp_update_post keeps a revision; content stays as written (no kses on the blocks' markup),
                // then kses goes back to what it was for this user.
                kses_remove_filters();
                wp_update_post(['ID' => $id, 'post_content' => wp_slash($content)]);
                kses_init();
            }
        }
        return $lines;
    }

    /** Block widgets (classic themes' sidebars). @return list<string> */
    private static function widgets(bool $dry): array
    {
        $widgets = get_option('widget_block');
        if (!is_array($widgets)) {
            return [];
        }
        $changed = 0;
        foreach ($widgets as $key => $widget) {
            if (!is_array($widget) || !isset($widget['content']) || !is_string($widget['content'])) {
                continue;
            }
            $content = serialize_blocks(BlockRules::rewrite(parse_blocks($widget['content']), self::block_map(), self::shortcode_map()));
            if ($content !== $widget['content']) {
                $widgets[$key]['content'] = $content;
                $changed++;
            }
        }
        if (!$changed) {
            return [];
        }
        if (!$dry) {
            update_option('widget_block', $widgets);
        }
        return [sprintf('%s %d widget a blocchi', $dry ? 'Cambierebbero' : 'Cambiati', $changed)];
    }

    /** @return list<string> */
    private static function settings(bool $dry): array
    {
        $old = [];
        foreach (array_keys(SettingsRules::OPTIONS) as $option) {
            $value = get_option($option, null);
            if (null !== $value) {
                $old[$option] = $value;
            }
        }
        $preload = get_option('ghb_preload_settings', []);
        $new     = SettingsRules::from($old, is_array($preload) ? $preload : []);
        // Only into settings still at Hive's default: what was chosen in Hive stays (and a second run changes nothing).
        $now      = Settings::all();
        $defaults = Core\SettingsSchema::defaults();
        $new      = array_filter($new, static fn($value, $key) => ($now[$key] ?? null) === ($defaults[$key] ?? null), ARRAY_FILTER_USE_BOTH);
        $merged   = Core\SettingsSchema::sanitize(array_merge($now, $new));
        $diff     = array_keys(array_filter($new, static fn($value, $key) => ($merged[$key] ?? null) !== ($now[$key] ?? null), ARRAY_FILTER_USE_BOTH));
        if (!$diff) {
            return [];
        }
        if (!$dry) {
            update_option(Settings::OPTION, $merged);
        }
        return [sprintf('%s le impostazioni: %s', $dry ? 'Porterebbe' : 'Portate', implode(', ', $diff))];
    }

    /**
     * Additional CSS (the Customizer's, the Site Editor's) aimed at
     * golden-hive-blocks' classes: Hive's markup has its own, so those rules
     * find nothing any more. Said in the dry run; a person rewrites them.
     *
     * @return list<string>
     */
    private static function custom_css(bool $dry): array
    {
        if (!$dry) {
            return [];
        }
        $lines = [];
        foreach (get_posts(['post_type' => ['custom_css', 'wp_global_styles'], 'post_status' => 'any', 'numberposts' => -1]) as $post) {
            preg_match_all('/\.(ghb?-[a-z0-9_-]+)/i', (string) $post->post_content, $found);
            $classes = array_values(array_unique($found[1]));
            if ($classes) {
                $lines[] = sprintf(
                    'CSS aggiuntivo (%s): %d classi di golden-hive-blocks (%s), che con Hive non trovano più nulla: da riscrivere sulle classi hive-',
                    'custom_css' === $post->post_type ? 'Personalizza, tema ' . $post->post_title : 'Editor del sito',
                    count($classes),
                    implode(', ', array_slice($classes, 0, 6)) . (count($classes) > 6 ? '…' : '')
                );
            }
        }
        return $lines;
    }

    /** @return list<string> */
    private static function drops(bool $dry): array
    {
        $ids = get_posts(['post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1, 'meta_key' => '_ghb_drop_ts']); // phpcs:ignore WordPress.DB.SlowDBQuery
        $moved = 0;
        foreach (array_map('intval', $ids) as $id) {
            if ('' !== (string) get_post_meta($id, Drops::META_TS, true)) {
                continue;
            }
            $moved++;
            if ($dry) {
                continue;
            }
            $ts = (int) get_post_meta($id, '_ghb_drop_ts', true);
            update_post_meta($id, Drops::META_TS, $ts);
            update_post_meta($id, Drops::META_LOCK, 'no' === get_post_meta($id, '_ghb_drop_lock', true) ? 'no' : 'yes');
            Drops::schedule($id, $ts);
        }
        return $moved ? [sprintf('%s le date di %d drop', $dry ? 'Porterebbe' : 'Portate', $moved)] : [];
    }

    /** @return list<string> */
    private static function people(bool $dry): array
    {
        $ids = get_posts(['post_type' => 'ghb_subscriber', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1]);
        if (!$ids) {
            return [];
        }
        $moved  = 0;
        $merged = 0;
        foreach (array_map('intval', $ids) as $id) {
            $email = strtolower(trim((string) get_post_meta($id, '_ghb_email', true)));
            if (!is_email($email)) {
                continue;
            }
            $existing = Shop\Alerts::subscriber($email);
            if ($existing) {
                // Already in Hive (signed up again since): nothing to bring.
                $merged++;
                continue;
            }
            $moved++;
            if ($dry) {
                continue;
            }
            foreach (self::PEOPLE_META as $from => $to) {
                $value = get_post_meta($id, $from, true);
                if ('' !== $value && null !== $value) {
                    update_post_meta($id, $to, '_ghb_email' === $from ? $email : $value);
                }
            }
            // They signed up through the newsletter block.
            update_post_meta($id, '_hive_newsletter', (int) get_post_time('U', true, $id));
            set_post_type($id, Shop\Alerts::TYPE);
        }
        $lines = [];
        if ($moved) {
            $lines[] = sprintf('%s %d iscritti (newsletter e avvisi taglia, con le loro conferme)', $dry ? 'Porterebbe' : 'Portati', $moved);
        }
        if ($merged) {
            $lines[] = sprintf('%d iscritti già in Hive con la stessa email: lasciati com’erano', $merged);
        }
        return $lines;
    }
}
