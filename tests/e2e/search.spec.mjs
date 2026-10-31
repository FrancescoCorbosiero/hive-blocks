// Step 8 on a real WooCommerce store: live search (its cacheable read and
// the dialog), the newsletter with size alerts, and Hive → Velocità's
// preloads and speculation rules.
import { expect, test } from '@playwright/test';
import { hiveErrors } from './helpers.mjs';

test('the search read: products for a query, cacheable', async ({ page }) => {
    const response = await page.request.get('/?rest_route=/hive/v1/search&q=dunk&v=1');
    expect(response.status()).toBe(200);
    expect(response.headers()['cache-control']).toContain('public');
    const data = await response.json();
    expect(data.items.map((item) => item.name).sort()).toEqual(['Nike Dunk Low Grey Fog', 'Nike Dunk Low Panda']);
    expect(data.url).toContain('s=dunk');
});

test('"Cerca" opens the live search; products appear as you type', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?pagename=cerca');
    await page.locator('[data-hive-search]').click();
    const dialog = page.locator('dialog[data-hive-dialog="search"]');
    await expect(dialog.getByRole('button', { name: 'Dunk' })).toBeVisible();
    await dialog.getByRole('searchbox').pressSequentially('calzini');
    await expect(dialog.locator('.hive-search__result')).toHaveText([/Calzini Hive/]);
    expect(errors).toEqual([]);
});

test('the newsletter: subscribed at once, size alerts waiting for the email’s confirmation', async ({ page }) => {
    const errors = hiveErrors(page);
    const email = `news-${Date.now()}@example.com`;
    await page.goto('/?pagename=cerca');
    const form = page.locator('[data-hive-newsletter]');
    await form.getByText('Avvisami quando arriva la mia taglia').click();
    await form.getByText('42', { exact: true }).click();
    await form.getByPlaceholder('La tua email').fill(email);
    await form.getByRole('button', { name: 'Iscriviti' }).click();
    await expect(form.locator('[data-hive-newsletter-status]')).toContainText('Iscrizione completata');
    await expect(form.locator('[data-hive-newsletter-status]')).toContainText('taglia 42');
    const person = await (await page.request.get(`/?rest_route=/hive-e2e/v1/subscriber&email=${encodeURIComponent(email)}`)).json();
    expect([person.newsletter, person.status, person.sizes_pending]).toEqual([true, 'pending', ['42']]);
    expect(errors).toEqual([]);
});

test('Hive → Velocità: the preload and the page to prerender, in <head>', async ({ page }) => {
    await page.goto('/?pagename=orari');
    await expect(page.locator('head link[rel="preload"][href*="hive-hero.webp"]')).toHaveAttribute('fetchpriority', 'high');
    const rules = await page.locator('head script[type="speculationrules"]').allTextContents();
    expect(rules.join('\n')).toContain('calzini-hive');
});

test('"Nella tua taglia": the size picked fills the rail; a size with nothing offers the alert', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?pagename=taglia');
    const section = page.locator('[data-hive-size-rail]');
    await section.getByRole('button', { name: '42', exact: true }).click();
    await expect(section.locator('.hive-card').filter({ hasText: 'Nike Dunk Low Panda' }).locator('.hive-card__match')).toHaveText(/^Taglia 42 · 180,00/);
    await section.getByRole('button', { name: 'Cambia taglia' }).click();
    await section.getByRole('button', { name: '41', exact: true }).click();
    await expect(section.getByText('Nessun paio disponibile in taglia 41, per ora.')).toBeVisible();
    await expect(section.getByRole('button', { name: 'Avvisami' })).toBeVisible();
    expect(errors).toEqual([]);
});
