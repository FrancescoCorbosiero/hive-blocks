// The store's own pages (pages/*.html), pasted into WordPress as they will be
// on the store: every block is Hive's and renders, with the store's texts.
// Rails for categories this test store doesn't have print nothing.
import { expect, test } from '@playwright/test';
import { hiveErrors } from './helpers.mjs';

test('the homepage: carousel, brands, sections, FAQ, newsletter, the visit', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?pagename=negozio-home');
    await expect(page.locator('.hive-hero-carousel')).toHaveCount(1);
    await expect(page.locator('.hive-category-slider__name')).toHaveText(['Asics', 'Essentials', 'New Balance', 'Nocta', 'Samba & Gazelle', 'Travis Scott', '59FIFTY']);
    await expect(page.locator('.hive-category-slider__link').first()).toHaveAttribute('href', /\/marchio\/asics$/);
    await expect(page.locator('.hive-brand-marquee__title')).toHaveText('SHOP BY BRAND');
    await expect(page.locator('.hive-brand-marquee img.hive-brand-marquee__logo').first()).toHaveAttribute('src', /Logo_NIKE\.png$/);
    await expect(page.getByText('La tua taglia, appena arriva')).toBeVisible();
    await expect(page.getByText('Come posso essere sicuro che i prodotti siano autentici?')).toBeVisible();
    await expect(page.locator('.hive-visit')).toHaveCount(1);
    await expect(page.locator('[class*="golden-hive"], [class^="gh-"], [class*=" gh-"]')).toHaveCount(0);
    expect(errors).toEqual([]);
});

test('Chi siamo: the video hero, the story, the boutique, the promise', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?pagename=negozio-chi-siamo');
    await expect(page.locator('h1.hive-hero__title')).toContainText('BORN in');
    await expect(page.locator('.hive-hero__video')).toHaveCount(1);
    await expect(page.getByRole('heading', { name: 'La Nostra Storia' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'La Boutique' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'LIMITED EDITION SNEAKERS' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Resta in Contatto' })).toBeVisible();
    await expect(page.locator('#chi-siamo')).toHaveCount(1);
    expect(errors).toEqual([]);
});

test('Contatti: the hero writes to the store on WhatsApp; details, hours, map', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?pagename=negozio-contatti');
    await expect(page.locator('h1.hive-hero__title')).toHaveText('Contattaci');
    await expect(page.getByRole('link', { name: 'Scrivici su WhatsApp' })).toHaveAttribute('href', /wa\.me\/393517603869/);
    await expect(page.getByRole('heading', { name: 'Vieni a Trovarci' })).toBeVisible();
    await expect(page.locator('.hive-hours__title')).toHaveText('Orari di Apertura');
    await expect(page.locator('.hive-map-embed')).toBeVisible();
    expect(errors).toEqual([]);
});
