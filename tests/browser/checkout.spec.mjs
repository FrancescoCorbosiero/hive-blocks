// The checkout's Hive parts (@hive/checkout): the order bump and the bank
// details, on a fixture page with a classic checkout's markup.
import { expect, test } from '@playwright/test';

test('the bump adds its product, then WooCommerce refreshes the order review', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    let posted = '';
    await page.route('**/cart-endpoint', (route) => {
        posted = route.request().postData() || '';
        route.fulfill({ contentType: 'application/json', body: JSON.stringify({ fragments: {}, cart_hash: 'x' }) });
    });
    await page.goto('/tests/browser/fixtures/checkout.html');
    const bump = page.locator('[data-hive-bump]');
    await bump.getByRole('button', { name: 'Aggiungi all’ordine' }).click();
    await expect(bump.locator('[data-hive-bump-status]')).toHaveText('Aggiunto all’ordine.');
    await expect(bump.getByRole('button')).toHaveText('✓ Aggiunto');
    expect(posted).toMatch(/name="product_id"\r\n\r\n50/);
    expect(await page.evaluate(() => window.hiveEvents)).toEqual(['added_to_cart', 'update_checkout']);
    expect(errors).toEqual([]);
});

test('the bank details open in a dialog; the IBAN is one tap from the clipboard', async ({ page, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await page.goto('/tests/browser/fixtures/checkout.html');
    await page.getByRole('button', { name: 'Mostra coordinate bancarie per il bonifico' }).click();
    const sheet = page.locator('dialog[data-hive-dialog="bank-details"]');
    await expect(sheet.getByRole('heading', { name: 'Coordinate bancarie' })).toBeVisible();
    await sheet.getByRole('button', { name: 'Copia' }).click();
    await expect(sheet.getByRole('button', { name: 'Copiato' })).toBeVisible();
    await expect(sheet.locator('[data-hive-copy-status]')).toHaveText('IBAN copiato');
    expect(await page.evaluate(() => navigator.clipboard.readText())).toBe('IT60X0542811101000000123456');
    await page.keyboard.press('Escape');
    await expect(sheet).toBeHidden();
});
