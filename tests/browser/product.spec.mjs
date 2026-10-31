// The product page's script (@hive/product, @hive/gallery, @hive/lightbox)
// in a real browser: a fixture page with Hive\Shop\ProductPage's markup and
// a stand-in for WooCommerce's variation script (fixtures/wc-variations.js).
import { expect, test } from '@playwright/test';

const page_ = '/tests/browser/fixtures/product.html';

async function open(page) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    // WooCommerce's alert() is what Hive replaces: none may appear.
    page.on('dialog', (dialog) => {
        errors.push(`alert: ${dialog.message()}`);
        dialog.dismiss();
    });
    await page.goto(page_);
    return errors;
}

const swatch = (page, size) => page.locator(`.hive-swatch[data-value="${size}"]`);
const add = (page) => page.locator('[data-hive-buy-add]');
const status = (page) => page.locator('[data-hive-buy-status]');

test('sizes show their price; sold out is still a choice; a pick brings its price, stock and photo', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const errors = await open(page);

    await expect(swatch(page, '40').locator('.hive-swatch__note')).toHaveText('150 €');
    await expect(swatch(page, '40')).toHaveClass(/is-sale/);
    await expect(swatch(page, '41').locator('.hive-swatch__note')).toHaveText('Esaurito');
    await expect(swatch(page, '41')).toBeEnabled();
    // The theme's rule for WooCommerce's select doesn't bring it back.
    await expect(page.locator('#pa_taglia')).toBeHidden();

    await swatch(page, '42').click();
    await expect(swatch(page, '42')).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('#pa_taglia')).toHaveValue('42');
    await expect(page.locator('[data-hive-swatches-picked]')).toHaveText(': 42');
    await expect(page.locator('[data-hive-price]')).toContainText('180,00');
    await expect(page.locator('.single_variation .hive-stock')).toHaveText('Disponibile');

    await swatch(page, '40').click();
    await expect(swatch(page, '42')).toHaveAttribute('aria-pressed', 'false');
    await expect(page.locator('[data-hive-price] ins')).toContainText('150,00');
    await expect(page.locator('.single_variation .hive-stock')).toContainText('Solo 2 disponibili');
    // Size 40 has its own photo, the third: the gallery goes to it.
    await expect(page.locator('[data-hive-gallery-go="2"]')).toHaveAttribute('aria-current', 'true');

    await swatch(page, '41').click();
    await expect(add(page)).toHaveText('Esaurito');
    await add(page).click();
    await expect(status(page)).toHaveText('Questa taglia è esaurita.');
    expect(errors).toEqual([]);
});

test('no size picked: the page says so, without WooCommerce’s alert', async ({ page }) => {
    const errors = await open(page);
    await add(page).click();
    await expect(status(page)).toHaveText('Scegli la taglia.');
    await expect(page.locator('[data-hive-swatches]')).toHaveClass(/is-invalid/);
    await expect(swatch(page, '40')).toBeFocused();
    expect(errors).toEqual([]);
});

test('adding posts the form as it is and refreshes the mini cart', async ({ page }) => {
    const errors = await open(page);
    let posted = '';
    await page.route('**/?wc-ajax=hive_add_to_cart', (route) => {
        posted = route.request().postData() || '';
        route.fulfill({
            contentType: 'application/json',
            body: JSON.stringify({ fragments: { 'div.widget_shopping_cart_content': '<div class="widget_shopping_cart_content">1 × Nike Dunk Low Panda</div>' }, cart_hash: 'abc' }),
        });
    });

    await swatch(page, '42').click();
    await page.getByRole('button', { name: 'Aumenta la quantità' }).click();
    await expect(page.locator('input.qty')).toHaveValue('2');
    await add(page).click();

    await expect(status(page)).toContainText('Aggiunto al carrello.');
    await expect(status(page).getByRole('link', { name: 'Vai al carrello' })).toHaveAttribute('href', '/carrello/');
    await expect(page.locator('.widget_shopping_cart_content')).toHaveText('1 × Nike Dunk Low Panda');
    expect(await page.evaluate(() => window.hiveEvents)).toEqual([['added_to_cart', 'abc']]);
    for (const field of ['name="add-to-cart"', 'name="attribute_pa_taglia"', 'name="quantity"', 'name="variation_id"']) {
        expect(posted).toContain(field);
    }
    expect(posted).toMatch(/name="attribute_pa_taglia"\r\n\r\n42/);
    expect(posted).toMatch(/name="quantity"\r\n\r\n2/);
    expect(page.url()).toContain('product.html');
    expect(errors).toEqual([]);
});

test('WooCommerce refuses: its reason, on the page', async ({ page }) => {
    await open(page);
    await page.route('**/?wc-ajax=hive_add_to_cart', (route) =>
        route.fulfill({ contentType: 'application/json', body: JSON.stringify({ error: true, messages: ['Ne abbiamo solo 2 in magazzino.'] }) })
    );
    await swatch(page, '40').click();
    await expect(add(page)).toHaveText('Aggiungi al carrello');
    await add(page).click();
    await expect(status(page)).toHaveText('Ne abbiamo solo 2 in magazzino.');
    await expect(status(page)).toHaveClass(/is-error/);
    await expect(add(page)).toHaveText('Aggiungi al carrello');
});

test('the quantity stays within the size’s stock', async ({ page }) => {
    await open(page);
    await swatch(page, '40').click();
    // WooCommerce sets the size's limits on the field (max 2 for size 40).
    await page.locator('input.qty').evaluate((input) => (input.max = '2'));
    const more = page.getByRole('button', { name: 'Aumenta la quantità' });
    await more.click();
    await more.click();
    await more.click();
    await expect(page.locator('input.qty')).toHaveValue('2');
    await page.getByRole('button', { name: 'Diminuisci la quantità' }).click();
    await page.getByRole('button', { name: 'Diminuisci la quantità' }).click();
    await expect(page.locator('input.qty')).toHaveValue('1');
});

