// The social and utility blocks on a real WordPress + WooCommerce site, on the
// theme under test (page from tests/e2e/pages/blocks-d.php): rendered by the
// server, the size guide opened by its script module.
import { expect, test } from '@playwright/test';
import { hiveErrors } from './helpers.mjs';

test('social, WhatsApp, Instagram, size guide and shortcode section', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?pagename=blocchi-d');

    const social = page.locator('.hive-social-buttons');
    await expect(social).toHaveCount(1);
    await expect(social.getByRole('link', { name: 'Seguici su Instagram' })).toHaveAttribute('href', 'https://www.instagram.com/resellpiacenza/');
    await expect(social.getByRole('link', { name: 'Seguici su TikTok' })).toHaveAttribute('href', 'https://www.tiktok.com/@resellpiacenza');

    // No number on the block: the store's, from Hive → Impostazioni.
    const whatsapp = page.locator('.hive-whatsapp-button');
    await expect(whatsapp).toHaveCount(1);
    await expect(whatsapp).toHaveAttribute('href', /^https:\/\/wa\.me\/393331234567\?text=Ciao/);
    await expect(whatsapp).toHaveText('Chatta con noi');

    const feed = page.locator('.hive-instagram-feed');
    await expect(feed).toHaveCount(1);
    await expect(feed.locator('.hive-instagram-feed__title')).toHaveText('Seguici su Instagram');
    await expect(feed.locator('.hive-instagram-feed__item')).toHaveCount(6);
    await expect(feed.locator('.hive-instagram-feed__button')).toHaveAttribute('href', 'https://www.instagram.com/resellpiacenza/');

    // No guide chosen: the table the old block always had, in the dialog.
    const chart = page.locator('.hive-size-chart-modal');
    await expect(chart).toHaveCount(1);
    const button = chart.getByRole('button', { name: 'Guida Taglie' });
    await button.click();
    const guide = page.locator('dialog[data-hive-dialog="size-guide"]');
    await expect(guide).toBeVisible();
    await expect(guide.locator('tbody tr')).toHaveCount(13);
    await page.keyboard.press('Escape');
    await expect(guide).toBeHidden();
    await expect(button).toBeFocused();

    // The section with its rail; the two the Store Hub switched off print nothing.
    const section = page.locator('.hive-shortcode-wrapper');
    await expect(section).toHaveCount(1);
    await expect(section.locator('.hive-shortcode-wrapper__title')).toHaveText('Novità');
    await expect(section.locator('.hive-card').first()).toBeVisible();
    await expect(section.locator('.hive-shortcode-wrapper__button')).toHaveText('Esplora il negozio');
    await expect(page.getByText('Sezione spenta')).toHaveCount(0);

    expect(errors).toEqual([]);
});
