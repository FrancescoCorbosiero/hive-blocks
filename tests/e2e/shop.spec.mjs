// Product cards, rails, Quick View and Quick Add on a real WooCommerce store
// (products from tests/e2e/fixtures.php: a pair in sizes 40 on sale, 41 sold
// out, 42; a simple product; a sold-out one).
import { expect, test } from '@playwright/test';
import { blockTheme, hiveErrors } from './helpers.mjs';

const card = (page, name) => page.locator('.hive-card', { hasText: name });

/** The cart, from WooCommerce's Store API, as [name, quantity, size] rows. */
async function cart(page) {
    const response = await page.request.get('/?rest_route=/wc/store/v1/cart');
    const data = await response.json();
    return data.items.map((item) => [item.name, item.quantity, item.variation?.[0]?.value ?? '']).sort((a, b) => String(a[2]).localeCompare(String(b[2])));
}

test('a rail of Hive cards: sale badge, sold out, the theme left out', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?pagename=rail');
    const rail = page.locator('main .hive-rail');
    await expect(rail.locator('.hive-rail__title')).toHaveText('Novità');
    await expect(rail.locator('.hive-card')).toHaveCount(3);
    await expect(rail.locator('h3.hive-card__title')).toHaveCount(3);
    await expect(card(rail, 'Nike Dunk Low Panda').locator('.hive-card__badge')).toHaveText('Fino a -17%');
    await expect(card(rail, 'Felpa esaurita').locator('.hive-card__add')).toHaveText('Esaurito');
    expect(errors).toEqual([]);
});

test('Quick View and Quick Add put the chosen sizes in the cart', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?pagename=rail');
    const dunk = card(page.locator('main'), 'Nike Dunk Low Panda');

    await dunk.hover();
    await dunk.locator('.hive-card__peek').click();
    const view = page.locator('dialog[data-hive-dialog="quick-view"]');
    await expect(view.locator('.hive-qv__title')).toHaveText('Nike Dunk Low Panda');
    await expect(view.getByRole('button', { name: '41', exact: true })).toBeDisabled();
    await view.getByRole('button', { name: '42', exact: true }).click();
    await expect(view.locator('.hive-buy__add')).toHaveText(/Aggiungi al carrello – 180,00/);
    await view.locator('.hive-buy__add').click();
    await expect(view.locator('.hive-buy__status')).toContainText('Aggiunto al carrello.');
    await page.keyboard.press('Escape');
    await expect(view).toBeHidden();

    await dunk.locator('.hive-card__add').click();
    const sheet = page.locator('dialog[data-hive-dialog="quick-add"]');
    await sheet.getByRole('button', { name: '40', exact: true }).click();
    await expect(sheet.locator('.hive-buy__add')).toHaveText(/150,00/);
    await sheet.locator('.hive-buy__add').click();
    await expect(sheet).toBeHidden({ timeout: 5000 });

    expect(await cart(page)).toEqual([
        ['Nike Dunk Low Panda', 1, '40'],
        ['Nike Dunk Low Panda', 1, '42'],
    ]);
    expect(errors).toEqual([]);
});

test('a simple product goes straight into the cart', async ({ page }) => {
    await page.goto('/?pagename=rail');
    const button = card(page.locator('main'), 'Calzini Hive').locator('.hive-card__add');
    await button.click();
    await expect(button).toHaveText('✓ Aggiunto');
    expect(await cart(page)).toEqual([['Calzini Hive', 1, '']]);
});

test('product data is a public REST read the CDN can cache', async ({ page }) => {
    await page.goto('/?pagename=rail');
    const id = await card(page.locator('main'), 'Nike Dunk Low Panda').getAttribute('data-product-id');

    const versioned = await page.request.get(`/?rest_route=/hive/v1/product/${id}&v=1`);
    expect(versioned.status()).toBe(200);
    expect(versioned.headers()['cache-control']).toContain('s-maxage=86400');
    const data = await versioned.json();
    expect(data.choices[0].options.map((option) => option.label)).toEqual(['40', '41', '42']);
    expect(data.variations.map((variation) => [variation.attributes.pa_taglia, variation.in_stock])).toEqual([
        ['40', true],
        ['41', false],
        ['42', true],
    ]);

    const plain = await page.request.get(`/?rest_route=/hive/v1/product/${id}`);
    expect(plain.headers()['cache-control']).toContain('max-age=60');
    expect((await page.request.get('/?rest_route=/hive/v1/product/999999')).status()).toBe(404);
});

test.describe("WooCommerce's own lists", () => {
    test.skip(blockTheme, 'Block themes list products with blocks: the catalog (step 3) takes those over.');

    test('the shop shows Hive cards in Hive’s grid, not the theme’s', async ({ page }) => {
        await page.goto('/?post_type=product');
        await expect(page.locator('ul.hive-grid .hive-card')).toHaveCount(3);
        await expect(page.locator('ul.products')).toHaveCount(0);
    });

    test('with a size in the filter, the card names that size’s price and Quick Add starts from it', async ({ page }) => {
        await page.goto('/?post_type=product&filter_taglia=42');
        const dunk = card(page, 'Nike Dunk Low Panda');
        await expect(dunk.locator('.hive-card__match')).toHaveText(/^Taglia 42 · 180,00/);
        await dunk.locator('.hive-card__add').click();
        const sheet = page.locator('dialog[data-hive-dialog="quick-add"]');
        await expect(sheet.getByRole('button', { name: '42', exact: true })).toHaveAttribute('aria-pressed', 'true');
    });
});
