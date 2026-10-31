<?php
/**
 * What a code snippet (the Code Snippets plugin) relies on that the
 * switch-over takes away: golden-hive-blocks' and CommerceKit's functions,
 * which are gone once those plugins are off, and WooCommerce's product-list
 * hooks, which Hive's cards and shop pages don't run. Said in the dry run,
 * for a person to look at before deactivating anything.
 *
 * Pure logic: no WordPress calls. Tested in tests/php/MigrateSnippetsTest.php.
 *
 * @package Hive
 */

namespace Hive\Core\Migrate;

defined('ABSPATH') || exit;

final class Snippets
{
    private const GOLDEN_HIVE = '/\b(ghb_[a-z0-9_]+|gh_(?:product_rail|size_rail|bank_details|filters)|golden[-_]hive[a-z0-9_-]*)/i';
    private const COMMERCEKIT = '/\b(commercekit[a-z0-9_]*|ckit_[a-z0-9_]+|cgkit_[a-z0-9_]+)/i';
    private const LOOP_HOOKS  = '/[\'"](woocommerce_(?:before|after)_shop_loop(?:_item(?:_title)?)?|woocommerce_shop_loop_item_title|woocommerce_archive_description|woocommerce_no_products_found)[\'"]/';

    /**
     * @return list<string> What the snippet relies on that goes, in words; empty when nothing.
     */
    public static function concerns(string $code): array
    {
        $out = [];
        foreach (
            [
                [self::GOLDEN_HIVE, 'usa golden-hive-blocks (%s)'],
                [self::COMMERCEKIT, 'usa CommerceKit (%s)'],
                [self::LOOP_HOOKS, 'aggiunge alle liste prodotti di WooCommerce (%s), che le schede e le pagine del negozio di Hive non usano'],
            ] as [$pattern, $words]
        ) {
            preg_match_all($pattern, $code, $found);
            $names = array_values(array_unique(array_map('strtolower', $found[1])));
            if ($names) {
                $out[] = sprintf($words, implode(', ', array_slice($names, 0, 3)) . (count($names) > 3 ? '…' : ''));
            }
        }
        return $out;
    }
}
