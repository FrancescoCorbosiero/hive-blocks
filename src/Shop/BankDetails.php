<?php
/**
 * The bank details for a bank transfer, at the checkout: "Mostra coordinate
 * bancarie per il bonifico" under the payment methods opens them in a
 * dialog (@hive/checkout), the IBAN and BIC one tap from the clipboard.
 *
 * The account is WooCommerce's own (Payments → Direct bank transfer), so the
 * IBAN lives in one place; the hive_bank_account filter can give another.
 * Nothing shows without an IBAN.
 *
 * Classic checkout: after the payment methods. The Checkout block: after
 * the block. Anywhere else: [hive_bank_details].
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Assets;

defined('ABSPATH') || exit;

final class BankDetails
{
    private const ICON = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>';

    public static function register(): void
    {
        add_action('woocommerce_review_order_after_payment', [self::class, 'classic']);
        add_filter('render_block_woocommerce/checkout', [self::class, 'block'], 20, 1);
        add_shortcode('hive_bank_details', [self::class, 'render']);
    }

    /**
     * WooCommerce's first bank account with an IBAN.
     *
     * @return array{account_name: string, bank_name: string, iban: string, bic: string, sort_code: string}
     */
    public static function account(): array
    {
        $account = ['account_name' => '', 'bank_name' => '', 'iban' => '', 'bic' => '', 'sort_code' => ''];
        foreach ((array) get_option('woocommerce_bacs_accounts', []) as $row) {
            if (is_array($row) && !empty($row['iban'])) {
                foreach (array_keys($account) as $key) {
                    $account[$key] = trim((string) ($row[$key] ?? ''));
                }
                break;
            }
        }
        $account = (array) apply_filters('hive_bank_account', $account);
        return array_map('strval', array_merge(['account_name' => '', 'bank_name' => '', 'iban' => '', 'bic' => '', 'sort_code' => ''], $account));
    }

    /** The button, and the details it opens (inert in a <template> until then). */
    public static function render(): string
    {
        $account = self::account();
        if ('' === $account['iban']) {
            return '';
        }
        Assets::use_module('@hive/checkout');
        $rows = [
            ['Intestatario del conto', $account['account_name'], false],
            ['Banca', $account['bank_name'], false],
            ['IBAN', $account['iban'], true],
            ['Codice SWIFT / BIC', $account['bic'], true],
            ['Codice filiale', $account['sort_code'], false],
        ];
        $body = '<p class="hive-bank__intro">Fai il bonifico con il numero dell’ordine come causale: l’ordine parte appena arriva l’accredito.</p><dl class="hive-bank__rows">';
        foreach ($rows as [$label, $value, $copy]) {
            if ('' === $value) {
                continue;
            }
            $body .= sprintf(
                '<div class="hive-bank__row"><dt>%s</dt><dd><span class="hive-bank__value">%s</span>%s</dd></div>',
                esc_html($label),
                esc_html($value),
                $copy ? sprintf('<button type="button" class="hive-bank__copy" data-hive-copy="%s" data-label="%s">Copia</button>', esc_attr($value), esc_attr($label)) : ''
            );
        }
        $body .= '</dl><p class="hive-bank__note">L’accredito arriva di solito in 1–3 giorni lavorativi, secondo la banca.</p><p class="hive-visually-hidden" role="status" data-hive-copy-status></p>';

        return Assets::use_style('hive-checkout') . sprintf(
            '<div class="hive-block hive-bank"><button type="button" class="hive-bank__open" data-hive-bank aria-haspopup="dialog">%s<span>Mostra coordinate bancarie per il bonifico</span></button><template data-hive-bank-details data-title="Coordinate bancarie">%s</template></div>',
            self::ICON,
            $body
        );
    }

    public static function classic(): void
    {
        echo self::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() escapes what it prints.
    }

    public static function block(mixed $content): mixed
    {
        return is_string($content) ? $content . self::render() : $content;
    }
}
