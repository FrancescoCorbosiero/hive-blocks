<?php
/**
 * Module "Aggiunta rapida": on a card of a product with sizes, "Aggiungi al
 * carrello" opens the size choice (the @hive/quick-add script module, loaded
 * on the first click) and adds the chosen size without leaving the page.
 * Switched off, the button leads to the product page instead.
 *
 * @package Hive
 */

namespace Hive\Modules;

use Hive\Module;

defined('ABSPATH') || exit;

final class QuickAdd implements Module
{
    public static function id(): string
    {
        return 'quick-add';
    }

    public static function label(): string
    {
        return 'Aggiunta rapida';
    }

    public static function description(): string
    {
        return 'Sulle schede dei prodotti con più taglie, «Aggiungi al carrello» apre la scelta della taglia senza lasciare la pagina.';
    }

    public static function enabled_by_default(): bool
    {
        return true;
    }

    public static function boot(): void
    {
        // Nothing to hook: Hive\Shop\Card asks whether the module is on.
    }
}
