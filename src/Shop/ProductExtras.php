<?php
/**
 * The product page's extras around the buying part (Hive\Shop\ProductPage):
 *
 *   "Avvisami" (Alerts): the waitlist form, for the sold-out size picked or
 *   the sold-out product
 *   "Chiedi su WhatsApp" (naming the size picked, at click time) and
 *   "Condividi" (the system's share sheet, or the link copied)
 *   the delivery estimate's place (@hive/delivery fills it, at the
 *   visitor's "now")
 *   the band under the product: "Non trovi la tua taglia?" (its sold-out
 *   sizes, each a WhatsApp message asking the store to source it) and
 *   "Acquista con fiducia" (filter hive_trust_items)
 *   the rails: "Altre colorway" (the same model, attribute pa_modello,
 *   filter hive_colorways_taxonomy) and "Visti di recente" (this browser's
 *   own, fetched from GET /wp-json/hive/v1/recent, a cacheable read)
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Assets;
use Hive\Modules;
use Hive\Settings;

defined('ABSPATH') || exit;

final class ProductExtras
{
    /** Products "Visti di recente" shows at most. */
    public const RECENT_MAX = 12;

    private const ICONS = [
        'authentic' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
        'shipping'  => '<rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'returns'   => '<path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
        'secure'    => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    ];

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void
    {
        register_rest_route(
            ProductData::NAMESPACE,
            '/recent',
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [self::class, 'recent'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'ids' => ['type' => 'string', 'required' => true, 'pattern' => '^[0-9,]{1,200}$'],
                    'v'   => ['type' => 'integer', 'minimum' => 0],
                ],
            ]
        );
    }

    /** GET /wp-json/hive/v1/recent?ids=…&v=…: the "Visti di recente" rail for these products, the same for everyone. */
    public static function recent(\WP_REST_Request $request): \WP_REST_Response
    {
        $ids  = array_slice(array_values(array_unique(array_filter(array_map('absint', explode(',', (string) $request['ids']))))), 0, self::RECENT_MAX);
        $html = $ids ? Rail::render(['title' => 'Visti di recente', 'ids' => implode(',', $ids), 'limit' => count($ids), 'show_all' => false]) : '';
        $response = new \WP_REST_Response(['html' => $html]);
        $response->header('Cache-Control', null !== $request->get_param('v') ? 'public, max-age=3600, s-maxage=86400, stale-while-revalidate=604800' : 'public, max-age=60, s-maxage=300');
        return $response;
    }

    /**
     * "Avvisami": for a variable product the form waits, hidden, for a
     * sold-out size to be picked; for a sold-out product it shows.
     */
    public static function wait_form(\WC_Product $product): string
    {
        if (!Modules::enabled('alerts')) {
            return '';
        }
        $variable = $product->is_type('variable');
        if (!$variable && ($product->is_in_stock() || !$product->is_type('simple'))) {
            return '';
        }
        return sprintf(
            '<form class="hive-wait" data-hive-wait="%1$d"%2$s novalidate><p class="hive-wait__title">%3$s</p><p class="hive-wait__text">Lasciaci la tua email: ti scriviamo appena torna disponibile.</p><div class="hive-wait__row"><label class="hive-visually-hidden" for="hive-wait-email-%1$d">La tua email</label><input class="hive-wait__email" type="email" id="hive-wait-email-%1$d" name="email" required autocomplete="email" inputmode="email" placeholder="La tua email"><button type="submit" class="hive-button hive-wait__submit">Avvisami</button></div><input type="hidden" name="product" value="%1$d"><input type="hidden" name="key" value="*" data-hive-wait-key><input class="hive-wait__trap" type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true"><p class="hive-wait__note">Ti chiediamo di confermare l’indirizzo; ogni email ha il link per smettere.</p><p class="hive-wait__status" role="status" data-hive-wait-status></p></form>',
            $product->get_id(),
            $variable ? ' hidden' : '',
            $variable ? 'Avvisami quando torna la taglia <span data-hive-wait-size></span>' : 'Avvisami quando torna disponibile'
        );
    }

    /** "Chiedi su WhatsApp" and "Condividi". */
    public static function actions(\WC_Product $product): string
    {
        $number = (string) Settings::get('whatsapp');
        $name   = wp_strip_all_tags($product->get_name());
        $url    = $product->get_permalink();
        $html   = sprintf('<div class="hive-actions" data-hive-actions data-name="%s" data-url="%s">', esc_attr($name), esc_url($url));
        if ('' !== $number) {
            $html .= sprintf(
                '<a class="hive-actions__item" data-hive-whatsapp="%s" href="%s" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91A9.9 9.9 0 0 0 12.04 2Zm5.8 14.13c-.25.69-1.44 1.32-2 1.4-.51.08-1.15.11-1.86-.12-.43-.14-.98-.32-1.68-.62-2.96-1.28-4.89-4.26-5.04-4.46-.15-.2-1.2-1.6-1.2-3.05 0-1.45.76-2.17 1.03-2.46.27-.3.59-.37.79-.37h.57c.18 0 .43-.07.67.51.25.59.84 2.04.91 2.19.07.15.12.32.02.52-.1.2-.15.32-.3.49-.15.17-.31.38-.44.52-.15.15-.3.31-.13.6.17.3.77 1.27 1.65 2.06 1.13 1.01 2.09 1.32 2.38 1.47.3.15.47.12.64-.07.17-.2.74-.86.94-1.16.2-.3.4-.25.67-.15.27.1 1.73.82 2.03.97.3.15.49.22.57.35.07.12.07.71-.18 1.4Z"/></svg><span>Chiedi su WhatsApp</span></a>',
                esc_attr($number),
                esc_url(self::whatsapp($number, sprintf('Ciao! Vorrei informazioni su %s: %s', $name, $url)))
            );
        }
        $html .= '<button type="button" class="hive-actions__item" data-hive-share hidden><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg><span>Condividi</span></button>';
        return $html . '</div>';
    }

    /** The delivery estimate's place, while it's on and the product is in stock. */
    public static function delivery(\WC_Product $product): string
    {
        if (!Settings::get('delivery') || !$product->is_in_stock()) {
            return '';
        }
        $min    = (int) Settings::get('delivery_min');
        $closed = array_values(array_filter((array) apply_filters('hive_delivery_closed_dates', []), static fn($day): bool => is_string($day) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)));
        $config = [
            'cutoff' => (string) Settings::get('delivery_cutoff'),
            'min'    => $min,
            'max'    => max($min, (int) Settings::get('delivery_max')),
            'closed' => $closed,
            'tz'     => wp_timezone_string(),
        ];
        return sprintf('<div class="hive-delivery" data-hive-delivery="%s" hidden></div>', esc_attr((string) wp_json_encode($config)));
    }

    /** "Non trovi la tua taglia?" and "Acquista con fiducia". */
    public static function band(\WC_Product $product): string
    {
        if (!Settings::get('band')) {
            return '';
        }
        $card  = self::size_request($product);
        $items = (array) apply_filters('hive_trust_items', self::trust_items());
        if ('' === $card && !$items) {
            return '';
        }
        $html = sprintf('<section class="hive-band%s" aria-label="Acquista con fiducia">', '' === $card ? ' hive-band--trust-only' : '') . $card;
        if ($items) {
            $html .= '<div class="hive-trust"><h2 class="hive-trust__title">Acquista con fiducia</h2><div class="hive-trust__grid">';
            foreach ($items as $item) {
                $icon  = self::ICONS[(string) ($item['icon'] ?? '')] ?? self::ICONS['authentic'];
                $html .= sprintf(
                    '<details class="hive-trust__item" name="hive-trust"><summary><span class="hive-trust__icon"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">%s</svg></span><span class="hive-trust__head"><strong>%s</strong>%s</span></summary><p>%s</p></details>',
                    $icon,
                    esc_html((string) ($item['title'] ?? '')),
                    empty($item['sub']) ? '' : '<span>' . esc_html((string) $item['sub']) . '</span>',
                    esc_html((string) ($item['text'] ?? ''))
                );
            }
            $html .= '</div></div>';
        }
        return $html . '</section>';
    }

    /**
     * The "Acquista con fiducia" tiles; the store's own policies, as its FAQ
     * states them: change them with the hive_trust_items filter.
     *
     * @return list<array{icon: string, title: string, sub: string, text: string}>
     */
    public static function trust_items(): array
    {
        return [
            ['icon' => 'authentic', 'title' => 'Originali al 100%', 'sub' => 'Verificati uno per uno', 'text' => 'Ogni articolo passa il controllo di autenticità del nostro team prima della spedizione: materiali, cuciture, etichette e ogni dettaglio che distingue un originale da un falso.'],
            ['icon' => 'shipping', 'title' => 'Spedizione assicurata', 'sub' => 'DHL o UPS, 2–5 giorni lavorativi', 'text' => 'Spediamo in tutta Italia con DHL o UPS. Gli ordini vengono preparati entro 24–48 ore dal pagamento e ricevi il codice di tracking via email.'],
            ['icon' => 'returns', 'title' => 'Reso entro 14 giorni', 'sub' => 'Dalla consegna', 'text' => 'Hai 14 giorni dalla consegna per il reso: il prodotto deve essere nelle condizioni originali, non indossato e con tutti i tag. Scrivici via email o WhatsApp per avviarlo.'],
            ['icon' => 'secure', 'title' => 'Paga come vuoi', 'sub' => 'Anche in 3 rate senza interessi', 'text' => 'Carte di credito (Visa, Mastercard, American Express), PayPal, Apple Pay, Google Pay e bonifico bancario. Oppure in 3 rate senza interessi con Klarna o Scalapay.'],
        ];
    }

    /** The sold-out sizes as WhatsApp messages asking the store to find them; variable products, with a WhatsApp number. */
    private static function size_request(\WC_Product $product): string
    {
        $number = (string) Settings::get('whatsapp');
        if ('' === $number || !$product->is_type('variable')) {
            return '';
        }
        $name  = wp_strip_all_tags($product->get_name());
        $url   = $product->get_permalink();
        $chips = '';
        $shown = 0;
        foreach (Sizes::rows($product) as $row) {
            if ($row['in_stock'] || $shown >= 12) {
                continue;
            }
            $shown++;
            $chips .= sprintf(
                '<a class="hive-request__chip" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url(self::whatsapp($number, sprintf('Ciao! Cerco %s in taglia %s. Riuscite a trovarla? %s', $name, $row['label'], $url))),
                esc_html((string) $row['label'])
            );
        }
        $chips .= sprintf(
            '<a class="hive-request__chip is-other" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url(self::whatsapp($number, sprintf('Ciao! Cerco %s in una taglia che non vedo disponibile: %s', $name, $url))),
            $shown ? 'Un’altra taglia' : 'Scrivici la tua taglia'
        );
        return sprintf(
            '<div class="hive-request"><p class="hive-request__eyebrow">Su richiesta</p><h2 class="hive-request__title">Non trovi la tua taglia?</h2><p class="hive-request__text">%s</p><div class="hive-request__chips">%s</div></div>',
            $shown
                ? 'Te la cerchiamo noi. Tocca la taglia che ti serve e scrivici su WhatsApp: ti diciamo se riusciamo a trovarla e a che prezzo.'
                : 'Te la cerchiamo noi. Scrivici su WhatsApp la taglia che ti serve: ti diciamo se riusciamo a trovarla e a che prezzo.',
            $chips
        );
    }

    /** "Altre colorway": the other products of the same model, available ones first. */
    public static function colorways(\WC_Product $product): string
    {
        if (!Settings::get('colorways')) {
            return '';
        }
        $taxonomy = (string) apply_filters('hive_colorways_taxonomy', 'pa_modello');
        if (!taxonomy_exists($taxonomy)) {
            return '';
        }
        $terms = wc_get_product_terms($product->get_id(), $taxonomy, ['fields' => 'ids']);
        if (!$terms) {
            return '';
        }
        $query = new \WP_Query(
            [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => self::RECENT_MAX * 2,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'post__not_in'   => [$product->get_id()], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- one product, the page's own.
                'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery
                    ['taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => array_map('intval', $terms)],
                    ['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => ['exclude-from-catalog'], 'operator' => 'NOT IN'],
                ],
            ]
        );
        $ids = array_map('intval', $query->posts);
        if (!$ids) {
            return '';
        }
        // Available colorways first, as in the shop.
        $in  = array_values(array_filter($ids, static fn(int $id): bool => 'outofstock' !== get_post_meta($id, '_stock_status', true)));
        $ids = array_slice(array_merge($in, array_diff($ids, $in)), 0, self::RECENT_MAX);
        return Rail::render(['title' => 'Altre colorway', 'ids' => implode(',', $ids), 'limit' => count($ids), 'show_all' => false]);
    }

    /** The place "Visti di recente" fills in, once it nears the screen. */
    public static function recent_slot(\WC_Product $product): string
    {
        if (!Settings::get('recent')) {
            return '';
        }
        // The rail arrives later: its scripts come with the page.
        Assets::use_module('@hive/rail');
        Assets::use_module('@hive/cards');
        return sprintf('<div class="hive-product__recent" data-hive-recent="%d" data-v="%d"></div>', $product->get_id(), Store::generation());
    }

    private static function whatsapp(string $number, string $text): string
    {
        return 'https://wa.me/' . $number . '?text=' . rawurlencode($text);
    }
}
