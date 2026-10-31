<?php
/**
 * Module "Drop": a release date on products (Hive\Shop\Drops). Switched
 * off, the date fields leave the product screen and products are on sale
 * whatever their date.
 *
 * @package Hive
 */

namespace Hive\Modules;

use Hive\Module;
use Hive\Shop\Drops as Feature;

defined('ABSPATH') || exit;

final class Drops implements Module
{
    public static function id(): string
    {
        return 'drops';
    }

    public static function label(): string
    {
        return 'Drop';
    }

    public static function description(): string
    {
        return 'Una data di uscita sui prodotti: «In arrivo» sulle schede, conto alla rovescia e «Ricordamelo» sulla pagina, acquisto bloccato fino al drop.';
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
