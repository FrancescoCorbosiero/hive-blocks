<?php
/**
 * Vieni in negozio — server render. The physical store in one block: the
 * address with "Indicazioni", WhatsApp (the block's number, or the one in
 * Hive → Impostazioni) and the phone, the opening hours, and the map that
 * loads when the visitor asks (Hive\Blocks\Map).
 *
 * The page is cached, so "now" is the browser's job: view.js writes "Aperto
 * ora · chiude alle 19:30" and marks today's row from data-hive-visit, with
 * the Orari block's logic (blocks/store-hours/hours.js). The server marks
 * today's row too, so it's right before the script runs.
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Blocks\Contact;
use Hive\Blocks\Map;
use Hive\Core\ContactLinks;
use Hive\Core\MapEmbed;
use Hive\Core\OpeningHours;

defined('ABSPATH') || exit;

$text = static fn(string $key): string => trim((string) ($attributes[$key] ?? ''));

$eyebrow  = $text('eyebrow');
$heading  = $text('title');
$subtitle = $text('subtitle');
$address  = $text('address');
$vat      = $text('vat');
$note     = $text('note');
$rows     = OpeningHours::rows($attributes['hours'] ?? []);

if ('' === $heading && '' === $address && !$rows) {
    return;
}

$phone    = $text('phone');
$tel      = ContactLinks::tel($phone);
$whatsapp = empty($attributes['showWhatsApp']) ? '' : Contact::whatsapp($text('whatsapp'), $text('whatsappText'));
$map_html = Map::facade($text('mapEmbed'), ['address' => $address, 'image' => (int) ($attributes['mapImageId'] ?? 0)]);
$today    = (int) wp_date('w');
$config   = [
    'rows'     => $rows,
    'tz'       => wp_timezone_string(),
    'holidays' => (bool) ($attributes['closedOnHolidays'] ?? true),
    'closed'   => OpeningHours::closed_dates($text('closedDates')),
];
?>
<section <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-visit' . ('' === $map_html ? ' hive-visit--no-map' : '')]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?><?php echo $rows ? ' data-hive-visit="' . esc_attr((string) wp_json_encode($config)) . '"' : ''; ?>>
    <div class="hive-visit__inner">
        <div class="hive-visit__info">
            <?php if ('' !== $eyebrow) : ?>
                <p class="hive-visit__eyebrow"><?php echo esc_html($eyebrow); ?></p>
            <?php endif; ?>
            <?php if ('' !== $heading) : ?>
                <h2 class="hive-visit__title"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>

            <?php if ($rows) : ?>
                <p class="hive-visit__status" data-hive-visit-status hidden>
                    <span class="hive-visit__dot" aria-hidden="true"></span>
                    <span data-hive-visit-text></span>
                </p>
            <?php endif; ?>

            <?php if ('' !== $subtitle) : ?>
                <p class="hive-visit__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>

            <?php if ('' !== $address) : ?>
                <p class="hive-visit__address">
                    <?php echo Contact::icon('address'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
                    <span>
                        <?php echo esc_html($address); ?>
                        <?php if ('' !== $vat) : ?>
                            <span class="hive-visit__vat"><?php echo esc_html($vat); ?></span>
                        <?php endif; ?>
                    </span>
                </p>
            <?php elseif ('' !== $vat) : ?>
                <p class="hive-visit__vat"><?php echo esc_html($vat); ?></p>
            <?php endif; ?>

            <?php if ('' !== $address || '' !== $whatsapp || '' !== $tel) : ?>
                <div class="hive-visit__actions">
                    <?php if ('' !== $address) : ?>
                        <a class="hive-visit__action is-primary" href="<?php echo esc_url(MapEmbed::directions_url($address)); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo Contact::icon('directions'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
                            <span>Indicazioni</span>
                        </a>
                    <?php endif; ?>
                    <?php if ('' !== $whatsapp) : ?>
                        <a class="hive-visit__action" href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo Contact::icon('whatsapp'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
                            <span>WhatsApp</span>
                        </a>
                    <?php endif; ?>
                    <?php if ('' !== $tel) : ?>
                        <a class="hive-visit__action" href="<?php echo esc_url($tel); ?>">
                            <?php echo Contact::icon('phone'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
                            <span><?php echo esc_html($phone); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($rows) : ?>
                <dl class="hive-visit__hours">
                    <?php
                    foreach ($rows as $row) :
                        $day      = OpeningHours::day_index($row['day']);
                        $is_today = $day === $today;
                        ?>
                        <div class="hive-visit__row<?php echo $is_today ? ' is-today' : ''; ?>"<?php echo null === $day ? '' : ' data-hive-day="' . (int) $day . '"'; ?>>
                            <dt class="hive-visit__day">
                                <?php echo esc_html($row['day']); ?>
                                <span class="hive-visit__badge" data-hive-today-badge<?php echo $is_today ? '' : ' hidden'; ?>>Oggi</span>
                            </dt>
                            <dd class="hive-visit__time<?php echo OpeningHours::is_closed($row['time']) ? ' is-closed' : ''; ?>"><?php echo esc_html($row['time']); ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>

            <?php if ('' !== $note) : ?>
                <p class="hive-visit__note"><?php echo esc_html($note); ?></p>
            <?php endif; ?>
        </div>

        <?php if ('' !== $map_html) : ?>
            <div class="hive-visit__map">
                <?php echo $map_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Map::facade() escapes what it prints. ?>
            </div>
        <?php endif; ?>
    </div>
</section>
