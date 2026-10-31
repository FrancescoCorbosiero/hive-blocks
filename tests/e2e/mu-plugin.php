<?php
/**
 * E2E only, installed as an mu-plugin by tests/e2e/serve.mjs: records every
 * PHP notice, warning or deprecation, and every "doing it wrong", whose call
 * stack goes through Hive Blocks. tests/e2e/global-teardown.mjs fails the
 * run on them. Issues from WordPress, WooCommerce or the theme alone are
 * not Hive's and are left out.
 */

const HIVE_E2E_LOG = '/wordpress/wp-content/plugins/hive-blocks/tests/e2e/artifacts/issues.log';

/** The first Hive file (not a test) in the current call stack, as file:line; null when Hive isn't involved. */
function hive_e2e_where(): ?string
{
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
        $file = $frame['file'] ?? '';
        if (str_contains($file, '/plugins/hive-blocks/') && !str_contains($file, '/plugins/hive-blocks/tests/')) {
            return $file . ':' . ($frame['line'] ?? 0);
        }
    }
    return null;
}

function hive_e2e_record(string $issue, ?string $where): void
{
    if (null !== $where) {
        file_put_contents(HIVE_E2E_LOG, $issue . ' (' . $where . ")\n", FILE_APPEND);
    }
}

set_error_handler(
    static function (int $level, string $message, string $file = '', int $line = 0): bool {
        if (error_reporting() & $level) {
            $hive = str_contains($file, '/plugins/hive-blocks/') && !str_contains($file, '/plugins/hive-blocks/tests/');
            hive_e2e_record("PHP error $level: $message", $hive ? "$file:$line" : hive_e2e_where());
        }
        return false; // PHP and WordPress go on handling it as usual.
    }
);

foreach (['doing_it_wrong_run', 'deprecated_function_run', 'deprecated_argument_run', 'deprecated_hook_run', 'deprecated_file_included', 'deprecated_class_run'] as $hive_e2e_hook) {
    add_action(
        $hive_e2e_hook,
        static function (...$args) use ($hive_e2e_hook): void {
            hive_e2e_record($hive_e2e_hook . ': ' . implode(' | ', array_filter($args, 'is_scalar')), hive_e2e_where());
        },
        10,
        4
    );
}
