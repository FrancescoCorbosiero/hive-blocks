// The commerce and marketing blocks (step 7, batch C) on a real WordPress +
// WooCommerce store, on the theme under test, with their default attributes
// (tests/e2e/pages/blocks-c.php). The store (tests/e2e/fixtures.php) has
// "Jordan 4 in arrivo" with a drop a week away, the "Sneakers" category with
// "Nike Dunk Low Panda" (the only product with photos) and "Calzini Hive" (the
// newest simple product in stock), no brands and no paid orders.
import { expect, test } from '@playwright/test';
import { hiveErrors, theme } from './helpers.mjs';

const url = '/?pagename=blocchi-c';
const promo = (page) => page.locator('dialog[data-hive-dialog="promo-promo-popup"]');

/** The promo popup opens by itself after 5 s: as if this visitor had seen it already. */
const promoSeen = (page) => page.addInitScript(() => localStorage.setItem('hive_promo_promo-popup', '1'));

test('each block draws itself from the store, with its own markup', async ({ page }) => {
    const errors = hiveErrors(page);
    await promoSeen(page);
    await page.goto(url);

    // The top-level categories with products ("Uncategorized", where the drop sits, left out),
    // with the photo of one of their products when they have none of their own.
    const slider = page.locator('.hive-category-slider');
    await expect(slider).toHaveCount(1);
    await expect(slider.locator('.hive-category-slider__title')).toHaveText('Esplora le categorie');
    await expect(slider.locator('.hive-category-slider__name')).toHaveText(['Sneakers']);
    await expect(slider.locator('.hive-category-slider__link')).toHaveAttribute('href', /sneakers/);
    await expect(slider.locator('img.hive-category-slider__image')).toHaveAttribute('src', /hive-dunk-0/);

    // No brands in this store: the marquee prints nothing.
    await expect(page.locator('.hive-brand-marquee')).toHaveCount(0);

    // Nothing featured: the newest product that can be bought, with the card's button.
    const spotlight = page.locator('.hive-product-spotlight');
    await expect(spotlight).toHaveCount(1);
    await expect(spotlight.locator('.hive-product-spotlight__title')).toHaveText('Calzini Hive');
    await expect(spotlight.locator('.hive-product-spotlight__price')).toContainText('15,00');
    await expect(spotlight.locator('[data-hive-add]')).toHaveText('Aggiungi al carrello');

    // No product chosen: the store's next drop, ticking.
    const countdown = page.locator('.hive-drop-countdown');
    await expect(countdown).toHaveCount(1);
    await expect(countdown.locator('.hive-drop-countdown__title')).toHaveText('Jordan 4 in arrivo');
    await expect(countdown.locator('[data-unit="d"]')).toHaveText(/^0?[67]$/);
    await expect(countdown.getByText('Ricordamelo')).toBeVisible();
    await expect(countdown.getByRole('link', { name: 'Acquista ora' })).toBeHidden();

    const calendar = page.locator('.hive-drop-calendar');
    await expect(calendar).toHaveCount(1);
    await expect(calendar.locator('.hive-drop-calendar__title')).toHaveText('Le prossime uscite');
    await expect(calendar.locator('.hive-drop-calendar__name')).toHaveText(['Jordan 4 in arrivo']);
    await expect(calendar.locator('.hive-drop-calendar__status')).toHaveText(/^Esce tra [67] giorni$/);

    // No paid orders: the toast is on the page, hidden.
    const toast = page.locator('.hive-social-proof');
    await expect(toast).toHaveCount(1);
    await expect(toast).toBeHidden();
    await expect(toast.locator('.hive-social-proof__title')).toHaveText('Qualcuno ha appena acquistato');
    expect(JSON.parse((await toast.getAttribute('data-hive-social-proof')) || '{}').src).toContain('social-proof');

    // The popup's offer waits in its template, taking no room.
    await expect(page.locator('.hive-promo-modal')).toHaveCount(1);
    await expect(page.locator('.hive-promo-modal')).toBeHidden();

    await page.screenshot({ path: `tests/e2e/artifacts/screenshots/${theme}-blocchi-c.png`, fullPage: true });
    expect(errors).toEqual([]);
});

test('the promo popup opens by itself after a few seconds, once per visitor', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.clock.install();
    await page.goto(url);
    await page.clock.runFor(6000);
    await expect(promo(page)).toBeVisible();
    await expect(promo(page).getByRole('heading', { name: '10% di sconto' })).toBeVisible();
    await expect(promo(page).locator('.hive-promo__code-value')).toHaveText('WELCOME10');
    await page.keyboard.press('Escape');
    await expect(promo(page)).toBeHidden();

    await page.reload();
    await page.clock.runFor(10000);
    await expect(promo(page)).toHaveCount(0);
    expect(errors).toEqual([]);
});

test("the spotlight's button puts the product in the cart", async ({ page }) => {
    const errors = hiveErrors(page);
    await promoSeen(page);
    await page.goto(url);
    const button = page.locator('.hive-product-spotlight [data-hive-add]');
    await button.click();
    await expect(button).toHaveText('✓ Aggiunto');
    const cart = await (await page.request.get('/?rest_route=/wc/store/v1/cart')).json();
    expect(cart.items.map((item) => [item.name, item.quantity])).toEqual([['Calzini Hive', 1]]);
    expect(errors).toEqual([]);
});

test('Slider categorie with its own tiles, Brand in scorrimento with logos by URL', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?pagename=blocchi-c-riquadri');
    const slider = page.locator('.hive-category-slider');
    await expect(slider.locator('.hive-category-slider__name')).toHaveText(['Asics', 'Nocta']);
    await expect(slider.locator('.hive-category-slider__link').first()).toHaveAttribute('href', /\/marchio\/asics$/);
    await expect(slider.locator('img.hive-category-slider__image').first()).toHaveAttribute('src', /sneaker\.svg$/);
    const marquee = page.locator('.hive-brand-marquee');
    await expect(marquee.locator('img.hive-brand-marquee__logo').first()).toHaveAttribute('src', /sneaker\.svg$/);
    await expect(marquee.locator('.hive-brand-marquee__name').first()).toHaveText('Jordan');
    expect(errors).toEqual([]);
});

