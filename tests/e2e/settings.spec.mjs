// Hive → Aspetto: the accent saved in the admin reaches the pages.
import { expect, test } from '@playwright/test';
import { login, rootVar } from './helpers.mjs';

// wp-admin with WooCommerce asks for many things at once, served one by one (one PHP worker).
test.slow();

async function saveAccent(page, color) {
    await page.goto('/wp-admin/admin.php?page=hive');
    await expect(page.locator('.wrap > h1')).toHaveText('Hive');
    await page.locator('#hive-accent').evaluate((input, value) => {
        input.value = value;
    }, color);
    await page.getByRole('button', { name: 'Salva le modifiche' }).click();
    await expect(page.locator('#setting-error-settings_updated')).toBeVisible();
    await expect(page.locator('#hive-accent')).toHaveValue(color);
}

test('the accent colour set in Hive reaches the pages, with readable text on it', async ({ page }) => {
    await login(page);

    await saveAccent(page, '#f5d300');
    await page.goto('/?pagename=orari');
    expect(await rootVar(page, '--hive-accent')).toBe('#f5d300');
    expect(await rootVar(page, '--hive-on-accent')).toBe('#0a0a0a');

    // Back to the store's colour, which the other specs expect.
    await saveAccent(page, '#721124');
    await page.goto('/?pagename=orari');
    expect(await rootVar(page, '--hive-accent')).toBe('#721124');
});
