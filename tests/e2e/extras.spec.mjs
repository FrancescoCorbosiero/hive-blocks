// The product page's second half (step 5) on a real WooCommerce store:
// drops, the waitlist with its email confirmation and the email when the
// size is back, social proof, WhatsApp, the delivery estimate, colorways
// and recently viewed. Products from tests/e2e/fixtures.php; the helpers
// under /hive-e2e/v1 (tests/e2e/mu-plugin.php) read what WordPress would
// have emailed and stand in for the scheduler.
import { expect, test } from '@playwright/test';
import { hiveErrors } from './helpers.mjs';

const dunk = '/?product=nike-dunk-low-panda';
const helper = (path) => `/?rest_route=/hive-e2e/v1/${path}`;

test('a drop not out yet: "In arrivo" on its card, a countdown on its page, nothing to buy', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?pagename=rail');
    const card = page.locator('.hive-card').filter({ hasText: 'Jordan 4 in arrivo' });
    await expect(card.locator('.hive-card__add')).toHaveText(/^In arrivo · \d+/);

    await page.goto('/?product=jordan-4-in-arrivo');
    const drop = page.locator('[data-hive-drop]');
    await expect(drop).toBeVisible();
    await expect(drop.locator('[data-unit="d"]')).toHaveText(/^0?[67]$/);
    await expect(page.locator('[data-hive-buy-add]')).toHaveCount(0);
    await expect(page.locator('[data-hive-sticky]')).toHaveCount(0);

    // "Ricordamelo": Google Calendar, or the .ics file.
    await drop.getByText('Ricordamelo').click();
    await expect(drop.getByRole('link', { name: 'Google Calendar' })).toHaveAttribute('href', /calendar\.google\.com/);
    const ics = await page.request.get(await drop.getByRole('link', { name: /\.ics/ }).getAttribute('href'));
    expect(ics.headers()['content-type']).toContain('text/calendar');
    expect(await ics.text()).toContain('SUMMARY:Drop: Jordan 4 in arrivo');

    // Not even a direct add to cart gets it.
    const id = await page.locator('[data-hive-product]').getAttribute('data-hive-product');
    await page.goto(`/?add-to-cart=${id}`);
    expect((await (await page.request.get('/?rest_route=/wc/store/v1/cart')).json()).items).toEqual([]);
    expect(errors).toEqual([]);
});

test('the waitlist: ask, confirm by email, hear when the size is back', async ({ page }) => {
    const errors = hiveErrors(page);
    const email = `attesa-${Date.now()}@example.com`;
    await page.goto(dunk);
    await page.locator('.hive-swatch[data-value="41"]').click();
    const wait = page.locator('[data-hive-wait]');
    await expect(wait.locator('[data-hive-wait-size]')).toHaveText('41');
    await wait.getByPlaceholder('La tua email').fill(email);
    await wait.getByRole('button', { name: 'Avvisami' }).click();
    await expect(wait.locator('[data-hive-wait-status]')).toContainText('conferma dalla tua email');

    let person = await (await page.request.get(helper(`subscriber&email=${encodeURIComponent(email)}`))).json();
    const product = await page.locator('[data-hive-product]').getAttribute('data-hive-product');
    expect(person.status).toBe('pending');
    expect(person.wait_pending).toEqual([`${product}|41`]);

    // The confirmation email's link: opening it only asks; the button confirms.
    const mails = (await (await page.request.get(helper('mails'))).json()).mails;
    const confirmation = mails.findLast((mail) => mail.to === email);
    expect(confirmation.subject).toBe('Conferma i tuoi avvisi');
    const link = /href="([^"]*hive_alerts=confirm[^"]*)"/.exec(confirmation.message)[1].replaceAll('&#038;', '&').replaceAll('&amp;', '&');
    await page.goto(link);
    await expect(page.getByRole('heading', { name: 'Conferma gli avvisi' })).toBeVisible();
    person = await (await page.request.get(helper(`subscriber&email=${encodeURIComponent(email)}`))).json();
    expect(person.status).toBe('pending');
    await page.getByRole('button', { name: 'Conferma gli avvisi' }).click();
    await expect(page.getByRole('heading', { name: 'Avvisi attivati' })).toBeVisible();
    person = await (await page.request.get(helper(`subscriber&email=${encodeURIComponent(email)}`))).json();
    expect([person.status, person.wait]).toEqual(['on', [`${product}|41`]]);

    // Size 41 is back: one email, and the wait is over.
    await page.request.post(helper('restock'), { form: { product, size: '41' } });
    try {
        const sent = (await (await page.request.post(helper('alerts-run'))).json()).mails.filter((mail) => mail.to === email);
        expect(sent).toHaveLength(1);
        expect(sent[0].subject).toBe('È di nuovo disponibile: Nike Dunk Low Panda');
        expect(sent[0].message).toContain('Taglia 41');
        person = await (await page.request.get(helper(`subscriber&email=${encodeURIComponent(email)}`))).json();
        expect(person.wait).toEqual([]);
    } finally {
        // The other specs count on 41 being sold out.
        await page.request.post(helper('restock'), { form: { product, size: '41', status: 'outofstock' } });
    }
    expect(errors).toEqual([]);
});

test('WhatsApp, the delivery estimate, the band, colorways', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto(dunk);
    await expect(page.getByRole('link', { name: 'Chiedi su WhatsApp' })).toHaveAttribute('href', /^https:\/\/wa\.me\/393331234567\?text=/);
    await expect(page.locator('[data-hive-delivery]')).toContainText('Consegna stimata');
    await expect(page.getByRole('heading', { name: 'Non trovi la tua taglia?' })).toBeVisible();
    await expect(page.locator('.hive-request__chip').first()).toHaveText('41');
    await expect(page.getByRole('heading', { name: 'Acquista con fiducia' })).toBeVisible();
    const colorways = page.locator('.hive-rail').filter({ hasText: 'Altre colorway' });
    await expect(colorways.locator('.hive-card')).toHaveText([/Nike Dunk Low Grey Fog/]);
    expect(errors).toEqual([]);
});

test('"Visti di recente": the products this browser opened', async ({ page }) => {
    await page.goto('/?product=calzini-hive');
    await page.goto(dunk);
    await page.locator('[data-hive-recent]').evaluate((slot) => slot.scrollIntoView());
    const recent = page.locator('[data-hive-recent] .hive-rail');
    await expect(recent.locator('.hive-rail__title')).toHaveText('Visti di recente');
    await expect(recent.locator('.hive-card')).toHaveText([/Calzini Hive/]);
});

test('social proof: a public, cacheable feed of paid orders', async ({ page }) => {
    const response = await page.request.get('/?rest_route=/hive/v1/social-proof');
    expect(response.status()).toBe(200);
    expect(response.headers()['cache-control']).toContain('public');
    expect(Array.isArray((await response.json()).items)).toBe(true);
});
