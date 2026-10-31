<?php
/**
 * The newsletter's signup (block hive/newsletter): wc-ajax=hive_newsletter.
 * The address joins the list of people (Hive → Iscritti) with the
 * newsletter ticked, and hive_newsletter_subscribed fires for an email
 * service (Brevo, Mailchimp…) to take it from there. Sizes ticked in the
 * form become size alerts, confirmed by email (Alerts).
 *
 * Posted from cached pages, where no nonce can be fresh: a rate limit per
 * address and a hidden field for bots guard it, as for "Avvisami".
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Modules;

defined('ABSPATH') || exit;

final class Newsletter
{
    public const AJAX = 'hive_newsletter';

    public static function register(): void
    {
        add_action('wc_ajax_' . self::AJAX, [self::class, 'signup']);
    }

    public static function endpoint(): string
    {
        return \WC_AJAX::get_endpoint(self::AJAX);
    }

    public static function signup(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- posted from cached pages; see the class comment.
        $ip    = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $limit = 'hive_news_' . md5($ip);
        $tries = (int) get_transient($limit);
        if ($tries >= 5) {
            wp_send_json(['ok' => false, 'message' => 'Troppi tentativi: riprova tra un po’.'], 429);
        }
        set_transient($limit, $tries + 1, HOUR_IN_SECONDS);
        if (!empty($_POST['website'])) {
            wp_send_json(['ok' => true, 'message' => 'Iscrizione completata! Grazie, a presto.']);
        }
        $email  = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $sizes  = isset($_POST['sizes']) && is_array($_POST['sizes']) ? array_slice(array_map('sanitize_text_field', wp_unslash($_POST['sizes'])), 0, 10) : [];
        $brands = isset($_POST['brands']) && is_array($_POST['brands']) ? array_slice(array_map('sanitize_title', wp_unslash($_POST['brands'])), 0, 20) : [];
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if (!is_email($email)) {
            wp_send_json(['ok' => false, 'message' => 'Scrivi un indirizzo email valido.'], 400);
        }
        $id = Alerts::subscriber($email, true);
        if (!$id) {
            wp_send_json(['ok' => false, 'message' => 'Qualcosa non ha funzionato: riprova.'], 500);
        }
        $already = (bool) get_post_meta($id, '_hive_newsletter', true);
        if (!$already) {
            update_post_meta($id, '_hive_newsletter', time());
            /**
             * A new newsletter subscriber: hook an email service here.
             *
             * @param string $email
             * @param int    $id    The hive_subscriber post.
             */
            do_action('hive_newsletter_subscribed', $email, $id);
        }

        $alerts = '';
        $sizes  = Modules::enabled('alerts') ? Alerts::clean_sizes($sizes) : [];
        if ($sizes) {
            $alerts = Alerts::ask($id, $email, $sizes, self::clean_brands($brands), []);
        }
        $message = $already ? 'Sei già iscritto alla newsletter.' : 'Iscrizione completata! Grazie, a presto.';
        wp_send_json(['ok' => true, 'message' => '' !== $alerts ? $message . ' ' . $alerts : $message]);
    }

    /**
     * The brands the form offers: the most stocked top-level ones of the
     * first brand taxonomy, cached for 12 hours.
     *
     * @return list<array{slug: string, name: string}>
     */
    public static function brand_options(int $limit = 10): array
    {
        $cached = get_transient('hive_alert_brands');
        if (!is_array($cached)) {
            $cached     = [];
            $taxonomies = Store::brand_taxonomies();
            if ($taxonomies) {
                $terms = get_terms(['taxonomy' => $taxonomies[0], 'parent' => 0, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 20]);
                foreach (is_array($terms) ? $terms : [] as $term) {
                    $cached[] = ['slug' => $term->slug, 'name' => html_entity_decode($term->name, ENT_QUOTES, 'UTF-8')];
                }
            }
            set_transient('hive_alert_brands', $cached, 12 * HOUR_IN_SECONDS);
        }
        return array_slice($cached, 0, $limit);
    }

    /** @param list<string> $raw @return list<string> */
    private static function clean_brands(array $raw): array
    {
        $known = array_column(self::brand_options(20), 'slug');
        return array_values(array_unique(array_intersect($raw, $known)));
    }
}
