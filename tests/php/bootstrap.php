<?php
/**
 * Pure PHP tests, no WordPress: the Hive\Core classes and check().
 * Run them all with: php tests/php/run.php
 */

define('ABSPATH', __DIR__ . '/');

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'Hive\\Core\\';
        if (str_starts_with($class, $prefix)) {
            require dirname(__DIR__, 2) . '/src/Core/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        }
    }
);

final class Results
{
    public static int $passes = 0;

    /** @var list<string> */
    public static array $failures = [];

    public static string $file = '';
}

/** Strict comparison; a failure is reported with both values. */
function check(mixed $actual, mixed $expected, string $what): void
{
    if ($actual === $expected) {
        Results::$passes++;
        return;
    }
    Results::$failures[] = sprintf(
        "%s: %s\n      expected %s\n      actual   %s",
        Results::$file,
        $what,
        var_export($expected, true),
        var_export($actual, true)
    );
}
