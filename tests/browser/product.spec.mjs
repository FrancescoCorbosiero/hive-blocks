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

<<<<<<< HEAD
test('a sold-out size offers "Avvisami", posted without leaving the page', async ({ page }) => {
    const errors = await open(page);
    let posted = '';
    await page.route('**/?wc-ajax=hive_alerts', (route) => {
        posted = route.request().postData() || '';
        route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, message: 'Ci siamo quasi: conferma dalla tua email e ti scriviamo appena torna disponibile.' }) });
    });
    const wait = page.locator('[data-hive-wait]');
    await expect(wait).toBeHidden();

    await swatch(page, '41').click();
    await expect(wait).toBeVisible();
    await expect(wait.locator('[data-hive-wait-size]')).toHaveText('41');

    await wait.getByRole('button', { name: 'Avvisami' }).click();
    await expect(wait.locator('[data-hive-wait-status]')).toHaveText('Scrivi un indirizzo email valido.');
    await wait.getByPlaceholder('La tua email').fill('mario@example.com');
    await wait.getByRole('button', { name: 'Avvisami' }).click();
    await expect(wait.locator('[data-hive-wait-status]')).toContainText('Ci siamo quasi');
    expect(posted).toMatch(/name="email"\r\n\r\nmario@example.com/);
    expect(posted).toMatch(/name="product"\r\n\r\n7/);
    expect(posted).toMatch(/name="key"\r\n\r\n41/);

    // A size in stock: nothing to wait for.
    await swatch(page, '42').click();
    await expect(wait).toBeHidden();
    expect(errors).toEqual([]);
});

test('"Chiedi su WhatsApp" names the size picked; "Condividi" copies the link', async ({ page, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await open(page);
    await page.evaluate(() =>
        document.addEventListener('click', (event) => event.target.closest('[data-hive-whatsapp]') && event.preventDefault(), true)
    );
    await swatch(page, '42').click();
    const whatsapp = page.getByRole('link', { name: 'Chiedi su WhatsApp' });
    await whatsapp.click();
    await expect(whatsapp).toHaveAttribute('href', /^https:\/\/wa\.me\/393331234567\?text=.*taglia%2042/);

    const share = page.locator('[data-hive-share]');
    await expect(share).toBeVisible();
    await share.click();
    await expect(share).toHaveText('Link copiato');
    expect(await page.evaluate(() => navigator.clipboard.readText())).toBe('https://example.test/prodotto/dunk/');
});

test('the delivery estimate, at the visitor’s time', async ({ page }) => {
    // Monday 19 October 2026, 10:30 in Italy.
    await page.clock.install({ time: new Date('2026-10-19T08:30:00Z') });
    await open(page);
    const box = page.locator('[data-hive-delivery]');
    await expect(box).toBeVisible();
    await expect(box.locator('.hive-delivery__line').first()).toHaveText('Ordina entro 2 h 30 min e spediamo oggi');
    await expect(box.locator('.hive-delivery__line').nth(1)).toHaveText(/^Consegna stimata mar 20 ott – gio 22 ott$/);

    // A sold-out size ships nothing.
    await swatch(page, '41').click();
    await expect(box).toBeHidden();
    await swatch(page, '42').click();
    await expect(box).toBeVisible();
});

test('"Visti di recente": this browser’s products, newest first, fetched as a rail', async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('hive_recent', JSON.stringify([3, 5])));
    let asked = '';
    await page.route('**/api/recent?**', (route) => {
        asked = route.request().url();
        const card = (id, name) => `<li class="hive-card" data-product-id="${id}"><div class="hive-card__body"><h3 class="hive-card__title"><a class="hive-card__link" href="#">${name}</a></h3></div></li>`;
        route.fulfill({
            contentType: 'application/json',
            body: JSON.stringify({ html: `<section class="hive-block hive-rail"><div class="hive-rail__head"><h2 class="hive-rail__title">Visti di recente</h2></div><div class="hive-rail__viewport"><ul class="hive-rail__track" role="list">${card(5, 'Felpa')}${card(3, 'Calzini')}</ul></div></section>` }),
        });
    });
    await open(page);
    expect(asked).toBe('');
    await page.locator('.site-footer').scrollIntoViewIfNeeded();
    await expect(page.locator('[data-hive-recent] .hive-rail__title')).toHaveText('Visti di recente');
    await expect(page.locator('[data-hive-recent] .hive-card__title')).toHaveText(['Calzini', 'Felpa']);
    expect(new URL(asked).searchParams.get('ids')).toBe('3,5');
    expect(new URL(asked).searchParams.get('v')).toBe('7');
    expect(await page.evaluate(() => JSON.parse(localStorage.getItem('hive_recent')))).toEqual([7, 3, 5]);
});

test('a drop counts down to the second, then the page reloads', async ({ page }) => {
    // Ten seconds before the release, the clock held still.
    await page.clock.install({ time: new Date('2026-10-24T07:59:40Z') });
    await page.clock.pauseAt(new Date('2026-10-24T07:59:50Z'));
    await page.goto('/tests/browser/fixtures/drop.html');
    const drop = page.locator('[data-hive-drop]');
    await expect(drop.locator('[data-unit="s"]')).toHaveText('10');
    await expect(drop.locator('[data-unit="d"]')).toHaveText('00');
    await page.clock.runFor('00:04');
    await expect(drop.locator('[data-unit="s"]')).toHaveText('06');

    await page.clock.runFor('00:07');
    await expect(drop).toHaveClass(/is-released/);
    await expect(drop.locator('.hive-remind')).toBeHidden();
    await expect(drop.getByText('È uscito')).toBeVisible();

    const reloaded = page.waitForEvent('load');
    await page.clock.runFor('00:08');
    await reloaded;
=======
test('sticky bar: a jump from above the button to past it (an anchor, a restored scroll) still shows it', async ({ page }) => {
    // Short screen: the button starts below it, never in view on the way down.
    await page.setViewportSize({ width: 1280, height: 320 });
    await open(page);
    const bar = page.locator('[data-hive-sticky]');
    await expect(add(page)).not.toBeInViewport();
    await add(page).evaluate((node) => window.scrollTo({ top: node.getBoundingClientRect().bottom + window.scrollY + 40, behavior: 'instant' }));
    await expect(bar).toHaveClass(/is-visible/);
    await expect(bar).toHaveAttribute('data-state', 'past');
>>>>>>> claude/step-4-product-page
});
