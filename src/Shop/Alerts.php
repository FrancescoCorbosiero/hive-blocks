<?php
/**
 * "Avvisami": the waitlist and the size alerts, one list of people (module
 * "Avvisi e lista d'attesa"), in place of CommerceKit's Waitlist and
 * golden-hive-blocks' size alerts.
 *
 *   the waitlist: a sold-out size picked on a product page (or a sold-out
 *   product) offers "Avvisami quando torna": that product, in that size
 *   the size alerts: up to three sizes, optionally some brands ("a pair in
 *   my size arrived"), from the newsletter block (step 8)
 *
 * Everyone is a hive_subscriber post (Hive → Iscritti). New addresses
 * confirm by email before anything is sent (double opt-in); a confirmed
 * address joins a waitlist at once; new sizes are always confirmed. Every
 * email carries the way out, also as one-click unsubscribe (RFC 8058). The
 * email links open a page with a button, and only the button (a POST) acts,
 * so mail scanners that open every link change nothing.
 *
 * Arrivals: a size comes into stock on a published product, a product with
 * stock is published, or a drop is released. Each waits in a short queue
 * (a week); 20 minutes after the first, a run checks each is still true and
 * sends each confirmed person one email with all their matches
 * (Core\Alerts): at most one every 12 hours for the size alerts (a waitlist
 * match doesn't wait), at most 40 emails a run, never the same product and
 * size twice. Action Scheduler runs the queue (WP-Cron without it).
 *
 * The signup is a POST to wc-ajax=hive_alerts, from a cached page: no nonce
 * could be fresh there, so a rate limit per address, a hidden field for
 * bots and the email confirmation guard it.
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Core\Alerts as Rules;
use Hive\Core\Sizes as SizeRules;
use Hive\Settings;

defined('ABSPATH') || exit;

final class Alerts
{
    public const TYPE = 'hive_subscriber';
    public const HOOK = 'hive_alerts_run';
    public const AJAX = 'hive_alerts';

    private const QUEUE     = 'hive_arrivals';
    private const MAX_SIZES = 3;
    private const COOLDOWN  = 12 * HOUR_IN_SECONDS;
    private const BATCH     = 40;
    private const KEEP      = 7 * DAY_IN_SECONDS;

    public static function register(): void
    {
        add_action('wc_ajax_' . self::AJAX, [self::class, 'signup']);
        add_action('template_redirect', [self::class, 'link'], 0);
        add_action('woocommerce_variation_set_stock_status', [self::class, 'variation_stock'], 10, 3);
        add_action('woocommerce_product_set_stock_status', [self::class, 'product_stock'], 10, 3);
        add_action('transition_post_status', [self::class, 'published'], 20, 3);
        add_action('hive_drop_released', [self::class, 'released']);
        add_action(self::HOOK, [self::class, 'run']);
        add_filter('script_module_data_@hive/product', [self::class, 'module_data']);
    }

    /** The list of people, whatever is switched on (the newsletter writes to it too): Hive → Iscritti. */
    public static function register_list(): void
    {
        add_action('init', [self::class, 'post_type']);
        add_filter('manage_' . self::TYPE . '_posts_columns', [self::class, 'columns']);
        add_action('manage_' . self::TYPE . '_posts_custom_column', [self::class, 'column'], 10, 2);
    }

    public static function post_type(): void
    {
        register_post_type(
            self::TYPE,
            [
                'labels'          => [
                    'name'          => 'Iscritti',
                    'singular_name' => 'Iscritto',
                    'all_items'     => 'Iscritti',
                    'menu_name'     => 'Iscritti',
                    'search_items'  => 'Cerca tra gli iscritti',
                    'not_found'     => 'Nessun iscritto',
                    'edit_item'     => 'Iscritto',
                ],
                'description'     => 'Chi ha chiesto gli avvisi taglia, la lista d’attesa o la newsletter.',
                'public'          => false,
                'show_ui'         => true,
                'show_in_menu'    => Settings::PAGE,
                'show_in_rest'    => false,
                'supports'        => ['title'],
                'capability_type' => 'post',
                'capabilities'    => ['create_posts' => 'do_not_allow'],
                'map_meta_cap'    => true,
                'rewrite'         => false,
                'query_var'       => false,
            ]
        );
    }

    /** @param array<string, mixed> $data */
    public static function module_data(array $data): array
    {
        return ['alerts' => \WC_AJAX::get_endpoint(self::AJAX)] + $data;
    }

    /* Signup ------------------------------------------------------------- */

    /** wc-ajax=hive_alerts: sizes (+ brands) or a waitlist entry, for an email address. */
    public static function signup(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- posted from cached pages, where no nonce can be fresh; see the class comment.
        $ip    = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $limit = 'hive_alerts_' . md5($ip);
        $tries = (int) get_transient($limit);
        if ($tries >= 6) {
            wp_send_json(['ok' => false, 'message' => 'Troppi tentativi: riprova tra un po’.'], 429);
        }
        set_transient($limit, $tries + 1, HOUR_IN_SECONDS);

        // A field people never see: whoever fills it in is a bot, told it went fine.
        if (!empty($_POST['website'])) {
            wp_send_json(['ok' => true, 'message' => 'Ci siamo quasi: conferma dalla tua email.']);
        }

        $email   = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $sizes   = isset($_POST['sizes']) && is_array($_POST['sizes']) ? array_slice(array_map('sanitize_text_field', wp_unslash($_POST['sizes'])), 0, 10) : [];
        $brands  = isset($_POST['brands']) && is_array($_POST['brands']) ? array_slice(array_map('sanitize_title', wp_unslash($_POST['brands'])), 0, 20) : [];
        $product = isset($_POST['product']) ? absint($_POST['product']) : 0;
        $key     = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if (!is_email($email)) {
            wp_send_json(['ok' => false, 'message' => 'Scrivi un indirizzo email valido.'], 400);
        }
        $sizes = self::clean_sizes($sizes);
        $wait  = $product ? self::clean_wait($product, $key) : '';
        if (!$sizes && '' === $wait) {
            wp_send_json(['ok' => false, 'message' => $product ? 'Questo prodotto non si può aspettare.' : 'Scegli almeno una taglia.'], 400);
        }

        $id = self::subscriber($email, true);
        if (!$id) {
            wp_send_json(['ok' => false, 'message' => 'Qualcosa non ha funzionato: riprova.'], 500);
        }
        wp_send_json(['ok' => true, 'message' => self::ask($id, $email, $sizes, self::clean_brands($brands), '' !== $wait ? [$wait] : [])]);
    }

    /**
     * Records what was asked and says what happens next. A confirmed
     * address joins a waitlist at once; new sizes, and anything from an
     * address not confirmed yet, wait for the email's button.
     *
     * @param list<string> $sizes
     * @param list<string> $brands
     * @param list<string> $waits
     */
    public static function ask(int $id, string $email, array $sizes, array $brands, array $waits): string
    {
        $confirmed = 'on' === get_post_meta($id, '_hive_alerts', true);
        if ($waits && $confirmed && !$sizes) {
            update_post_meta($id, '_hive_wait', Rules::waits(self::list($id, '_hive_wait'), $waits));
            return 'Fatto: ti scriviamo appena torna disponibile.';
        }

        if ($sizes) {
            update_post_meta($id, '_hive_sizes_pending', $sizes);
            update_post_meta($id, '_hive_brands_pending', $brands);
        }
        if ($waits) {
            update_post_meta($id, '_hive_wait_pending', Rules::waits(self::list($id, '_hive_wait_pending'), $waits));
        }
        if (!$confirmed) {
            update_post_meta($id, '_hive_alerts', 'pending');
        }
        // One confirmation email per address every 10 minutes.
        $last = (int) get_post_meta($id, '_hive_confirm_sent_at', true);
        if (time() - $last > 10 * MINUTE_IN_SECONDS) {
            self::send_confirmation($email, self::token($id), $sizes, $waits);
            update_post_meta($id, '_hive_confirm_sent_at', time());
        }
        return $sizes
            ? sprintf('Ci siamo quasi: conferma dalla tua email gli avvisi per la taglia %s.', Rules::labels(self::size_labels($sizes)))
            : 'Ci siamo quasi: conferma dalla tua email e ti scriviamo appena torna disponibile.';
    }

    /** The subscriber of an address, created when asked. */
    public static function subscriber(string $email, bool $create = false): int
    {
        $email = strtolower(trim($email));
        $found = get_posts(
            [
                'post_type'      => self::TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_key'       => '_hive_email', // phpcs:ignore WordPress.DB.SlowDBQuery
                'meta_value'     => $email, // phpcs:ignore WordPress.DB.SlowDBQuery
            ]
        );
        if ($found) {
            return (int) $found[0];
        }
        if (!$create) {
            return 0;
        }
        $id = wp_insert_post(['post_type' => self::TYPE, 'post_status' => 'private', 'post_title' => $email, 'meta_input' => ['_hive_email' => $email]], true);
        if (is_wp_error($id) || !$id) {
            return 0;
        }
        do_action('hive_subscriber_created', (int) $id, $email);
        return (int) $id;
    }

    /**
     * Size keys the store carries (Hive → Negozio's size attribute), at most three.
     *
     * @param list<string> $raw
     * @return list<string>
     */
    public static function clean_sizes(array $raw): array
    {
        $known = self::size_options();
        $keys  = [];
        foreach ($raw as $value) {
            $key = SizeRules::key((string) $value);
            if ('' !== $key && isset($known[$key])) {
                $keys[] = $key;
            }
        }
        return array_slice(array_values(array_unique($keys)), 0, self::MAX_SIZES);
    }

    /** @param list<string> $raw @return list<string> Brand slugs that exist. */
    private static function clean_brands(array $raw): array
    {
        $brands = [];
        foreach ($raw as $slug) {
            foreach (Store::brand_taxonomies() as $taxonomy) {
                if ('' !== $slug && get_term_by('slug', $slug, $taxonomy)) {
                    $brands[] = $slug;
                    break;
                }
            }
        }
        return array_values(array_unique($brands));
    }

    /** "812|42": a published product, in one of its sizes ("*" for a product without). */
    private static function clean_wait(int $product_id, string $key): string
    {
        $product = wc_get_product($product_id);
        if (!$product instanceof \WC_Product || 'publish' !== $product->get_status() || $product->is_type('variation')) {
            return '';
        }
        $key = Rules::ANY === $key ? $key : SizeRules::key($key);
        if (Rules::ANY !== $key && !in_array($key, self::keys_of($product, false), true)) {
            return '';
        }
        $entry = $product_id . '|' . $key;
        return Rules::is_wait($entry) ? $entry : '';
    }

    /**
     * The size attribute's terms grouped by size: key => {label, slugs}.
     *
     * @return array<string, array{key: string, label: string, slugs: list<string>, count: int}>
     */
    public static function size_options(): array
    {
        $terms = get_terms(['taxonomy' => Store::size_attribute(), 'hide_empty' => false]);
        $rows  = [];
        foreach (is_array($terms) ? $terms : [] as $term) {
            $rows[] = ['name' => $term->name, 'slug' => $term->slug, 'count' => $term->count];
        }
        return array_column(SizeRules::group_options($rows), null, 'key');
    }

    /** @param list<string> $keys @return list<string> */
    private static function size_labels(array $keys): array
    {
        $options = self::size_options();
        return array_map(static fn(string $key): string => Rules::ANY === $key ? '' : (string) ($options[$key]['label'] ?? $key), $keys);
    }

    private static function token(int $id): string
    {
        $token = (string) get_post_meta($id, '_hive_token', true);
        if ('' === $token) {
            $token = wp_generate_password(32, false, false);
            update_post_meta($id, '_hive_token', $token);
        }
        return $token;
    }

    /** confirm | off: the email links. */
    public static function link_url(string $do, string $token): string
    {
        return add_query_arg(['hive_alerts' => $do, 't' => $token], home_url('/'));
    }

    /** @return list<string> A list kept in meta. */
    private static function list(int $id, string $key): array
    {
        return array_values(array_filter(array_map('strval', (array) get_post_meta($id, $key, true)), 'strlen'));
    }

    /* The email links ------------------------------------------------------- */

    /** ?hive_alerts=confirm|off&t=…: opening the link asks; the button (or a mail provider's one-click POST) acts. */
    public static function link(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token in the link is the proof; see the class comment.
        if (!isset($_GET['hive_alerts'])) {
            return;
        }
        $do    = sanitize_key(wp_unslash($_GET['hive_alerts']));
        $token = isset($_GET['t']) && is_string($_GET['t']) ? (string) preg_replace('/[^A-Za-z0-9]/', '', sanitize_text_field(wp_unslash($_GET['t']))) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        nocache_headers();

        $found = '' === $token ? [] : get_posts(
            [
                'post_type'      => self::TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_key'       => '_hive_token', // phpcs:ignore WordPress.DB.SlowDBQuery
                'meta_value'     => $token, // phpcs:ignore WordPress.DB.SlowDBQuery
            ]
        );
        if (!$found || !in_array($do, ['confirm', 'off'], true)) {
            self::page('Link non valido', 'Questo link è scaduto o non è completo. Chiedi di nuovo gli avvisi dal sito.');
        }
        $id   = (int) $found[0];
        $post = isset($_SERVER['REQUEST_METHOD']) && 'POST' === strtoupper(sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])));

        if ('off' === $do) {
            if (!$post) {
                self::page('Disattivare gli avvisi?', 'Non riceverai più email quando arriva la tua taglia o torna un prodotto che aspetti.', 'Disattiva gli avvisi');
            }
            update_post_meta($id, '_hive_alerts', 'off');
            foreach (['_hive_sizes', '_hive_sizes_pending', '_hive_brands', '_hive_brands_pending', '_hive_wait', '_hive_wait_pending'] as $key) {
                delete_post_meta($id, $key);
            }
            self::page('Avvisi disattivati', 'Non ti scriveremo più. Puoi riattivarli quando vuoi dal sito.');
        }

        $sizes_pending = self::list($id, '_hive_sizes_pending');
        $wait_pending  = self::list($id, '_hive_wait_pending');
        if (!$post) {
            $what = $sizes_pending
                ? sprintf('Ti scriviamo quando arriva un paio nella taglia %s.', Rules::labels(self::size_labels($sizes_pending)))
                : 'Ti scriviamo appena torna disponibile quello che aspetti.';
            self::page('Conferma gli avvisi', $what, 'Conferma gli avvisi');
        }

        $sizes = Rules::merge_sizes(self::list($id, '_hive_sizes'), $sizes_pending, self::MAX_SIZES);
        $waits = Rules::waits(self::list($id, '_hive_wait'), $wait_pending);
        if (!$sizes && !$waits) {
            self::page('Niente da confermare', 'Scegli la tua taglia, o un prodotto da aspettare, dal sito.');
        }
        update_post_meta($id, '_hive_sizes', $sizes);
        update_post_meta($id, '_hive_wait', $waits);
        $brands = self::list($id, '_hive_brands_pending');
        if ($sizes_pending) {
            update_post_meta($id, '_hive_brands', $brands);
        }
        foreach (['_hive_sizes_pending', '_hive_brands_pending', '_hive_wait_pending'] as $key) {
            delete_post_meta($id, $key);
        }
        update_post_meta($id, '_hive_alerts', 'on');
        update_post_meta($id, '_hive_confirmed_at', time());

        self::page(
            'Avvisi attivati',
            $sizes
                ? sprintf('Ti scriviamo quando arriva un paio nella taglia %s%s. Ogni email ha il link per disattivarli.', Rules::labels(self::size_labels($sizes)), $waits ? ' e quando torna quello che aspetti' : '')
                : 'Ti scriviamo appena torna disponibile quello che aspetti. Ogni email ha il link per disattivare gli avvisi.'
        );
    }

    /** A small page of its own for the email links; with $button, a form that POSTs back to the link. */
    private static function page(string $title, string $text, string $button = ''): never
    {
        status_header(200);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');
        $shop   = wc_get_page_permalink('shop');
        $accent = (string) Settings::get('accent');
        $form   = '' === $button ? '' : sprintf('<form method="post" action="%s"><button type="submit">%s</button></form>', esc_url(add_query_arg([])), esc_html($button));
        printf(
            '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>%1$s</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f5f5;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#171717}main{max-width:440px;margin:24px;padding:36px 28px;border-radius:16px;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.08);text-align:center}.brand{margin:0 0 18px;color:%2$s;font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}h1{margin:0 0 10px;font-size:24px}p{margin:0 0 22px;color:#525252;line-height:1.55}a,button{display:inline-block;padding:12px 22px;border:0;border-radius:8px;background:%2$s;color:#fff;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}form{margin:0 0 14px}form+a{background:none;color:#525252;font-weight:600;text-decoration:underline}</style></head><body><main><p class="brand">%3$s</p><h1>%4$s</h1><p>%5$s</p>%6$s<a href="%7$s">Vai al negozio</a></main></body></html>',
            esc_html($title . ' — ' . get_bloginfo('name')),
            esc_attr($accent),
            esc_html(get_bloginfo('name')),
            esc_html($title),
            esc_html($text),
            $form, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built, above.
            esc_url($shop)
        );
        exit;
    }

    /* Emails ---------------------------------------------------------------- */

    /** HTML email in WooCommerce's template (Settings → Email), with the one-click way out. */
    private static function mail(string $to, string $subject, string $heading, string $body, string $unsubscribe = ''): bool
    {
        $headers = "Content-Type: text/html; charset=UTF-8\r\n";
        if ('' !== $unsubscribe) {
            $headers .= 'List-Unsubscribe: <' . esc_url_raw($unsubscribe) . ">\r\n";
            $headers .= "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
        }
        $mailer = WC()->mailer();
        return (bool) $mailer->send($to, $subject, $mailer->wrap_message($heading, $body), $headers);
    }

    private static function button(string $url, string $label): string
    {
        return sprintf(
            '<p style="margin:24px 0;"><a href="%s" style="display:inline-block;padding:12px 22px;border-radius:6px;background:%s;color:#ffffff;font-weight:700;text-decoration:none;">%s</a></p>',
            esc_url($url),
            esc_attr((string) Settings::get('accent')),
            esc_html($label)
        );
    }

    /**
     * @param list<string> $sizes
     * @param list<string> $waits
     */
    private static function send_confirmation(string $email, string $token, array $sizes, array $waits): bool
    {
        $what = [];
        if ($sizes) {
            $what[] = sprintf('un’email quando arriva un paio nella taglia <strong>%s</strong>', esc_html(Rules::labels(self::size_labels($sizes))));
        }
        foreach ($waits as $entry) {
            [$pid, $key] = explode('|', $entry, 2);
            $product     = wc_get_product((int) $pid);
            if ($product instanceof \WC_Product) {
                $size   = Rules::ANY === $key ? '' : ' in taglia ' . esc_html(self::size_labels([$key])[0]);
                $what[] = sprintf('un’email quando torna <strong>%s</strong>%s', esc_html(wp_strip_all_tags($product->get_name())), $size);
            }
        }
        $body  = '<p>Hai chiesto di ricevere ' . implode(' e ', $what) . '.</p>';
        $body .= self::button(self::link_url('confirm', $token), 'Conferma gli avvisi');
        $body .= '<p style="color:#737373;font-size:13px;">Se non sei stato tu, ignora questa email: senza conferma non ti scriveremo.</p>';
        return self::mail($email, 'Conferma i tuoi avvisi', 'Un ultimo passo', $body);
    }

    /**
     * One email with everything that arrived for someone: the first six
     * products, a link to the shop in their sizes, the way out.
     *
     * @param array<int, list<string>> $picks Product id => keys.
     */
    private static function send_digest(int $id, array $picks, bool $sizes_too): bool
    {
        $email = (string) get_post_meta($id, '_hive_email', true);
        if (!is_email($email) || !$picks) {
            return false;
        }
        $rows  = '';
        $names = [];
        $keys  = [];
        foreach (array_slice($picks, 0, 6, true) as $pid => $pick) {
            $product = wc_get_product($pid);
            if (!$product instanceof \WC_Product) {
                continue;
            }
            $names[] = wp_strip_all_tags($product->get_name());
            $keys    = array_merge($keys, array_diff($pick, [Rules::ANY]));
            $image   = wp_get_attachment_image_url((int) $product->get_image_id(), 'woocommerce_thumbnail');
            $rows   .= '<tr><td style="padding:10px 12px 10px 0;width:96px;vertical-align:middle;">'
                . ($image ? '<img src="' . esc_url($image) . '" width="96" height="96" alt="" style="display:block;width:96px;height:96px;object-fit:contain;border-radius:8px;background:#f5f5f5;">' : '')
                . '</td><td style="padding:10px 0;vertical-align:middle;"><strong style="display:block;color:#171717;font-size:15px;">' . esc_html(end($names)) . '</strong>'
                . '<span style="display:block;margin:4px 0 8px;color:' . esc_attr((string) Settings::get('accent')) . ';font-weight:700;">' . esc_html(self::line($product, $pick)) . '</span>'
                . '<a href="' . esc_url($product->get_permalink()) . '" style="color:#171717;font-weight:700;">Vedi il prodotto &rarr;</a></td></tr>';
        }
        if ('' === $rows) {
            return false;
        }
        $count  = count($picks);
        $sizes  = self::list($id, '_hive_sizes');
        $labels = Rules::labels(self::size_labels($sizes));
        $off    = self::link_url('off', self::token($id));

        $body = '<p>' . ($sizes_too && '' !== $labels ? sprintf('Sono disponibili, anche nella taglia <strong>%s</strong>:', esc_html($labels)) : 'Sono di nuovo disponibili:') . '</p>';
        $body .= '<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;">' . $rows . '</table>';
        if ($sizes_too && $sizes) {
            $body .= self::button(self::shop_url($sizes), $count > 6 ? sprintf('Vedi tutti i %d nella tua taglia', $count) : 'Vedi tutto nella tua taglia');
        }
        $body .= '<p style="color:#737373;font-size:12px;">Ricevi questa email perché hai chiesto gli avvisi su ' . esc_html(get_bloginfo('name')) . '. <a href="' . esc_url($off) . '" style="color:#737373;">Disattiva gli avvisi</a></p>';

        if (!$sizes_too) {
            $subject = 1 === $count ? sprintf('È di nuovo disponibile: %s', $names[0]) : sprintf('%d prodotti che aspettavi sono disponibili', $count);
        } else {
            $subject = 1 === $count ? sprintf('È arrivato un paio nella tua taglia %s', $labels) : sprintf('%d nuovi arrivi nella tua taglia %s', $count, $labels);
        }
        return self::mail($email, $subject, $sizes_too ? 'Nuovi arrivi nella tua taglia' : 'Di nuovo disponibile', $body, $off);
    }

    /** "Taglia 42 · 180 €", or the price for a product without sizes. */
    private static function line(\WC_Product $product, array $keys): string
    {
        $keys = array_values(array_diff($keys, [Rules::ANY]));
        if ($keys && $product->is_type('variable')) {
            $line = SizeRules::line(SizeRules::pick(Sizes::rows($product), $keys));
            if ('' !== $line) {
                return $line;
            }
        }
        $price = html_entity_decode(wp_strip_all_tags(wc_price(wc_get_price_to_display($product))), ENT_QUOTES, 'UTF-8');
        return $keys ? sprintf('Taglia %s · %s', Rules::labels(self::size_labels($keys)), $price) : 'Disponibile · ' . $price;
    }

    /** The shop filtered on these sizes (every spelling of each). */
    private static function shop_url(array $keys): string
    {
        $options = self::size_options();
        $slugs   = [];
        foreach ($keys as $key) {
            $slugs = array_merge($slugs, $options[$key]['slugs'] ?? []);
        }
        $shop  = wc_get_page_permalink('shop');
        $param = Store::size_param();
        return $slugs ? add_query_arg([$param => implode(',', $slugs), 'query_type_' . substr($param, 7) => 'or'], $shop) : $shop;
    }

    /* Arrivals ---------------------------------------------------------------- */

    /**
     * The keys a product has in stock now: its sizes, and "*" (any) for the
     * waitlist of a product without sizes.
     *
     * @return list<string>
     */
    public static function keys_of(\WC_Product $product, bool $in_stock = true): array
    {
        $keys = [];
        if ($product->is_type('variable')) {
            foreach (Sizes::rows($product) as $row) {
                if (!$in_stock || $row['in_stock']) {
                    $keys[] = SizeRules::key((string) $row['label']);
                }
            }
            if (!$keys && (!$in_stock || $product->is_in_stock())) {
                $keys[] = Rules::ANY;
            }
        } elseif (!$in_stock || $product->is_in_stock()) {
            foreach (wc_get_product_terms($product->get_id(), Store::size_attribute(), ['fields' => 'names']) as $name) {
                $keys[] = SizeRules::key((string) $name);
            }
            $keys[] = Rules::ANY;
        }
        return array_values(array_unique(array_filter($keys)));
    }

    /** Queues "this product arrived in these sizes", then makes sure a run is due. */
    public static function record(int $product_id, array $keys): void
    {
        $keys = array_values(array_filter($keys));
        if (!$keys || Drops::locked($product_id)) {
            return;
        }
        $queue = self::queue();
        foreach ($keys as $key) {
            $queue[$product_id . '|' . $key] = time();
        }
        arsort($queue);
        update_option(self::QUEUE, array_slice($queue, 0, 500, true), false);
        self::schedule(20 * MINUTE_IN_SECONDS, false);
    }

    /** @return array<string, int> "productId|key" => when, a week at most. */
    private static function queue(): array
    {
        $queue = get_option(self::QUEUE, []);
        $since = time() - self::KEEP;
        return array_filter(is_array($queue) ? $queue : [], static fn($t): bool => (int) $t >= $since);
    }

    public static function variation_stock(mixed $variation_id, mixed $status, mixed $variation = null): void
    {
        if ('instock' !== $status) {
            return;
        }
        $variation = $variation instanceof \WC_Product ? $variation : wc_get_product((int) $variation_id);
        $parent_id = $variation instanceof \WC_Product ? $variation->get_parent_id() : 0;
        if (!$parent_id || 'publish' !== get_post_status($parent_id)) {
            return;
        }
        $label = $variation->get_attribute(Store::size_attribute());
        self::record($parent_id, ['' !== $label ? SizeRules::key($label) : Rules::ANY]);
    }

    public static function product_stock(mixed $product_id, mixed $status, mixed $product = null): void
    {
        if ('instock' !== $status) {
            return;
        }
        $product = $product instanceof \WC_Product ? $product : wc_get_product((int) $product_id);
        if ($product instanceof \WC_Product && !$product->is_type(['variable', 'variation']) && 'publish' === $product->get_status()) {
            self::record($product->get_id(), self::keys_of($product));
        }
    }

    public static function published(mixed $new, mixed $old, mixed $post): void
    {
        if ('publish' !== $new || 'publish' === $old || !$post instanceof \WP_Post || 'product' !== $post->post_type) {
            return;
        }
        $product = wc_get_product($post->ID);
        if ($product instanceof \WC_Product) {
            self::record($product->get_id(), self::keys_of($product));
        }
    }

    public static function released(mixed $product_id): void
    {
        $product = wc_get_product((int) $product_id);
        if ($product instanceof \WC_Product && 'publish' === $product->get_status()) {
            self::record($product->get_id(), self::keys_of($product));
        }
    }

    /** A run in $delay seconds; $force adds one even when one is due (a run continuing itself). */
    private static function schedule(int $delay, bool $force): void
    {
        if (function_exists('as_schedule_single_action') && function_exists('as_next_scheduled_action')) {
            if ($force || false === as_next_scheduled_action(self::HOOK, [], 'hive')) {
                as_schedule_single_action(time() + $delay, self::HOOK, [], 'hive');
            }
            return;
        }
        if ($force || !wp_next_scheduled(self::HOOK)) {
            wp_schedule_single_event(time() + $delay, self::HOOK);
        }
    }

    /** Brand slugs of a product, parent brands included. */
    private static function brands_of(int $product_id): array
    {
        $slugs = [];
        foreach (Store::brand_taxonomies() as $taxonomy) {
            $terms = get_the_terms($product_id, $taxonomy);
            foreach (is_array($terms) ? $terms : [] as $term) {
                $slugs[] = $term->slug;
                foreach (get_ancestors($term->term_id, $taxonomy, 'taxonomy') as $ancestor) {
                    $parent = get_term($ancestor, $taxonomy);
                    if ($parent instanceof \WP_Term) {
                        $slugs[] = $parent->slug;
                    }
                }
            }
        }
        return array_values(array_unique($slugs));
    }

    /** The queue's run: what's still true, to whom, in batches. */
    public static function run(): void
    {
        $queue = self::queue();
        update_option(self::QUEUE, $queue, false);
        if (!$queue) {
            return;
        }

        $by = [];
        foreach ($queue as $pair => $t) {
            [$pid, $key] = array_pad(explode('|', (string) $pair, 2), 2, '');
            if ((int) $pid && '' !== $key) {
                $by[(int) $pid]['keys'][] = $key;
                $by[(int) $pid]['t']      = max((int) ($by[(int) $pid]['t'] ?? 0), (int) $t);
            }
        }
        // Still true now: published, visible, out, and that size in stock.
        $live      = [];
        $brands_of = [];
        foreach ($by as $pid => $arrival) {
            $product = wc_get_product($pid);
            if (!$product instanceof \WC_Product || 'publish' !== $product->get_status() || 'hidden' === $product->get_catalog_visibility() || Drops::locked($pid)) {
                continue;
            }
            $keys = array_values(array_intersect($arrival['keys'], self::keys_of($product)));
            if ($keys) {
                $live[$pid]      = ['keys' => $keys, 't' => $arrival['t']];
                $brands_of[$pid] = self::brands_of($pid);
            }
        }
        if (!$live) {
            return;
        }

        $people = get_posts(
            [
                'post_type'      => self::TYPE,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'meta_key'       => '_hive_alerts', // phpcs:ignore WordPress.DB.SlowDBQuery
                'meta_value'     => 'on', // phpcs:ignore WordPress.DB.SlowDBQuery
            ]
        );
        $sent     = 0;
        $deferred = false;
        $more     = false;
        foreach ($people as $id) {
            $id    = (int) $id;
            $waits = self::list($id, '_hive_wait');
            $picks = Rules::pick($live, self::list($id, '_hive_sizes'), self::list($id, '_hive_brands'), $brands_of, $waits, self::list($id, '_hive_told'));
            if (!$picks) {
                continue;
            }
            $pairs     = [];
            foreach ($picks as $pid => $keys) {
                foreach ($keys as $key) {
                    $pairs[] = $pid . '|' . $key;
                }
            }
            $waited    = (bool) array_intersect($pairs, $waits);
            $sizes_too = (bool) array_diff($pairs, $waits);
            $last      = (int) get_post_meta($id, '_hive_sent_at', true);
            // Size alerts at most every 12 hours; what someone waited for goes at once.
            if (!$waited && $last && time() - $last < self::COOLDOWN) {
                $deferred = true;
                continue;
            }
            if ($sent >= self::BATCH) {
                $more = true;
                break;
            }
            if (self::send_digest($id, $picks, $sizes_too)) {
                $sent++;
                update_post_meta($id, '_hive_told', array_slice(array_values(array_unique(array_merge(self::list($id, '_hive_told'), $pairs))), -300));
                update_post_meta($id, '_hive_wait', Rules::waits($waits, [], $pairs));
                update_post_meta($id, '_hive_sent_at', time());
            }
        }
        if ($more) {
            self::schedule(10 * MINUTE_IN_SECONDS, true);
        } elseif ($deferred) {
            self::schedule(6 * HOUR_IN_SECONDS, true);
        }
    }

    /* Admin: Hive → Iscritti ---------------------------------------------- */

    public static function columns(array $columns): array
    {
        $date = $columns['date'] ?? null;
        unset($columns['date']);
        $columns['hive_news']   = 'Newsletter';
        $columns['hive_alerts'] = 'Avvisi taglia';
        $columns['hive_wait']   = 'Lista d’attesa';
        if (null !== $date) {
            $columns['date'] = $date;
        }
        return $columns;
    }

    public static function column(string $column, int $post_id): void
    {
        $state = (string) get_post_meta($post_id, '_hive_alerts', true);
        if ('hive_news' === $column) {
            $since = (int) get_post_meta($post_id, '_hive_newsletter', true);
            echo $since ? esc_html('Sì, dal ' . wp_date('j M Y', $since)) : '—';
            return;
        }
        if ('hive_alerts' === $column) {
            if ('off' === $state) {
                echo 'Disattivati';
                return;
            }
            $sizes = 'on' === $state ? self::list($post_id, '_hive_sizes') : self::list($post_id, '_hive_sizes_pending');
            echo $sizes ? esc_html(Rules::labels(self::size_labels($sizes))) . ' <em>(' . esc_html('on' === $state ? 'confermati' : 'da confermare') . ')</em>' : '—';
            return;
        }
        if ('hive_wait' === $column) {
            $waits = 'on' === $state ? self::list($post_id, '_hive_wait') : self::list($post_id, '_hive_wait_pending');
            $names = [];
            foreach (array_slice($waits, 0, 3) as $entry) {
                [$pid, $key] = explode('|', $entry, 2);
                $names[]     = get_the_title((int) $pid) . (Rules::ANY === $key ? '' : ' (' . self::size_labels([$key])[0] . ')');
            }
            echo $names ? esc_html(implode(', ', $names) . (count($waits) > 3 ? ' e altri ' . (count($waits) - 3) : '')) . ('on' === $state ? '' : ' <em>(da confermare)</em>') : '—';
        }
    }
}
