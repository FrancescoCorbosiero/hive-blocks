// The checkout's Hive parts on a real WooCommerce store (classic checkout,
// as on the store): the order bump (Hive → Offerte al checkout) and the
// bank details. Fixtures: tests/e2e/fixtures.php.
import { expect, test } from '@playwright/test';
import { hiveErrors } from './helpers.mjs';

async function cartNames(page) {
    const cart = await (await page.request.get('/?rest_route=/wc/store/v1/cart')).json();
    return cart.items.map((item) => item.name).sort();
}

test('the bump offers the socks to a cart with sneakers; one click adds them', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?product=nike-dunk-low-panda');
    await page.locator('.hive-swatch[data-value="42"]').click();
    await page.locator('[data-hive-buy-add]').click();
    await expect(page.locator('[data-hive-buy-status]')).toContainText('Aggiunto al carrello.');

    await page.goto('/?pagename=checkout');
    const bump = page.locator('.hive-bump[data-hive-bump]:not([hidden])');
    await expect(bump.locator('.hive-bump__name')).toHaveText('Calzini Hive');
    await expect(bump.locator('.hive-bump__note')).toHaveText('Il paio giusto per le tue sneakers.');
    await bump.getByRole('button', { name: 'Aggiungi all’ordine' }).click();
    await expect(page.locator('.woocommerce-checkout-review-order-table')).toContainText('Calzini Hive');
    expect(await cartNames(page)).toEqual(['Calzini Hive', 'Nike Dunk Low Panda']);
    // After WooCommerce's refresh, nothing more fits: the bump steps aside.
    await expect(page.locator('.hive-bump[data-hive-bump]:not([hidden])')).toHaveCount(0);
    expect(errors).toEqual([]);
});

test('the bank details, from WooCommerce’s own account', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?product=calzini-hive');
    await page.locator('[data-hive-buy-add]').click();
    await expect(page.locator('[data-hive-buy-status]')).toContainText('Aggiunto al carrello.');
    await page.goto('/?pagename=checkout');
    await page.getByRole('button', { name: 'Mostra coordinate bancarie per il bonifico' }).click();
    const sheet = page.locator('dialog[data-hive-dialog="bank-details"]');
    await expect(sheet).toContainText('IT60X0542811101000000123456');
    await expect(sheet).toContainText('Banca di Piacenza');
    expect(errors).toEqual([]);
});
