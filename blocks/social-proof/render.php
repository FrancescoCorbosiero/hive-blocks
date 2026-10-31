<?php
/**
 * Acquisti recenti — "Qualcuno ha appena acquistato": a toast with the
 * store's latest paid orders, from Hive\Shop\SocialProof's public feed
 * (GET /wp-json/hive/v1/social-proof): the product, the city, "2 ore fa".
 *
 * The page carries only the empty toast and the feed's URL, the same for
 * every visitor; view.js asks the feed once the page has settled and works
 * the times out in the browser, so a cached page shows today's purchases.
 * Never on the cart and checkout pages, where nothing should distract. In
 * the editor the toast stands still in the canvas with the latest purchase.
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Shop\ProductData;
use Hive\Shop\SocialProof;

defined('ABSPATH') || exit;

if (function_exists('is_cart') && (is_cart() || is_checkout())) {
    return;
}

$days     = max(1, min(30, (int) ($attributes['days'] ?? 14)));
$limit    = max(1, min(20, (int) ($attributes['limit'] ?? 10)));
$city     = (bool) ($attributes['showCity'] ?? true);
$verified = (bool) ($attributes['showVerified'] ?? true);
$heading  = trim((string) ($attributes['title'] ?? ''));
$config   = [
    'src'      => add_query_arg(['days' => $days, 'limit' => $limit, 'city' => $city ? 1 : 0], rest_url(ProductData::NAMESPACE . '/social-proof')),
    'days'     => $days,
    'max'      => max(1, min(10, (int) ($attributes['maxPerVisit'] ?? 3))),
    'interval' => max(5000, min(120000, (int) ($attributes['interval'] ?? 25000))),
    'delay'    => max(0, min(60000, (int) ($attributes['initialDelay'] ?? 8000))),
    'duration' => max(2000, min(20000, (int) ($attributes['displayDuration'] ?? 6000))),
];

// The editor's canvas: the latest purchase, standing still.
$in_editor   = wp_is_serving_rest_request();
$sample      = null;
$sample_meta = '';
if ($in_editor && function_exists('wc_get_orders')) {
    $sample      = SocialProof::feed($days, 1, $city)[0] ?? null;
    $sample_meta = $sample
        ? implode(' · ', array_filter([$sample['city'], sprintf('%s fa', human_time_diff((int) $sample['ts']))]))
        : sprintf('Nessun ordine pagato negli ultimi %d giorni: sul sito l’avviso non si vede finché non ne arriva uno.', $days);
}
?>
<div <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-social-proof' . ($in_editor ? ' is-preview is-visible' : '')]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?> data-hive-social-proof="<?php echo esc_attr((string) wp_json_encode($config)); ?>" role="status" aria-live="polite" aria-atomic="true">
    <a class="hive-social-proof__link" data-hive-social-proof-link<?php echo $sample ? ' href="' . esc_url($sample['url']) . '"' : ''; ?>>
        <span class="hive-social-proof__media" data-hive-social-proof-media hidden></span>
        <span class="hive-social-proof__body">
            <?php if ('' !== $heading) : ?>
                <span class="hive-social-proof__title"><?php echo esc_html($heading); ?></span>
            <?php endif; ?>
            <strong class="hive-social-proof__product" data-hive-social-proof-product><?php echo $sample ? esc_html($sample['product']) : ''; ?></strong>
            <span class="hive-social-proof__meta" data-hive-social-proof-meta><?php echo esc_html($sample_meta); ?></span>
            <?php if ($verified) : ?>
                <span class="hive-social-proof__verified"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>Acquisto verificato</span>
            <?php endif; ?>
        </span>
    </a>
    <button type="button" class="hive-social-proof__close" data-hive-social-proof-close aria-label="Chiudi"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.4 5 5 6.4 10.6 12 5 17.6 6.4 19l5.6-5.6 5.6 5.6 1.4-1.4-5.6-5.6L19 6.4 17.6 5 12 10.6z"/></svg></button>
</div>
