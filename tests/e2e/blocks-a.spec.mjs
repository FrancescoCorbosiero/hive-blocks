// Heroes and calls to action on a real WordPress + WooCommerce site, on the
// theme under test: rendered by the server from their defaults (and in their
// other layouts), styled by their own CSS, the carousel made live by its
// script module. Behaviour is covered in tests/browser/blocks-a.spec.mjs.
import { expect, test } from '@playwright/test';
import { hiveErrors, theme } from './helpers.mjs';

test('the heroes, the carousel and the calls to action render, with their texts', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?pagename=blocchi-a');

    const cover = page.locator('main .hive-hero--cover');
    await expect(cover).toBeVisible();
    await expect(cover.locator('.hive-hero__title')).toHaveText('Sneakers premium');
    await expect(cover.locator('.hive-hero__badge')).toHaveText('100% autentico');

    const split = page.locator('main .hive-hero--split');
    await expect(split).toBeVisible();
    await expect(split.locator('.hive-hero__title')).toHaveText('Sneakers premium');

    const about = page.locator('main .hive-hero--about');
    await expect(about).toBeVisible();
    await expect(about.locator('.hive-hero__value-title')).toHaveText('Passione');

    const carousel = page.locator('main .hive-hero-carousel');
    await expect(carousel).toBeVisible();
    await expect(carousel.locator('.hive-hero-carousel__slide')).toHaveCount(2);
    await expect(carousel.locator('.hive-hero-carousel__title').first()).toHaveText('Sneakers premium autentiche');
    // The module ran: it shows the controls, hidden in the server's markup.
    await expect(carousel.locator('[data-hive-carousel-controls]')).toBeVisible();
    await expect(carousel.locator('.hive-hero-carousel__dot')).toHaveCount(2);

    const banner = page.locator('main .hive-cta--banner');
    await expect(banner).toBeVisible();
    await expect(banner.locator('.hive-cta__title')).toHaveText('Spedizione gratuita su tutti gli ordini');
    await expect(page.locator('main .hive-cta--split .hive-cta__title')).toBeVisible();

    await page.screenshot({ path: `tests/e2e/artifacts/screenshots/${theme}-blocchi-a.png`, fullPage: true });
    expect(errors).toEqual([]);
});

test('their CSS is in <head>; the carousel brings its module, the heroes without a video none', async ({ page }) => {
    await page.goto('/?pagename=blocchi-a');
    const found = await page.evaluate(() => ({
        styles: ['hive-hero-view-style', 'hive-hero-carousel-view-style', 'hive-cta-view-style'].map((id) =>
            Boolean(document.head.querySelector(`#${id}-css, #${id}-inline-css`))
        ),
        carousel: Boolean(document.querySelector('script[type="module"][src*="/blocks/hero-carousel/build/view.js"]')),
        video: Boolean(document.querySelector('script[type="module"][src*="/blocks/hero/build/view.js"]')),
    }));
    expect(found).toEqual({ styles: [true, true, true], carousel: true, video: false });
});
