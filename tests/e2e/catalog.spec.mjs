// The shop's pages drawn by Hive (module "Pagine del negozio"), on a real
// WooCommerce store, classic and block themes alike. Products from
// tests/e2e/fixtures.php, all in "Sneakers": a pair in sizes 40, 41 (sold
// out) and 42; a simple product; a sold-out one.
import { expect, test } from '@playwright/test';
import { hiveErrors } from './helpers.mjs';

const results = (page) => page.locator('[data-hive-catalog-results] .hive-card');

test('the shop is Hive’s page inside the theme’s header and footer; sold out comes last', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?post_type=product');
    await expect(page.locator('header').first()).toBeVisible();
    await expect(page.locator('.hive-catalog h1.hive-catalog__title')).toBeVisible();
    await expect(results(page)).toHaveCount(3);
    await expect(results(page).last()).toContainText('Felpa esaurita');
    await expect(page.locator('ul.products')).toHaveCount(0);
    expect(errors).toEqual([]);
});

test('a size keeps only the products with it in stock, swapped in place', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const errors = hiveErrors(page);
    await page.goto('/?post_type=product');
    await page.evaluate(() => (window.hiveSamePage = true));

    const sizes = page.locator('.hive-facet--chips');
    // 41 is sold out everywhere: not offered at all.
    await expect(sizes.getByRole('link', { name: '40', exact: true })).toBeVisible();
    await expect(sizes.getByText('41', { exact: true })).toHaveCount(0);
    await sizes.getByRole('link', { name: '42', exact: true }).click();

    await expect(page).toHaveURL(/filter_taglia=42/);
    await expect(results(page)).toHaveCount(1);
    await expect(results(page).first()).toContainText('Nike Dunk Low Panda');
    await expect(results(page).first().locator('.hive-card__match')).toHaveText(/^Taglia 42 · 180,00/);
    // Its page opens with the size picked.
    await expect(results(page).first().locator('.hive-card__link')).toHaveAttribute('href', /attribute_pa_taglia=42/);
    await expect(page.locator('.hive-filters__chip')).toContainText('42');
    expect(await page.evaluate(() => window.hiveSamePage)).toBe(true);

    // The filtered URL is a plain page too.
    await page.reload();
    await expect(results(page)).toHaveCount(1);
    expect(errors).toEqual([]);
});

test('a category page has its own title; the sort order keeps sold out last', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/?product_cat=sneakers');
    await expect(page.locator('.hive-catalog__title')).toHaveText('Sneakers');
    await expect(results(page)).toHaveCount(3);

    await page.locator('[data-hive-sort]').selectOption('price-desc');
    await expect(page).toHaveURL(/orderby=price-desc/);
    // The collection's own URL (pretty or plain permalinks), with the order.
    await expect(page).toHaveURL(/product-category\/sneakers|product_cat=sneakers/);
    await expect(results(page).first()).toContainText('Nike Dunk Low Panda');
    await expect(results(page).last()).toContainText('Felpa esaurita');
});

test('only in stock', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/?post_type=product');
    await page.getByRole('link', { name: 'Solo disponibili' }).click();
    await expect(page).toHaveURL(/instock=1/);
    await expect(results(page)).toHaveCount(2);
    await expect(results(page).filter({ hasText: 'Felpa esaurita' })).toHaveCount(0);
});
