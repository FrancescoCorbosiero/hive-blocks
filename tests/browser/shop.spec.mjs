// The product cards' buttons, Quick View, Quick Add and the rail arrows, in a
// real browser: the built modules, with the product data and WooCommerce's
// cart endpoint answered by the test.
import { expect, test } from '@playwright/test';

const product = {
    id: 101,
    type: 'variable',
    title: 'Nike Dunk Low Panda',
    url: '/prodotto/nike-dunk/',
    brand: 'Nike',
    categories: ['Sneakers'],
    sku: 'DD1391',
    price_html: '<span class="amount">150,00 €</span> – <span class="amount">180,00 €</span>',
    summary: '<p>La Dunk più amata.</p>',
    images: [
        { src: '/tests/browser/fixtures/sneaker.svg', srcset: '', thumb: '/tests/browser/fixtures/sneaker.svg', alt: 'Dunk di lato', width: 600, height: 600 },
        { src: '/tests/browser/fixtures/sneaker.svg', srcset: '', thumb: '/tests/browser/fixtures/sneaker.svg', alt: 'Dunk dall’alto', width: 600, height: 600 },
    ],
    attributes: [{ label: 'Colorway', value: 'White/Black' }],
    in_stock: true,
    purchasable: true,
    state: { kind: 'variable', label: 'Aggiungi al carrello' },
    choices: [{ name: 'pa_taglia', label: 'Taglia', options: [{ value: '40', label: '40' }, { value: '41', label: '41' }, { value: '42', label: '42' }] }],
    variations: [
        { id: 1011, attributes: { pa_taglia: '40' }, in_stock: true, price_html: '<span class="amount">150,00 €</span>', price_text: '150,00 €', max_qty: 2, image: '' },
        { id: 1012, attributes: { pa_taglia: '41' }, in_stock: false, price_html: '', price_text: '', max_qty: -1, image: '' },
        { id: 1013, attributes: { pa_taglia: '42' }, in_stock: true, price_html: '<span class="amount">180,00 €</span>', price_text: '180,00 €', max_qty: -1, image: '' },
    ],
};

/** Opens the fixture with the product data and the cart endpoint mocked; returns the cart posts. */
async function shop(page, { refuse = false } = {}) {
    const posts = [];
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.route('**/api/product/101*', (route) => route.fulfill({ json: product }));
    await page.route('**/cart-endpoint', async (route) => {
        // The multipart form fields, as {name: value}.
        const fields = {};
        for (const [, name, value] of route.request().postDataBuffer().toString().matchAll(/name="([^"]+)"\r\n\r\n([^\r]*)/g)) fields[name] = value;
        posts.push(fields);
        await route.fulfill({
            json: refuse ? { error: true, product_url: '/prodotto/nike-dunk/' } : { fragments: { 'span.cart-count': `<span class="cart-count">${posts.length}</span>` }, cart_hash: 'abc' },
        });
    });
    await page.goto('/tests/browser/fixtures/shop.html');
    return { posts, errors };
}

const quickView = (page) => page.locator('dialog[data-hive-dialog="quick-view"]');
const quickAdd = (page) => page.locator('dialog[data-hive-dialog="quick-add"]');

test('Quick View: the product, its sizes, and the picked size into the cart', async ({ page }) => {
    const { posts, errors } = await shop(page);
    const peek = page.locator('.hive-card[data-product-id="101"] .hive-card__peek').first();
    await peek.click();

    const dialog = quickView(page);
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('.hive-qv__title')).toHaveText('Nike Dunk Low Panda');
    await expect(dialog.locator('.hive-qv__eyebrow')).toHaveText('Nike');
    await expect(page.locator('link[href="/assets/build/dialog.css"]')).toHaveCount(1);
    await expect(dialog.getByRole('button', { name: '41' })).toBeDisabled();

    // No size yet: a nudge, nothing posted.
    await dialog.getByRole('button', { name: 'Aggiungi al carrello' }).click();
    await expect(dialog.locator('.hive-buy__status')).toHaveText('Scegli una taglia.');
    expect(posts).toEqual([]);

    await dialog.getByRole('button', { name: '42' }).click();
    await expect(dialog.getByRole('button', { name: '42' })).toHaveAttribute('aria-pressed', 'true');
    await expect(dialog.locator('.hive-buy__add')).toHaveText('Aggiungi al carrello – 180,00 €');
    await expect(dialog.locator('.hive-qv__price')).toHaveText('180,00 €');

    await dialog.locator('.hive-buy__add').click();
    await expect(dialog.locator('.hive-buy__status')).toContainText('Aggiunto al carrello.');
    await expect(dialog.getByRole('link', { name: 'Vai al carrello' })).toHaveAttribute('href', '/carrello/');
    expect(posts).toEqual([{ product_id: '1013', quantity: '1' }]);
    await expect(page.locator('header .cart-count')).toHaveText('1');

    // WhatsApp names the picked size.
    const whatsapp = dialog.getByRole('link', { name: 'Chiedi su WhatsApp' });
    await whatsapp.dispatchEvent('click');
    expect(decodeURIComponent(await whatsapp.getAttribute('href'))).toContain('Nike Dunk Low Panda, taglia 42');

    // Esc closes it and gives the focus back.
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(peek).toBeFocused();
    expect(errors).toEqual([]);
});

