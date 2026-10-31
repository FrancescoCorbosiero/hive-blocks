// The site is the one the job's name says: its PHP, WordPress and
// WooCommerce versions (tests/e2e/serve.mjs asks Playground for them).
import { expect, test } from '@playwright/test';

test('the site runs the PHP, WordPress and WooCommerce the job asks for', async ({ request }) => {
    const env = await (await request.get('/?rest_route=/hive-e2e/v1/env')).json();
    const php = process.env.HIVE_E2E_PHP || '8.3';
    expect(env.php.split('.').slice(0, 2).join('.'), `PHP ${env.php}`).toBe(php);
    const wp = process.env.HIVE_E2E_WP || 'latest';
    if (wp !== 'latest') expect(env.wp === wp || env.wp.startsWith(`${wp}.`), `WordPress ${env.wp}`).toBe(true);
    const wc = process.env.HIVE_E2E_WC || 'latest';
    if (wc !== 'latest') expect(env.wc, `WooCommerce ${env.wc}`).toBe(wc);
});
