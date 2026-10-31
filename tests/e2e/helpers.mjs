// Shared by the e2e specs.

export const theme = process.env.HIVE_E2E_THEME || 'twentytwentyfive';

/** Block themes render the whole page before <head>; classic ones print <head> first. */
export const blockTheme = ['twentytwentyfive', 'twentytwentyfour', 'twentytwentythree'].includes(theme);

/** Collects errors from Hive's own scripts and files on the page (another plugin's are not ours to fail on). */
export function hiveErrors(page) {
    const errors = [];
    page.on('pageerror', (error) => {
        if (/hive-blocks|@hive\//.test(String(error.stack))) errors.push(error.message);
    });
    page.on('console', (message) => {
        if (message.type() === 'error' && /hive-blocks|@hive\//.test(`${message.text()} ${message.location()?.url ?? ''}`)) {
            errors.push(message.text());
        }
    });
    return errors;
}

export async function login(page) {
    await page.goto('/wp-login.php');
    await page.locator('#user_login').fill('admin');
    await page.locator('#user_pass').fill('password');
    await page.locator('#wp-submit').click();
    await page.waitForURL(/\/wp-admin\//);
}

/** A custom property as computed on <html>. */
export function rootVar(page, name) {
    return page.evaluate((property) => getComputedStyle(document.documentElement).getPropertyValue(property).trim(), name);
}

/**
 * Computed styles the Orari block's own CSS sets, which a theme's usual
 * content rules (h2, dl, dd margins…) would change if they won.
 */
export function hoursStyles(block) {
    return block.evaluate((root) => {
        const get = (selector, property) => getComputedStyle(root.querySelector(selector)).getPropertyValue(property);
        return {
            titleMarginTop: get('.hive-hours__title', 'margin-top'),
            titleAlign: get('.hive-hours__title', 'text-align'),
            listMarginLeft: get('.hive-hours__list', 'margin-left'),
            listPaddingLeft: get('.hive-hours__list', 'padding-left'),
            listBorder: get('.hive-hours__list', 'border-top-style'),
            timeMarginLeft: get('.hive-hours__time', 'margin-left'),
            timeNumbers: get('.hive-hours__time', 'font-variant-numeric'),
        };
    });
}

export const OWN_HOURS_STYLES = {
    titleMarginTop: '0px',
    titleAlign: 'center',
    listMarginLeft: '0px',
    listPaddingLeft: '0px',
    listBorder: 'solid',
    timeMarginLeft: '0px',
    timeNumbers: 'tabular-nums',
};
