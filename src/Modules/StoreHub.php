<?php
/**
 * Module "Store Hub" (Hive\Hub\Routes): the wc-gh/v1 REST API the Store Hub
 * app's Vetrina edits the homepage through, unchanged from golden-hive-blocks.
 * Switched off, the app can't read or edit the homepage; the page and the
 * order it was given stay as they are.
 *
 * @package Hive
 */

namespace Hive\Modules;

use Hive\Hub\Routes;
use Hive\Module;

defined('ABSPATH') || exit;

final class StoreHub implements Module
{
    public static function id(): string
    {
        return 'store-hub';
    }

    public static function label(): string
    {
        return 'Store Hub';
    }

    public static function description(): string
    {
        return 'La Vetrina dell’app Store Hub modifica la homepage: quali sezioni di prodotti ci sono e in che ordine, cosa mostrano, i prodotti in testa o esclusi, i testi.';
    }

    public static function enabled_by_default(): bool
    {
        return true;
    }

    public static function boot(): void
    {
        Routes::register();
    }
}
