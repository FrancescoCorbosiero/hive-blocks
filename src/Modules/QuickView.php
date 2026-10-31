<?php
/**
 * Module "Anteprima rapida": the eye button on every card opens photos,
 * sizes, price and add to cart without leaving the page. The dialog is the
 * @hive/quick-view script module, loaded on the first click; its data comes
 * from Hive\Shop\ProductData. Switched off, the cards just don't show the
 * button.
 *
 * @package Hive
 */

namespace Hive\Modules;

use Hive\Module;

defined('ABSPATH') || exit;

final class QuickView implements Module
{
    public static function id(): string
    {
        return 'quick-view';
    }

    public static function label(): string
    {
        return 'Anteprima rapida';
    }

    public static function description(): string
    {
        return 'Il pulsante sull’immagine di ogni scheda apre foto, taglie e prezzo senza lasciare la pagina.';
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
