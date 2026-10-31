<?php
/**
 * Runs every tests/php/*Test.php. From the plugin root: php tests/php/run.php
 */

require __DIR__ . '/bootstrap.php';

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    Results::$file = basename($file);
    require $file;
}

foreach (Results::$failures as $failure) {
    echo 'FAIL ', $failure, "\n";
}
printf("PHP: %d passed, %d failed\n", Results::$passes, count(Results::$failures));
exit(Results::$failures ? 1 : 0);
