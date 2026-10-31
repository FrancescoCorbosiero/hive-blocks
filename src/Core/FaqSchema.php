<?php
/**
 * The questions of the FAQ block (blocks/faq-schema) and the page's
 * FAQPage structured data (schema.org) built from them: one for the whole
 * page, however many FAQ blocks it has (Blocks\Faq prints it once).
 * Pure: tested by tests/php/FaqSchemaTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class FaqSchema
{
    /**
     * The block's questions as it prints them: the ones with both a
     * question and an answer, trimmed.
     *
     * @return list<array{question: string, answer: string}>
     */
    public static function items(mixed $items): array
    {
        $clean = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (!is_array($item) || !is_scalar($item['question'] ?? null) || !is_scalar($item['answer'] ?? null)) {
                continue;
            }
            $question = trim((string) $item['question']);
            $answer   = trim((string) $item['answer']);
            if ('' !== $question && '' !== $answer) {
                $clean[] = ['question' => $question, 'answer' => $answer];
            }
        }
        return $clean;
    }

    /** Some HTML as one line of plain text: tags (and what scripts and styles hold) out, entities decoded. */
    public static function text(string $html): string
    {
        $text = (string) preg_replace('@<(script|style)[^>]*?>.*?</\1>@si', '', $html);
        $text = strip_tags((string) preg_replace('@<br\s*/?>|</(p|div|li|h[1-6])>@i', ' ', $text));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * The page's FAQPage from the questions of all its FAQ blocks: each
     * question once (the first answer wins), as plain text; null when there
     * are none.
     *
     * @return array<string, mixed>|null
     */
    public static function page(mixed $items): ?array
    {
        $questions = [];
        foreach (self::items($items) as $item) {
            $question = self::text($item['question']);
            $answer   = self::text($item['answer']);
            if ('' === $question || '' === $answer || isset($questions[$question])) {
                continue;
            }
            $questions[$question] = [
                '@type'          => 'Question',
                'name'           => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
            ];
        }
        if (!$questions) {
            return null;
        }
        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_values($questions),
        ];
    }
}
