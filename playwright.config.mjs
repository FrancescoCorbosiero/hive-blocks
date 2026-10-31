// End-to-end tests: real WordPress and WooCommerce in WordPress Playground,
// with the theme in HIVE_E2E_THEME (tests/e2e/serve.mjs lists the options).
// npm run test:e2e: needs access to wordpress.org, as on GitHub Actions.
import { defineConfig, devices } from '@playwright/test';

const port = 9400;

export default defineConfig({
    testDir: 'tests/e2e',
    testMatch: '*.spec.mjs',
    outputDir: 'test-results/e2e',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    workers: 1,
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    reporter: process.env.CI ? [['list'], ['github']] : 'list',
    globalTeardown: './tests/e2e/global-teardown.mjs',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        command: 'node tests/e2e/serve.mjs',
        // A fixture page: it exists only once the whole blueprint has run.
        url: `http://127.0.0.1:${port}/?pagename=orari`,
        timeout: 10 * 60_000,
        reuseExistingServer: !process.env.CI,
        stdout: 'pipe',
        stderr: 'pipe',
    },
});