test('Quick View opens with the size the shop filter matched', async ({ page }) => {
    await shop(page);
    await page.locator('#filtered .hive-card__peek').click();
    const dialog = quickView(page);
    await expect(dialog.getByRole('button', { name: '42' })).toHaveAttribute('aria-pressed', 'true');
    await expect(dialog.locator('.hive-buy__add')).toHaveText('Aggiungi al carrello – 180,00 €');
});

test('Quick Add: size, quantity up to what can be bought, add, close', async ({ page }) => {
    const { posts } = await shop(page);
    await page.locator('.hive-card[data-product-id="101"] .hive-card__add').first().click();

    const sheet = quickAdd(page);
    await expect(sheet).toBeVisible();
    await sheet.getByRole('button', { name: '40' }).click();
    const plus = sheet.getByRole('button', { name: 'Aumenta' });
    await plus.click();
    await plus.click();
    await plus.click();
    await expect(sheet.locator('.hive-buy__qty-value')).toHaveValue('2');

    await sheet.locator('.hive-buy__add').click();
    await expect(sheet).toBeHidden({ timeout: 4000 });
    expect(posts).toEqual([{ product_id: '1011', quantity: '2' }]);
});

test('Quick Add: when WooCommerce refuses, it says so and links the product', async ({ page }) => {
    await shop(page, { refuse: true });
    await page.locator('#filtered .hive-card__add').click();
    const sheet = quickAdd(page);
    await sheet.locator('.hive-buy__add').click();
    await expect(sheet.locator('.hive-buy__status')).toContainText('Non è stato possibile aggiungerlo.');
    await expect(sheet.getByRole('link', { name: 'Vedi il prodotto' })).toHaveAttribute('href', '/prodotto/nike-dunk/');
    await expect(sheet).toBeVisible();
});

test('a simple product goes straight into the cart', async ({ page }) => {
    const { posts } = await shop(page);
    const button = page.locator('.hive-card[data-product-id="102"] .hive-card__add');
    await button.click();
    await expect(button).toHaveText('✓ Aggiunto');
    expect(posts).toEqual([{ product_id: '102', quantity: '1' }]);
    await expect(page.locator('header .cart-count')).toHaveText('1');
});

test("the cards' own rules win over the theme's list and button rules", async ({ page }) => {
    await shop(page);
    const styles = await page.locator('.hive-grid').evaluate((grid) => {
        const card = grid.querySelector('.hive-card');
        const add = grid.querySelector('.hive-card__add');
        return {
            gridDisplay: getComputedStyle(grid).display,
            gridMarginLeft: getComputedStyle(grid).marginLeft,
            cardListStyle: getComputedStyle(card).listStyleType,
            addBackground: getComputedStyle(add).backgroundColor,
            addColor: getComputedStyle(add).color,
        };
    });
    expect(styles).toEqual({
        gridDisplay: 'grid',
        gridMarginLeft: '0px',
        cardListStyle: 'none',
        addBackground: 'rgb(10, 10, 10)',
        addColor: 'rgb(255, 255, 255)',
    });
});

test('rail arrows: shown when there is more, a page at a time, off at the ends', async ({ page }) => {
    await page.setViewportSize({ width: 1200, height: 900 });
    await shop(page);
    const rail = page.locator('.hive-rail');
    const prev = rail.getByRole('button', { name: 'Prodotti precedenti' });
    const next = rail.getByRole('button', { name: 'Prodotti successivi' });
    await expect(next).toBeVisible();
    await expect(prev).toBeDisabled();

    await next.click();
    await expect.poll(() => rail.locator('.hive-rail__track').evaluate((track) => track.scrollLeft)).toBeGreaterThan(300);
    await expect(prev).toBeEnabled();
    await next.click();
    await expect(next).toBeDisabled();
});
