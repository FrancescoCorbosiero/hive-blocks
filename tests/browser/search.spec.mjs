// Live search (@hive/search) and the newsletter block, on a fixture page.
import { expect, test } from '@playwright/test';

const page_ = '/tests/browser/fixtures/search.html';
const results = (q) => ({
    query: q,
    total: 8,
    url: `/?s=${q}&post_type=product`,
    items: [
        { id: 7, name: 'Nike Dunk Low Panda', brand: 'Nike', url: '/prodotto/dunk/', price_html: '<span>150,00 €</span>', image: '/tests/browser/fixtures/sneaker.svg' },
        { id: 8, name: 'Nike Dunk Low Grey Fog', brand: 'Nike', url: '/prodotto/fog/', price_html: '<span>170,00 €</span>', image: '/tests/browser/fixtures/sneaker-2.svg' },
    ],
});

test('typing shows products from the search read; Enter goes to the full results', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const asked = [];
    await page.route('**/api/search?**', (route) => {
        const url = new URL(route.request().url());
        asked.push(url.searchParams.get('q'));
        route.fulfill({ contentType: 'application/json', body: JSON.stringify(results(url.searchParams.get('q'))) });
    });
    await page.goto(page_);
    await page.getByRole('button', { name: /Cerca sneakers/ }).click();
    const dialog = page.locator('dialog[data-hive-dialog="search"]');
    const field = dialog.getByRole('searchbox', { name: 'Cerca prodotti' });
    await expect(field).toBeFocused();
    await expect(dialog.getByRole('button', { name: 'Jordan 4' })).toBeVisible();

    await field.pressSequentially('dunk');
    await expect(dialog.locator('.hive-search__result')).toHaveCount(2);
    await expect(dialog.locator('.hive-search__status')).toHaveText('8 prodotti per «dunk»');
    await expect(dialog.getByRole('link', { name: 'Vedi tutti i risultati (8)' })).toHaveAttribute('href', '/?s=dunk&post_type=product');
    // One request for the word, not one per letter.
    expect(asked).toEqual(['dunk']);

    await field.press('ArrowDown');
    await expect(dialog.locator('.hive-search__result').first()).toBeFocused();

    await page.route('**/?s=dunk&post_type=product', (route) => route.fulfill({ contentType: 'text/html', body: '<title>Risultati</title>' }));
    await field.focus();
    await field.press('Enter');
    await expect(page).toHaveURL(/\?post_type=product&s=dunk$/);
    expect(errors).toEqual([]);
});

test('a popular search is a tap away, and searches are remembered', async ({ page }) => {
    await page.route('**/api/search?**', (route) => route.fulfill({ contentType: 'application/json', body: JSON.stringify(results('jordan 4')) }));
    await page.goto(page_);
    await page.getByRole('button', { name: /Cerca sneakers/ }).click();
    const dialog = page.locator('dialog[data-hive-dialog="search"]');
    await dialog.getByRole('button', { name: 'Jordan 4' }).click();
    await expect(dialog.getByRole('searchbox')).toHaveValue('Jordan 4');
    await expect(dialog.locator('.hive-search__result')).toHaveCount(2);
    await page.route('**/prodotto/dunk/', (route) => route.fulfill({ contentType: 'text/html', body: '<title>Dunk</title>' }));
    await dialog.locator('.hive-search__result').first().click();
    await page.waitForURL(/prodotto\/dunk/);
    expect(await page.evaluate(() => JSON.parse(localStorage.getItem('hive_searches')))).toEqual(['Jordan 4']);
});

test('newsletter: email and up to three sizes, answered on the page', async ({ page }) => {
    let posted = '';
    await page.route('**/?wc-ajax=hive_newsletter', (route) => {
        posted = route.request().postData() || '';
        route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, message: 'Iscrizione completata! Grazie, a presto.' }) });
    });
    await page.goto(page_);
    const form = page.locator('[data-hive-newsletter]');
    await form.getByRole('button', { name: 'Iscriviti' }).click();
    await expect(form.locator('[data-hive-newsletter-status]')).toHaveText('Scrivi un indirizzo email valido.');

    await form.getByText('Avvisami quando arriva la mia taglia').click();
    for (const size of ['40', '41', '42', '43']) await form.getByText(size, { exact: true }).click();
    // A fourth size unticks the first.
    await expect(form.locator('input[value="40"]')).not.toBeChecked();
    await expect(form.locator('input[name="sizes[]"]:checked')).toHaveCount(3);

    await form.getByPlaceholder('La tua email').fill('mario@example.com');
    await form.getByRole('button', { name: 'Iscriviti' }).click();
    await expect(form.locator('[data-hive-newsletter-status]')).toHaveText('Iscrizione completata! Grazie, a presto.');
    expect(posted).toMatch(/name="email"\r\n\r\nmario@example.com/);
    expect(posted.match(/name="sizes\[\]"/g)).toHaveLength(3);
    // The newsletter's own field beats the page's rule for e-mail inputs.
    expect(await form.getByPlaceholder('La tua email').evaluate((input) => getComputedStyle(input).borderTopWidth)).toBe('1px');
});
