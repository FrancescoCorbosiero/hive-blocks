// "Nella tua taglia" (@hive/size-rail): the remembered size fills the rail
// from the size read; nothing in a size offers the size alert.
import { expect, test } from '@playwright/test';

const rail = (label) =>
    `<section class="hive-block hive-rail"><div class="hive-rail__viewport"><ul class="hive-rail__track" role="list"><li class="hive-card" data-product-id="7"><div class="hive-card__body"><h3 class="hive-card__title"><a class="hive-card__link" href="#">Nike Dunk Low Panda</a></h3><p class="hive-card__match">Taglia ${label} · 180,00 €</p></div></li></ul></div></section>`;

async function routes(page) {
    const asked = [];
    await page.route('**/api/size-rail?**', (route) => {
        const size = new URL(route.request().url()).searchParams.get('size');
        asked.push(size);
        route.fulfill({ contentType: 'application/json', body: JSON.stringify({ key: size, label: size, html: size === '41' ? '' : rail(size), alerts: true }) });
    });
    return asked;
}

test('a size picked fills the rail, and is remembered for the next visit', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const asked = await routes(page);
    await page.goto('/tests/browser/fixtures/size-rail.html');
    await page.getByRole('button', { name: '42', exact: true }).click();
    await expect(page.locator('[data-hive-size-current]')).toHaveText('· 42');
    await expect(page.locator('.hive-card__match')).toHaveText('Taglia 42 · 180,00 €');
    await expect(page.locator('[data-hive-size-picker]')).toBeHidden();

    await page.reload();
    await expect(page.locator('.hive-card__match')).toHaveText('Taglia 42 · 180,00 €');
    expect(asked).toEqual(['42', '42']);

    await page.getByRole('button', { name: 'Cambia taglia' }).click();
    await expect(page.locator('[data-hive-size-picker]')).toBeVisible();
    await expect(page.getByRole('button', { name: '42', exact: true })).toHaveAttribute('aria-pressed', 'true');
    expect(errors).toEqual([]);
});

test('nothing in a size: the size alert, sent without leaving the page', async ({ page }) => {
    await routes(page);
    let posted = '';
    await page.route('**/?wc-ajax=hive_alerts', (route) => {
        posted = route.request().postData() || '';
        route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, message: 'Ci siamo quasi: conferma dalla tua email gli avvisi per la taglia 41.' }) });
    });
    await page.goto('/tests/browser/fixtures/size-rail.html');
    await page.getByRole('button', { name: '41', exact: true }).click();
    await expect(page.getByText('Nessun paio disponibile in taglia 41, per ora.')).toBeVisible();
    await page.getByPlaceholder('La tua email').fill('mario@example.com');
    await page.getByRole('button', { name: 'Avvisami' }).click();
    await expect(page.locator('.hive-size-rail__status')).toContainText('taglia 41');
    expect(posted).toMatch(/name="sizes\[\]"\r\n\r\n41/);
});
