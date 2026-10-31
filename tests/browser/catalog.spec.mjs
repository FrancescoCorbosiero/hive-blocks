// The shop's pages script (@hive/catalog) in a real browser: two fixture
// pages with Hive\Shop\Catalog's markup, one of them "filtered".
import { expect, test } from '@playwright/test';

const start = '/tests/browser/fixtures/catalog.html';

async function open(page) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(start);
    // Set on this document: it survives an in-place swap, not a page load.
    await page.evaluate(() => (window.hiveSamePage = true));
    return errors;
}

const samePage = (page) => page.evaluate(() => window.hiveSamePage === true);
const cards = (page) => page.locator('[data-hive-catalog-results] .hive-card');

test('a filter swaps the results in place and goes in the address bar; back goes back', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const errors = await open(page);
    await expect(cards(page)).toHaveCount(3);

    await page.locator('.hive-facet--chips').getByRole('link', { name: '42' }).click();
    await expect(cards(page)).toHaveCount(1);
    await expect(page).toHaveURL(/catalog-42\.html$/);
    await expect(page).toHaveTitle('Taglia 42 — fixture');
    await expect(page.locator('.hive-filters__chip')).toHaveText(/Taglia: 42/);
    await expect(page.locator('.hive-catalog__count')).toHaveText('1 prodotto');
    expect(await samePage(page)).toBe(true);

    await page.goBack();
    await expect(cards(page)).toHaveCount(3);
    await expect(page).toHaveURL(/catalog\.html$/);
    expect(await samePage(page)).toBe(true);
    expect(errors).toEqual([]);
});

test('options nobody can buy are not links', async ({ page }) => {
    await open(page);
    await expect(page.locator('.hive-facet--chips .is-disabled')).toHaveText(/41/);
    await expect(page.locator('.hive-facet--chips').getByRole('link', { name: '41' })).toHaveCount(0);
});

test('phones: the filters open as a panel, Esc closes it and the focus goes back', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 });
    await open(page);
    const panel = page.locator('[data-hive-filters]');
    const toggle = page.locator('[data-hive-filters-open]');
    await expect(panel).not.toBeInViewport();

    await toggle.click();
    await expect(panel).toBeInViewport();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('.hive-catalog__backdrop')).toBeVisible();

    // A filter chosen in the open panel keeps it open.
    await panel.locator('.hive-facet--chips').getByRole('link', { name: '42' }).click();
    await expect(page.locator('[data-hive-filters-foot] .hive-filters__done')).toHaveText('Vedi 1 prodotto');
    await expect(panel).toBeInViewport();

    await page.keyboard.press('Escape');
    await expect(panel).not.toBeInViewport();
    await expect(page.locator('[data-hive-filters-open]')).toBeFocused();
});

test('price: handles and fields move together, and the handles never cross', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await open(page);
    const form = page.locator('[data-hive-price]');
    // Typing fires input; change (which applies the filter) only comes when the field is left.
    const type = (selector, value) =>
        form.locator(selector).evaluate((input, v) => {
            input.value = v;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }, value);

    await type('[data-hive-price-field="min"]', '100');
    await expect(form.locator('input[name="min_price"]')).toHaveValue('100');

    await type('[data-hive-price-field="max"]', '50');
    await expect(form.locator('input[name="max_price"]')).toHaveValue('50');
    await expect(form.locator('input[name="min_price"]')).toHaveValue('50');
    await expect(form.locator('[data-hive-price-field="min"]')).toHaveValue('50');
});

test('a long list can be searched', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await open(page);
    const facet = page.locator('.hive-facet--list');
    await facet.locator('[data-hive-facet-search]').fill('new bal');
    await expect(facet.locator('.hive-facet__options > li:not(.is-hidden)')).toHaveCount(2);
    await facet.locator('[data-hive-facet-search]').fill('');
    await expect(facet.locator('.hive-facet__options > li:not(.is-hidden)')).toHaveCount(12);
});

test('the sort order navigates to its URL', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.route('**/catalog.html?orderby=price', (route) => route.fulfill({ path: 'tests/browser/fixtures/catalog-42.html' }));
    await open(page);
    await page.locator('[data-hive-sort]').selectOption('price');
    await expect(page).toHaveURL(/catalog\.html\?orderby=price$/);
    await expect(cards(page)).toHaveCount(1);
    expect(await samePage(page)).toBe(true);
});
