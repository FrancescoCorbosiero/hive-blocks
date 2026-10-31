// Pages load only what their blocks need, whichever way the theme prints them.
import { expect, test } from '@playwright/test';
import { OWN_HOURS_STYLES, blockTheme, hiveErrors, hoursStyles } from './helpers.mjs';

test('a page without Hive blocks loads nothing from Hive', async ({ page }) => {
    await page.goto('/?pagename=senza-hive');
    await expect(page.locator('main')).toContainText('Una pagina senza blocchi Hive.');

    const html = await page.content();
    expect(html).not.toMatch(/\/plugins\/hive-blocks\//);
    expect(html).not.toMatch(/id=["']hive-[a-z0-9-]+-(css|inline-css)["']/);
    expect(html).not.toContain('@hive/');
});

test('a block rendered after <head> brings its stylesheet, right before it', async ({ page }) => {
    test.skip(blockTheme, 'Block themes render the whole page before <head>.');
    const errors = hiveErrors(page);
    await page.goto('/?pagename=widget-hive');

    const block = page.locator('.widget .hive-hours');
    await expect(block).toHaveCount(1);
    await expect(block.locator('[data-hive-hours-text]')).toHaveText(/^(Aperto|Chiuso)/);

    const placement = await page.evaluate(() => {
        const link = document.getElementById('hive-store-hours-view-style-css');
        const widget = document.querySelector('.widget .hive-hours');
        return {
            inBody: Boolean(link && document.body.contains(link)),
            beforeBlock: Boolean(link && widget && link.compareDocumentPosition(widget) & Node.DOCUMENT_POSITION_FOLLOWING),
        };
    });
    expect(placement).toEqual({ inBody: true, beforeBlock: true });
    expect(await hoursStyles(block)).toEqual(OWN_HOURS_STYLES);
    expect(errors).toEqual([]);
});
