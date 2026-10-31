<?php
/**
 * Domande frequenti — server render. Each question is a <details>: it opens
 * and closes without a script, from the keyboard too, screen readers say
 * whether it's open, and the browser's find opens the answer it finds in.
 * Unless the block allows several open at once, the questions share a name,
 * so opening one closes the others (the browser does it).
 *
 * The page's FAQPage structured data is printed once, by the server, for
 * all the FAQ blocks on the page (Hive\Blocks\Faq).
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Blocks\Faq;
use Hive\Core\FaqSchema;

defined('ABSPATH') || exit;

$items = FaqSchema::items($attributes['items'] ?? []);
if (!$items) {
    return;
}

$heading  = trim((string) ($attributes['title'] ?? ''));
$subtitle = trim((string) ($attributes['subtitle'] ?? ''));
$group    = empty($attributes['allowMultiple']) ? wp_unique_id('hive-faq-') : '';
?>
<section <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-faq']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <div class="hive-faq__inner">
        <?php if ('' !== $heading || '' !== $subtitle) : ?>
            <div class="hive-faq__header">
                <?php if ('' !== $heading) : ?>
                    <h2 class="hive-faq__title"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ('' !== $subtitle) : ?>
                    <p class="hive-faq__subtitle"><?php echo esc_html($subtitle); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="hive-faq__list">
            <?php foreach ($items as $item) : ?>
                <details class="hive-faq__item"<?php echo '' !== $group ? ' name="' . esc_attr($group) . '"' : ''; ?>>
                    <summary class="hive-faq__question">
                        <span class="hive-faq__question-text"><?php echo esc_html($item['question']); ?></span>
                        <span class="hive-faq__icon" aria-hidden="true"></span>
                    </summary>
                    <div class="hive-faq__answer"><?php echo wpautop(wp_kses_post($item['answer'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() keeps only post HTML; wpautop() adds paragraphs. ?></div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php
echo Faq::add($items); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON in a script tag, escaped by Faq.
