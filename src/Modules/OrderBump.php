<?php
/**
 * Module "Offerta al checkout" (Hive\Shop\OrderBumps), at the checkout.
 *
 * @package Hive
 */

namespace Hive\Modules;

use Hive\Module;
use Hive\Shop\OrderBumps as Feature;

defined('ABSPATH') || exit;

final class OrderBump implements Module
{
    public static function id(): string
    {
        return 'order-bump';
    }

    public static function label(): string
    {
        return 'Offerta al checkout';
    }

    public static function description(): string
    {
        return 'Un prodotto in più proposto al checkout, sopra i metodi di pagamento («Aggiungi anche…»), per ogni carrello o per i carrelli con certe categorie (Hive → Offerte al checkout).';
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
