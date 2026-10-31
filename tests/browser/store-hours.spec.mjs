// The Orari block's built CSS and script module in a real browser, on a page
// cached on a Sunday and visited later, with the clock set.
import { expect, test } from '@playwright/test';

const page_url = '/tests/browser/fixtures/store-hours.html';

async function visit(page, isoTime) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.clock.install({ time: new Date(isoTime) });
    await page.goto(page_url);
    return errors;
}

const block = (page) => page.locator('.hive-hours');

test('Monday 11:00 in Rome: open, and Monday is today', async ({ page }) => {
    const errors = await visit(page, '2026-10-19T09:00:00Z');
    const status = block(page).locator('[data-hive-hours-status]');

    await expect(status).toBeVisible();
    await expect(status).toHaveText('Aperto ora · chiude alle 13:00');
    await expect(status).toHaveClass(/is-open/);
    await expect(block(page).locator('.hive-hours__row.is-today')).toHaveCount(1);
    await expect(block(page).locator('.hive-hours__row.is-today')).toHaveAttribute('data-hive-day', '1');
    await expect(block(page).locator('[data-hive-today-badge]:visible')).toHaveCount(1);
    expect(errors).toEqual([]);
});

test('a holiday: closed, and when it opens again', async ({ page }) => {
    await visit(page, '2026-12-08T10:00:00Z');
    await expect(block(page).locator('[data-hive-hours-text]')).toHaveText('Chiuso per festività · apre domani alle 10:30');
});

test('Saturday evening: Sunday is closed, so Monday', async ({ page }) => {
    await visit(page, '2026-10-24T18:30:00Z');
    await expect(block(page).locator('[data-hive-hours-text]')).toHaveText('Chiuso · apre lunedì alle 10:30');
});

test("the block's own rules win over the theme's content rules", async ({ page }) => {
    await visit(page, '2026-10-19T09:00:00Z');
    const styles = await block(page).evaluate((root) => {
        const get = (selector, property) => getComputedStyle(root.querySelector(selector)).getPropertyValue(property);
        return {
            titleMarginTop: get('.hive-hours__title', 'margin-top'),
            titleAlign: get('.hive-hours__title', 'text-align'),
            listMarginLeft: get('.hive-hours__list', 'margin-left'),
            timeMarginLeft: get('.hive-hours__time', 'margin-left'),
            listBorder: get('.hive-hours__list', 'border-top-style'),
            badgeColor: get('.hive-hours__row.is-today .hive-hours__badge', 'background-color'),
        };
    });
    expect(styles).toEqual({
        titleMarginTop: '0px',
        titleAlign: 'center',
        listMarginLeft: '0px',
        timeMarginLeft: '0px',
        listBorder: 'solid',
        badgeColor: 'rgb(114, 17, 36)',
    });
});
