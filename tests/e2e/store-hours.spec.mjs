// The Orari block on a real WordPress + WooCommerce site, on the theme under test.
import { expect, test } from '@playwright/test';
import { OWN_HOURS_STYLES, hiveErrors, hoursStyles, rootVar, theme } from './helpers.mjs';

test('rendered by the server, made live by its script module, styled by its own CSS', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?pagename=orari');

    const block = page.locator('main .hive-hours');
    await expect(block).toHaveCount(1);
    await expect(block.locator('.hive-hours__row')).toHaveCount(7);
    await expect(block.locator('.hive-hours__row.is-today')).toHaveCount(1);
    await expect(block.locator('[data-hive-hours-status]')).toBeVisible();
    await expect(block.locator('[data-hive-hours-text]')).toHaveText(/^(Aperto|Chiuso)/);

    expect(await hoursStyles(block)).toEqual(OWN_HOURS_STYLES);
    expect(await rootVar(page, '--hive-accent')).toBe('#721124');

    await block.screenshot({ path: `tests/e2e/artifacts/screenshots/${theme}-orari.png` });
    expect(errors).toEqual([]);
});

test('its CSS is in <head>, so the block never paints unstyled', async ({ page }) => {
    await page.goto('/?pagename=orari');
    const inHead = await page.evaluate(() =>
        ['hive-base', 'hive-store-hours-view-style'].map((id) => Boolean(document.head.querySelector(`#${id}-css, #${id}-inline-css`)))
    );
    expect(inHead).toEqual([true, true]);
});
