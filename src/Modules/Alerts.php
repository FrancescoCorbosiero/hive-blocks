<?php
/**
 * Module "Avvisi e lista d'attesa" (Hive\Shop\Alerts): "Avvisami" on sold-out
 * sizes and products, and the size alerts, with double opt-in. Switched
 * off, no form is offered and nothing is sent; the list of people stays.
 *
 * @package Hive
 */

namespace Hive\Modules;

use Hive\Module;
use Hive\Shop\Alerts as Feature;

defined('ABSPATH') || exit;

final class Alerts implements Module
{
    public static function id(): string
    {
        return 'alerts';
    }

    public static function label(): string
    {
        return 'Avvisi e lista d’attesa';
    }

    public static function description(): string
    {
        return '«Avvisami» sulle taglie e i prodotti esauriti, e gli avvisi per taglia: un’email quando arrivano, con doppia conferma.';
    }

    public static function enabled_by_default(): bool
    {
        return true;
    }

    public static function boot(): void
    {
        Feature::register();
    }
}
