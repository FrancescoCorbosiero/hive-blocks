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

/*
 * Helpers for the specs, on this test site only: the mail WordPress would
 * send (kept instead of sent), a subscriber's state, a size back in stock,
 * and the alerts' run, without waiting for the scheduler.
 */
add_filter(
    'pre_wp_mail',
    static function ($sent, array $mail) {
        $mails   = (array) get_option('hive_e2e_mails', []);
        $mails[] = ['to' => $mail['to'], 'subject' => $mail['subject'], 'message' => $mail['message']];
        update_option('hive_e2e_mails', array_slice($mails, -20), false);
        return true;
    },
    10,
    2
);

add_action(
    'rest_api_init',
    static function (): void {
        $open = ['permission_callback' => '__return_true'];
        register_rest_route(
            'hive-e2e/v1',
            '/subscriber',
            $open + [
                'methods'  => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    $id = Hive\Shop\Alerts::subscriber((string) $request['email']);
                    if (!$id) {
                        return new WP_REST_Response(null, 404);
                    }
                    $meta = static fn(string $key) => get_post_meta($id, $key, true);
                    return [
                        'status'        => $meta('_hive_alerts'),
                        'token'         => $meta('_hive_token'),
                        'newsletter'    => (bool) $meta('_hive_newsletter'),
                        'sizes_pending' => array_values(array_filter((array) $meta('_hive_sizes_pending'))),
                        'wait'          => array_values(array_filter((array) $meta('_hive_wait'))),
                        'wait_pending'  => array_values(array_filter((array) $meta('_hive_wait_pending'))),
                    ];
                },
            ]
        );
        register_rest_route(
            'hive-e2e/v1',
            '/restock',
            $open + [
                'methods'  => 'POST',
                'callback' => static function (WP_REST_Request $request) {
                    $product = wc_get_product((int) $request['product']);
                    foreach ($product ? $product->get_children() : [] as $child) {
                        $variation = wc_get_product($child);
                        if ($variation && $variation->get_attribute('pa_taglia') === (string) $request['size']) {
                            $variation->set_stock_status('outofstock' === $request['status'] ? 'outofstock' : 'instock');
                            $variation->save();
                            return ['variation' => $variation->get_id()];
                        }
                    }
                    return new WP_REST_Response(null, 404);
                },
            ]
        );
        register_rest_route(
            'hive-e2e/v1',
            '/alerts-run',
            $open + [
                'methods'  => 'POST',
                'callback' => static function () {
                    delete_option('hive_e2e_mails');
                    do_action(Hive\Shop\Alerts::HOOK);
                    return ['mails' => (array) get_option('hive_e2e_mails', [])];
                },
            ]
        );
        register_rest_route(
            'hive-e2e/v1',
            '/mails',
            $open + [
                'methods'  => 'GET',
                'callback' => static fn() => ['mails' => (array) get_option('hive_e2e_mails', [])],
            ]
        );
    }
);

// More helpers, one file per area (tests/e2e/mu/*.php).
foreach (glob(__DIR__ . '/mu/*.php') ?: [] as $hive_e2e_file) {
    require $hive_e2e_file;
}
