<?php

// What the product page says about stock.

use Hive\Core\StockMeter;

check(StockMeter::state('outofstock', 0, 5), ['level' => 'out', 'text' => 'Esaurito', 'percent' => 0], 'sold out');
check(StockMeter::state('onbackorder', null, 5)['level'], 'backorder', 'on backorder');
check(StockMeter::state('onbackorder', 0, 5)['text'], 'Disponibile su ordinazione', 'on backorder at zero');
check(StockMeter::state('instock', 1, 5), ['level' => 'low', 'text' => 'Ultimo disponibile', 'percent' => 17], 'the last one');
check(StockMeter::state('instock', 3, 5), ['level' => 'low', 'text' => 'Solo 3 disponibili', 'percent' => 50], 'few left');
check(StockMeter::state('instock', 5, 5)['level'], 'low', 'at the threshold');
check(StockMeter::state('instock', 6, 5)['level'], 'in', 'over the threshold');
check(StockMeter::state('instock', null, 5), ['level' => 'in', 'text' => 'Disponibile', 'percent' => 100], 'stock not managed');
check(StockMeter::state('instock', 2, 0)['level'], 'in', 'threshold 0: never "few left"');
check(StockMeter::state('instock', 1, 50)['percent'], 8, 'the bar never vanishes');
