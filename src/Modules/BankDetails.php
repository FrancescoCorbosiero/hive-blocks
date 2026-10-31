<?php
/**
 * Module "Coordinate bancarie" (Hive\Shop\BankDetails), at the checkout.
 *
 * @package Hive
 */

namespace Hive\Modules;

use Hive\Module;
use Hive\Shop\BankDetails as Feature;

defined('ABSPATH') || exit;

final class BankDetails implements Module
{
    public static function id(): string
    {
        return 'bank-details';
    }

    public static function label(): string
    {
        return 'Coordinate bancarie';
    }

    public static function description(): string
    {
        return '«Mostra coordinate bancarie per il bonifico» al checkout, con IBAN e BIC da copiare con un tocco, dal conto di WooCommerce (Pagamenti → Bonifico bancario).';
    }

    public static function enabled_by_default(): bool
    {
        return true;
    }

    public static function boot(): void
    {
        Feature::register();
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
    }

    /** At the checkout, its CSS and script from the start: the order review's refresh can bring it in later. */
    public static function assets(): void
    {
        if (function_exists('is_checkout') && is_checkout() && !is_order_received_page()) {
            wp_enqueue_style('hive-checkout');
            wp_enqueue_script_module('@hive/checkout');
        }
    }
}
