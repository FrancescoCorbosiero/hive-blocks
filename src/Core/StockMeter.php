<?php
/**
 * What the product page says about stock (the stock meter): sold out, on
 * backorder, available, or few left ("Solo 2 disponibili", with a bar) when
 * the quantity is at or under the threshold set in Hive → Pagina prodotto.
 *
 * Pure logic: no WordPress calls. Tested in tests/php/StockMeterTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class StockMeter
{
    /**
     * @param string   $status   WooCommerce's stock status: instock, outofstock, onbackorder.
     * @param int|null $quantity The stock quantity when WooCommerce manages it, null otherwise.
     * @param int      $low      "Few left" from this quantity down; 0 never says it.
     * @return array{level: string, text: string, percent: int}
     *   level: out | backorder | low | in; percent: the bar's fill for "low"
     */
    public static function state(string $status, ?int $quantity, int $low): array
    {
        if ('outofstock' === $status) {
            return ['level' => 'out', 'text' => 'Esaurito', 'percent' => 0];
        }
        if ('onbackorder' === $status && (null === $quantity || $quantity <= 0)) {
            return ['level' => 'backorder', 'text' => 'Disponibile su ordinazione', 'percent' => 0];
        }
        if (null !== $quantity && $quantity > 0 && $low > 0 && $quantity <= $low) {
            return [
                'level'   => 'low',
                'text'    => 1 === $quantity ? 'Ultimo disponibile' : sprintf('Solo %d disponibili', $quantity),
                // Never full: at the threshold the bar is most of the way, one left is a sliver.
                'percent' => max(8, (int) round(100 * $quantity / ($low + 1))),
            ];
        }
        return ['level' => 'in', 'text' => 'Disponibile', 'percent' => 100];
    }
}
