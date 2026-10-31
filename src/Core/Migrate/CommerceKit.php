<?php
/**
 * Reading CommerceKit's data without knowing its format for sure: it isn't
 * documented, so the importer looks at what's there (tables, columns, the
 * `commercekit` option) and these rules decide what can be brought over.
 * Whatever they can't read is reported, not guessed.
 *
 * @package Hive
 */

namespace Hive\Core\Migrate;

final class CommerceKit
{
    /** Column names, most likely first. */
    private const EMAIL   = ['email', 'user_email', 'email_address', 'customer_email', 'mail'];
    private const PRODUCT = ['variation_id', 'product_id', 'pid', 'post_id', 'item_id', 'product', 'parent_id', 'product_parent', 'parent'];
    private const SENT    = ['mail_sent', 'email_sent', 'notified', 'sent', 'is_sent', 'sent_at', 'notified_at', 'status'];

    /**
     * The columns of a waitlist table: where the email, the product and
     * "already told" are. The product columns come most precise first (the
     * variation, then the product): a row's product is the first one set.
     * Null when there's no email or product column.
     *
     * @param list<string> $columns
     * @return array{email: string, products: list<string>, sent: ?string}|null
     */
    public static function waitlist_columns(array $columns): ?array
    {
        $lower = [];
        foreach ($columns as $column) {
            $lower[strtolower((string) $column)] = (string) $column;
        }
        $find = static function (array $names) use ($lower): ?string {
            foreach ($names as $name) {
                if (isset($lower[$name])) {
                    return $lower[$name];
                }
            }
            return null;
        };
        $email    = $find(self::EMAIL);
        $products = [];
        foreach (self::PRODUCT as $name) {
            if (isset($lower[$name])) {
                $products[] = $lower[$name];
            }
        }
        if (null === $email || !$products) {
            return null;
        }
        return ['email' => $email, 'products' => $products, 'sent' => $find(self::SENT)];
    }

    /**
     * Whether a waitlist row was already dealt with: its email sent, or
     * marked done. A status column counts when it says so in words.
     */
    public static function already_sent(mixed $value, string $column): bool
    {
        if (null === $value || '' === $value || '0' === (string) $value || '0000-00-00 00:00:00' === (string) $value) {
            return false;
        }
        if ('status' === strtolower($column)) {
            return in_array(strtolower((string) $value), ['sent', 'notified', 'done', 'completed', 'mailed', '1'], true);
        }
        return true;
    }

    /**
     * Order bumps in CommerceKit's settings. Its option keeps them under
     * keys like order_bump_product, order_bump_title… as single values or
     * lists (one entry per bump); every key whose name says "bump" counts.
     *
     * @param array<string, mixed> $option
     * @return list<array{product: int, headline: string, text: string, button: string}>
     */
    public static function bumps(array $option): array
    {
        $fields = ['product' => [], 'headline' => [], 'text' => [], 'button' => []];
        foreach ($option as $key => $value) {
            $key = strtolower((string) $key);
            if (!preg_match('/(order_?bump|obp|bump)/', $key)) {
                continue;
            }
            $field = match (true) {
                (bool) preg_match('/button|btn|cta/', $key)                   => 'button',
                (bool) preg_match('/product|item|pid/', $key)                 => 'product',
                (bool) preg_match('/title|headline|heading/', $key)           => 'headline',
                (bool) preg_match('/desc|text|content|message|body/', $key)   => 'text',
                default                                                       => null,
            };
            if (null === $field || $fields[$field]) {
                continue;
            }
            $fields[$field] = array_values(is_array($value) ? $value : [$value]);
        }
        $bumps = [];
        foreach ($fields['product'] as $i => $product) {
            $product = is_array($product) ? reset($product) : $product;
            if (!is_numeric($product) || (int) $product <= 0) {
                continue;
            }
            $bumps[] = [
                'product'  => (int) $product,
                'headline' => self::text($fields['headline'][$i] ?? ''),
                'text'     => self::text($fields['text'][$i] ?? ''),
                'button'   => self::text($fields['button'][$i] ?? ''),
            ];
        }
        return $bumps;
    }

    /**
     * Post types that look like size guides (CommerceKit's, or another
     * plugin's left behind).
     *
     * @param list<string> $types
     * @return list<string>
     */
    public static function guide_types(array $types): array
    {
        return array_values(
            array_filter(
                $types,
                static fn($type): bool => is_string($type) && 'hive_size_guide' !== $type && (bool) preg_match('/size_?guide|sizeguide|size_?chart/', $type)
            )
        );
    }

    /** Whether a table, option or meta key belongs to CommerceKit. */
    public static function is_commercekit(string $name): bool
    {
        return (bool) preg_match('/commercekit|(^|_)ckit_|cgkit/i', $name);
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
