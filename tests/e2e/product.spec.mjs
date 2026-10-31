// The product page drawn by Hive (module "Pagina prodotto") on a real
// WooCommerce store, with WooCommerce's own variation script underneath.
// Products from tests/e2e/fixtures.php: a pair in sizes 40 (on sale, 2 left),
// 41 (sold out) and 42, with three photos and a size guide (its category's);
// a simple product; a sold-out one.
import { expect, test } from '@playwright/test';
import { hiveErrors } from './helpers.mjs';

const dunk = '/?product=nike-dunk-low-panda';
const swatch = (page, size) => page.locator(`.hive-swatch[data-value="${size}"]`);
const status = (page) => page.locator('[data-hive-buy-status]');

/** The cart as WooCommerce's Store API sees it, with this browser's session. */
async function cart(page) {
    const response = await page.request.get('/?rest_route=/wc/store/v1/cart');
    expect(response.ok()).toBe(true);
    return response.json();
}

test('the product page is Hive’s, inside the theme’s header and footer, without WooCommerce’s gallery scripts', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto(dunk);
    await expect(page.locator('header').first()).toBeVisible();
    await expect(page.locator('.hive-product h1.hive-product__title')).toHaveText('Nike Dunk Low Panda');
    await expect(page.locator('.hive-gallery__slide')).toHaveCount(3);
    await expect(page.locator('.hive-gallery__slide').first().locator('img')).toHaveAttribute('fetchpriority', 'high');
    await expect(page.locator('.woocommerce-product-gallery')).toHaveCount(0);
    await expect(page.locator('script[src*="flexslider"], script[src*="photoswipe"], script[src*="zoom"]')).toHaveCount(0);
    await expect(page.locator('.pswp')).toHaveCount(0);

    // Plugins on WooCommerce's hooks keep working: WooCommerce's own structured data is one.
    const data = await page.locator('script[type="application/ld+json"]').allTextContents();
    expect(data.join('\n')).toContain('"Product"');

    // The size guide of the product's category.
    await page.getByRole('button', { name: 'Guida alle taglie' }).click();
    const guide = page.locator('dialog[data-hive-dialog="size-guide"]');
    await expect(guide.getByRole('heading', { name: 'Guida taglie sneakers' })).toBeVisible();
    await expect(guide.locator('tbody tr')).toHaveCount(3);
    await page.keyboard.press('Escape');
    expect(errors).toEqual([]);
});

test('sizes with their price; a pick brings its price and stock; it goes into the cart without leaving the page', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const errors = hiveErrors(page);
    const alerts = [];
    page.on('dialog', (dialog) => {
        alerts.push(dialog.message());
        dialog.dismiss();
    });
    await page.goto(dunk);

    await expect(swatch(page, '40').locator('.hive-swatch__note')).toHaveText(/^150\s€$/);
    await expect(swatch(page, '40')).toHaveClass(/is-sale/);
    await expect(swatch(page, '41').locator('.hive-swatch__note')).toHaveText('Esaurito');
    await expect(swatch(page, '42').locator('.hive-swatch__note')).toHaveText(/^180\s€$/);

    // No size yet: the page asks for one (not WooCommerce's alert).
    await page.locator('[data-hive-buy-add]').click();
    await expect(status(page)).toHaveText('Scegli la taglia.');

    await swatch(page, '41').click();
    await expect(page.locator('[data-hive-buy-add]')).toHaveText('Esaurito');

    await swatch(page, '40').click();
    await expect(page.locator('[data-hive-price]')).toContainText('150,00');
    await expect(page.locator('.single_variation .hive-stock')).toContainText('Solo 2 disponibili');
    const url = page.url();
    await page.locator('[data-hive-buy-add]').click();
    await expect(status(page)).toContainText('Aggiunto al carrello.');
    expect(page.url()).toBe(url);

    const items = (await cart(page)).items;
    expect(items).toHaveLength(1);
    expect(items[0].name).toBe('Nike Dunk Low Panda');
    expect(items[0].variation.map((row) => row.value)).toEqual(['40']);
    expect(alerts).toEqual([]);
    expect(errors).toEqual([]);
});

test('WooCommerce’s own refusal shows on the page: no more than the stock', async ({ page }) => {
    await page.goto(dunk);
    await swatch(page, '40').click();
    await expect(page.locator('input.qty')).toHaveAttribute('max', '2');
    const add = async () => {
        const answer = page.waitForResponse((response) => response.url().includes('wc-ajax=hive_add_to_cart'));
        await page.locator('[data-hive-buy-add]').click();
        return (await answer).json();
    };
    // Two adds, one after the other: the form is ready again as soon as WooCommerce answers.
    expect((await add()).error).toBeUndefined();
    expect((await add()).error).toBeUndefined();
    expect((await cart(page)).items[0].quantity).toBe(2);

    // A third is more than the stock: WooCommerce refuses, and the page says why.
    const refused = await add();
    expect(refused.error).toBe(true);
    await expect(status(page)).toHaveClass(/is-error/);
    await expect(status(page)).toContainText(refused.messages[0].slice(0, 20));
    expect((await cart(page)).items[0].quantity).toBe(2);
});

test('the sticky bar takes over once the button scrolls away', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 640 });
    await page.goto(dunk);
    const bar = page.locator('[data-hive-sticky]');
    const button = page.locator('[data-hive-buy-add]');
    await expect(bar).not.toHaveClass(/is-visible/);

    // Just past the button: it's above the screen, the end of the product's part is below it.
    const layout = await button.evaluate((node) => {
        window.scrollTo({ top: node.getBoundingClientRect().bottom + window.scrollY + 40, behavior: 'instant' });
        const end = node.closest('[data-hive-product]').getBoundingClientRect();
        return { scrollY: Math.round(window.scrollY), viewport: window.innerHeight, page: document.documentElement.scrollHeight, productEnd: Math.round(end.bottom) };
    });
    await expect(button).not.toBeInViewport();
    await expect(bar, JSON.stringify(layout)).toHaveClass(/is-visible/);
    await bar.getByRole('button', { name: 'Scegli la taglia' }).click();
    await expect(status(page)).toHaveText('Scegli la taglia.');
    await expect(page.locator('[data-hive-buy-add]')).toBeInViewport();
});

test('a link with a size opens the page with it picked', async ({ page }) => {
    await page.goto(`${dunk}&attribute_pa_taglia=42`);
    await expect(swatch(page, '42')).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('[data-hive-swatches-picked]')).toHaveText(': 42');
    await expect(page.locator('[data-hive-price]')).toContainText('180,00');
});

test('a simple product: quantity and add to cart', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?product=calzini-hive');
    await expect(page.locator('.hive-product__title')).toHaveText('Calzini Hive');
    await page.getByRole('button', { name: 'Aumenta la quantità' }).click();
    await page.locator('[data-hive-buy-add]').click();
    await expect(status(page)).toContainText('Aggiunto al carrello.');
    const items = (await cart(page)).items;
    expect(items.map((item) => [item.name, item.quantity])).toEqual([['Calzini Hive', 2]]);
    expect(errors).toEqual([]);
});

test('a sold-out product says so, with nothing to add', async ({ page }) => {
    await page.goto('/?product=felpa-esaurita');
    await expect(page.locator('.hive-product .hive-stock.is-out')).toHaveText('Esaurito');
    await expect(page.locator('[data-hive-buy-add]')).toHaveCount(0);
    await expect(page.locator('.hive-gallery__badge.is-sold-out')).toHaveText('Esaurito');
});