test('Hive’s quantity and button beat the theme’s rules for WooCommerce’s classes', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await open(page);
    const qty = await page.locator('input.qty').evaluate((input) => ({ width: input.getBoundingClientRect().width, border: getComputedStyle(input).borderTopWidth }));
    expect(qty.width).toBeLessThan(60);
    expect(qty.border).toBe('0px');
    const button = await add(page).evaluate((node) => ({ float: getComputedStyle(node).float, background: getComputedStyle(node).backgroundColor }));
    expect(button).toEqual({ float: 'none', background: 'rgb(10, 10, 10)' });
});

test('gallery: thumbnails and arrows move through the photos; a photo opens full screen', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const errors = await open(page);
    const current = page.locator('.hive-gallery__thumb[aria-current="true"]');

    await page.locator('[data-hive-gallery-go="1"]').click();
    await expect(current).toHaveAttribute('data-hive-gallery-go', '1');
    await page.locator('.hive-gallery__stage').hover();
    await page.getByRole('button', { name: 'Foto successiva' }).click();
    await expect(current).toHaveAttribute('data-hive-gallery-go', '2');
    await expect(page.getByRole('button', { name: 'Foto successiva' })).toBeDisabled();

    await page.getByRole('link', { name: 'Ingrandisci la foto 3 di 3' }).click();
    const lightbox = page.locator('dialog[data-hive-dialog="lightbox"]');
    await expect(lightbox).toBeVisible();
    await expect(lightbox.locator('.hive-lightbox__image')).toHaveCount(3);
    await expect(lightbox.locator('.hive-lightbox__count')).toHaveText('3 / 3');
    await page.keyboard.press('ArrowLeft');
    await expect(lightbox.locator('.hive-lightbox__count')).toHaveText('2 / 3');

    // A click zooms in, another zooms out.
    const slide = lightbox.locator('.hive-lightbox__slide').nth(1);
    await slide.locator('img').click();
    await expect(slide).toHaveClass(/is-zoomed/);
    await slide.locator('img').click();
    await expect(slide).not.toHaveClass(/is-zoomed/);

    await page.keyboard.press('Escape');
    await expect(lightbox).toBeHidden();
    // Back on the page, the gallery shows the photo last seen.
    await expect(current).toHaveAttribute('data-hive-gallery-go', '1');
    expect(errors).toEqual([]);
});

test('phones: the photos swipe, with a count', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 });
    await open(page);
    await expect(page.locator('.hive-gallery__thumbs')).toBeHidden();
    await expect(page.locator('.hive-gallery__count')).toHaveText('1 / 3');
    await page.locator('[data-hive-gallery-track]').evaluate((track) => track.scrollTo({ left: track.clientWidth * 2, behavior: 'instant' }));
    await expect(page.locator('[data-hive-gallery-index]')).toHaveText('3');
});

test('sticky bar: shows once the button has scrolled away; with no size it brings the sizes back', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 700 });
    const errors = await open(page);
    const bar = page.locator('[data-hive-sticky]');
    await expect(bar).not.toHaveClass(/is-visible/);

    await page.locator('#hive-details-description').scrollIntoViewIfNeeded();
    await page.mouse.wheel(0, 600);
    await expect(bar).toHaveClass(/is-visible/);
    await expect(bar.getByRole('button')).toHaveText('Scegli la taglia');

    await bar.getByRole('button').click();
    await expect(status(page)).toHaveText('Scegli la taglia.');
    await expect(add(page)).toBeInViewport();
    await expect(bar).not.toHaveClass(/is-visible/);

    // At the end of the product's part of the page it steps aside for the footer.
    await page.locator('.site-footer').scrollIntoViewIfNeeded();
    await expect(bar).not.toHaveClass(/is-visible/);
    expect(errors).toEqual([]);
});

test('sticky bar: with a size picked it adds to the cart', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 700 });
    await open(page);
    let calls = 0;
    await page.route('**/?wc-ajax=hive_add_to_cart', (route) => {
        calls++;
        route.fulfill({ contentType: 'application/json', body: JSON.stringify({ fragments: {}, cart_hash: 'x' }) });
    });
    await swatch(page, '42').click();
    await page.locator('#hive-details-description').scrollIntoViewIfNeeded();
    await page.mouse.wheel(0, 600);
    const bar = page.locator('[data-hive-sticky]');
    await expect(bar.getByRole('button')).toHaveText('Aggiungi al carrello');
    await expect(bar.locator('[data-hive-sticky-price]')).toContainText('180,00');
    await bar.getByRole('button').click();
    await expect(bar.getByRole('button')).toHaveText('✓ Aggiunto');
    expect(calls).toBe(1);
});

test('the size guide opens in a dialog', async ({ page }) => {
    const errors = await open(page);
    await page.getByRole('button', { name: 'Guida alle taglie' }).click();
    const guide = page.locator('dialog[data-hive-dialog="size-guide"]');
    await expect(guide).toBeVisible();
    await expect(guide.getByRole('heading', { name: 'Guida taglie Nike' })).toBeVisible();
    await expect(guide.locator('tbody tr')).toHaveCount(3);
    await page.keyboard.press('Escape');
    await expect(guide).toBeHidden();
    await expect(page.getByRole('button', { name: 'Guida alle taglie' })).toBeFocused();
    expect(errors).toEqual([]);
});
