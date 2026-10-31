<?php
/**
 * Orari del negozio — server render.
 *
 * The page is cached, so "now" is the browser's job: view.js marks today's
 * row and writes the status ("Aperto ora · chiude alle 19:30") from
 * data-hive-hours, in the store's time zone, holidays and closed dates
 * included. The server marks today's row too, so it's right before the
 * script runs.
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Core\OpeningHours;

defined('ABSPATH') || exit;

$rows = OpeningHours::rows($attributes['hours'] ?? []);
if (!$rows) {
    return;
}

$heading = trim((string) ($attributes['title'] ?? ''));
$note    = trim((string) ($attributes['note'] ?? ''));
$today   = (int) wp_date('w');
$config  = [
    'rows'     => $rows,
    'tz'       => wp_timezone_string(),
    'holidays' => (bool) ($attributes['closedOnHolidays'] ?? true),
    'closed'   => OpeningHours::closed_dates((string) ($attributes['closedDates'] ?? '')),
];
?>
<section <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-hours']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?> data-hive-hours="<?php echo esc_attr(wp_json_encode($config)); ?>">
    <div class="hive-hours__inner">
        <?php if ('' !== $heading) : ?>
            <h2 class="hive-hours__title"><?php echo esc_html($heading); ?></h2>
        <?php endif; ?>

        <p class="hive-hours__status" data-hive-hours-status hidden>
            <span class="hive-hours__dot" aria-hidden="true"></span>
            <span data-hive-hours-text></span>
        </p>

        <dl class="hive-hours__list">
            <?php
            foreach ($rows as $row) :
                $day      = OpeningHours::day_index($row['day']);
                $is_today = $day === $today;
                ?>
                <div class="hive-hours__row<?php echo $is_today ? ' is-today' : ''; ?>"<?php echo null === $day ? '' : ' data-hive-day="' . (int) $day . '"'; ?>>
                    <dt class="hive-hours__day">
                        <?php echo esc_html($row['day']); ?>
                        <span class="hive-hours__badge" data-hive-today-badge<?php echo $is_today ? '' : ' hidden'; ?>>Oggi</span>
                    </dt>
                    <dd class="hive-hours__time<?php echo OpeningHours::is_closed($row['time']) ? ' is-closed' : ''; ?>"><?php echo esc_html($row['time']); ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>

        <?php if ('' !== $note) : ?>
            <p class="hive-hours__note"><?php echo esc_html($note); ?></p>
        <?php endif; ?>
    </div>
</section>
