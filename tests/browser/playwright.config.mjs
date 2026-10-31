// Browser tests without WordPress: the built blocks in fixture pages, in Chromium.
// npm run test:browser
import { defineConfig, devices } from '@playwright/test';

const port = 9401;

export default defineConfig({
    testDir: '.',
    testMatch: '*.spec.mjs',
    outputDir: '../../test-results/browser',
    forbidOnly: Boolean(process.env.CI),
    reporter: process.env.CI ? [['list'], ['github']] : 'list',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        trace: 'retain-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        command: `node tests/browser/serve.mjs`,
        cwd: '../..',
        env: { PORT: String(port) },
        url: `http://127.0.0.1:${port}/tests/browser/fixtures/store-hours.html`,
        reuseExistingServer: !process.env.CI,
    },
});
