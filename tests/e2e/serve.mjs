#!/usr/bin/env node
/**
 * Starts WordPress for the e2e tests with WordPress Playground: WooCommerce,
 * the theme under test, this plugin (mounted from the working copy) and the
 * fixtures the specs visit (fixtures.php). Playwright runs it
 * (playwright.config.mjs); it needs access to wordpress.org.
 *
 *   HIVE_E2E_THEME  twentytwentyfive (block theme, default) | storefront (classic) | any wordpress.org theme
 *   HIVE_E2E_WP     latest (default) | 6.8 …
 *   HIVE_E2E_WC     latest (default) | 9.9.5 …
 *   HIVE_E2E_PHP    8.3 (default) | 8.1 …
 */
import { spawn } from 'node:child_process';
import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const PLAYGROUND = '@wp-playground/cli@3.1.61';
const root = path.resolve(import.meta.dirname, '../..');
const artifacts = path.join(root, 'tests/e2e/artifacts');
const plugin = '/wordpress/wp-content/plugins/hive-blocks';

const theme = process.env.HIVE_E2E_THEME || 'twentytwentyfive';
const wp = process.env.HIVE_E2E_WP || 'latest';
const wc = process.env.HIVE_E2E_WC || 'latest';
const php = process.env.HIVE_E2E_PHP || '8.3';
const bundledThemes = ['twentytwentyfive', 'twentytwentyfour', 'twentytwentythree'];

rmSync(artifacts, { recursive: true, force: true });
mkdirSync(path.join(artifacts, 'screenshots'), { recursive: true });

const blueprint = {
    $schema: 'https://playground.wordpress.net/blueprint-schema.json',
    steps: [
        // Records PHP issues raised through Hive (tests/e2e/mu-plugin.php).
        {
            step: 'runPHP',
            code: `<?php @mkdir('/wordpress/wp-content/mu-plugins', 0777, true); file_put_contents('/wordpress/wp-content/mu-plugins/hive-e2e.php', "<?php require '${plugin}/tests/e2e/mu-plugin.php';\\n");`,
        },
        {
            step: 'defineWpConfigConsts',
            consts: { WP_DEBUG: true, WP_DEBUG_DISPLAY: false, WP_DEBUG_LOG: `${plugin}/tests/e2e/artifacts/debug.log` },
        },
        {
            step: 'installPlugin',
            pluginData: wc === 'latest'
                ? { resource: 'wordpress.org/plugins', slug: 'woocommerce' }
                : { resource: 'url', url: `https://downloads.wordpress.org/plugin/woocommerce.${wc}.zip` },
            options: { activate: true },
        },
        bundledThemes.includes(theme)
            ? { step: 'activateTheme', themeFolderName: theme }
            : { step: 'installTheme', themeData: { resource: 'wordpress.org/themes', slug: theme }, options: { activate: true } },
        { step: 'activatePlugin', pluginPath: 'hive-blocks/hive-blocks.php' },
        {
            step: 'setSiteOptions',
            options: {
                blogname: 'Hive e2e',
                timezone_string: 'Europe/Rome',
                woocommerce_coming_soon: 'no',
                woocommerce_store_pages_only: 'no',
            },
        },
        { step: 'runPHP', code: `<?php require '/wordpress/wp-load.php'; require '${plugin}/tests/e2e/fixtures.php';` },
    ],
};

const file = path.join(artifacts, 'blueprint.json');
writeFileSync(file, JSON.stringify(blueprint, null, 2));
console.log(`WordPress ${wp}, WooCommerce ${wc}, PHP ${php}, theme ${theme}`);

const child = spawn(
    'npx',
    ['--yes', PLAYGROUND, 'server', '--port=9400', `--wp=${wp}`, `--php=${php}`, `--mount=${root}:${plugin}`, `--blueprint=${file}`],
    { stdio: 'inherit' }
);
for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => child.kill(signal));
}
child.on('exit', (code) => process.exit(code ?? 1));
