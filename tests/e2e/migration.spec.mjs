// The switch-over (step 9): a store as golden-hive-blocks and CommerceKit
// left it (tests/e2e/mu/migration.php writes it), through Hive →
// Migrazione: the dry run changes nothing, the run brings everything over,
// a second run finds nothing left but what has to be done by hand.
import { expect, test } from '@playwright/test';
import { hiveErrors, login } from './helpers.mjs';

test.describe.configure({ mode: 'serial' });

const helper = (path) => `/?rest_route=/hive-e2e/v1/${path}`;

/** Hive → Migrazione: one of its two buttons, then the report's lines. */
async function migrate(page, button) {
    await page.goto('/wp-admin/admin.php?page=hive-migration');
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: button }).click();
    await expect(page.getByRole('heading', { name: button === 'Migra' ? 'Fatto: cosa è cambiato' : 'Prova: cosa cambierebbe' })).toBeVisible();
    return page.locator('.hive-migration-report li').allTextContents();
}

test('the dry run lists what would change and changes nothing', async ({ page }) => {
    const ids = await (await page.request.post(helper('legacy'))).json();
    await login(page);
    const lines = await migrate(page, 'Prova (nessuna modifica)');

    const all = lines.join('\n');
    expect(all).toContain(`Cambierebbe «Pagina golden-hive» (page, #${ids.page}): 1× golden-hive/store-hours → hive/store-hours, [bestsellers] → [hive_product_rail], [gh_filters] (tolto). Restano da fare a mano: golden-hive/old-thing`);
    expect(all).toContain('Porterebbe le impostazioni: shape');
    expect(all).toContain('Porterebbe le date di 1 drop');
    expect(all).toContain('Porterebbe 1 iscritti');
    expect(all).toMatch(/CommerceKit, lista d’attesa \(\w*commercekit_waitlist\): porterebbe 1 richieste di 1 persone \(saltate: 1 già avvisate; 1 su prodotti non più in negozio/);
    expect(all).toContain('Guide alle taglie (ckit_size_guide): porterebbe 1 come bozze');
    expect(all).toContain('CommerceKit, offerte al checkout: porterebbe 1 come bozze');

    const state = await (await page.request.get(helper('legacy'))).json();
    expect(state.content).toContain('<!-- wp:golden-hive/store-hours');
    expect(state.drop.ts).toBe(0);
    expect(state.person).toBe('ghb_subscriber');
    expect(state.guides).toEqual([]);
    expect(state.bumps).toEqual([]);
    expect(state.settings.shape).not.toBe('sharp');
});

test('the run brings pages, settings, drops, people and CommerceKit over', async ({ page }) => {
    await login(page);
    const lines = await migrate(page, 'Migra');
    expect(lines.join('\n')).toContain('Cambiato «Pagina golden-hive»');

    const state = await (await page.request.get(helper('legacy'))).json();
    // The page: Hive's blocks and shortcode, the filters shortcode gone, the unknown block left for a person.
    expect(state.content).toContain('<!-- wp:hive/store-hours {"title":"Orari di prima"} /-->');
    expect(state.content).toContain('[hive_product_rail type="best_selling" title="Best Sellers" limit="4" title="I più venduti"]');
    expect(state.content).not.toContain('gh_filters');
    expect(state.content).toContain('<!-- wp:golden-hive/old-thing /-->');
    // Settings: into what was at Hive's default; what the store chose in Hive stays.
    expect(state.settings).toEqual({ shape: 'sharp', whatsapp: '393331234567' });
    // The drop, scheduled.
    expect(state.drop.ts).toBeGreaterThan(Date.now() / 1000);
    expect(state.drop.lock).toBe('yes');
    expect(state.drop.scheduled).toBe(true);
    // CommerceKit's size guide and bump, as drafts.
    expect(state.guides).toEqual([{ title: 'Guida Nike', status: 'draft', from: `ckit_size_guide:${state.ids.guide}`, product: 0 }]);
    expect(state.bumps).toEqual([{ title: 'Aggiungi i calzini', status: 'draft', from: `commercekit:${state.ids.socks}`, product: state.ids.socks }]);

    // People: the newsletter subscriber with their confirmed alerts and token…
    expect(state.person).toBe('hive_subscriber');
    const person = await (await page.request.get(helper('subscriber&email=legacy@example.com'))).json();
    expect(person).toMatchObject({ status: 'on', token: 'LegacyToken123', newsletter: true });
    // …and CommerceKit's waitlist, active on the same product and size.
    const waiting = await (await page.request.get(helper('subscriber&email=wait@example.com'))).json();
    expect(waiting).toMatchObject({ status: 'on', wait: [`${state.ids.drop}|41`] });
    expect((await page.request.get(helper('subscriber&email=told@example.com'))).status()).toBe(404);
});

test('the page renders with Hive, and old email links answer through Hive', async ({ browser }) => {
    const page = await browser.newPage();
    const errors = hiveErrors(page);
    await page.goto('/?pagename=golden-hive');
    await expect(page.locator('.hive-hours__title')).toHaveText('Orari di prima');
    await expect(page.locator('.hive-rail').filter({ hasText: 'I più venduti' }).locator('.hive-card').first()).toBeVisible();
    await expect(page.locator('body')).not.toContainText('[gh_filters]');

    // A size-alert email golden-hive-blocks sent: its "stop" link.
    await page.goto('/wp-admin/admin-ajax.php?action=ghb_alerts&do=off&t=LegacyToken123');
    await expect(page).toHaveURL(/hive_alerts=off&t=LegacyToken123/);
    await expect(page.getByRole('heading', { name: 'Disattivare gli avvisi?' })).toBeVisible();
    expect(errors).toEqual([]);
    await page.close();
});

test('a second run has nothing left but what needs a person', async ({ page }) => {
    await login(page);
    const lines = await migrate(page, 'Migra');
    expect(lines).toEqual([expect.stringMatching(/^«Pagina golden-hive» \(page, #\d+\): restano da fare a mano: golden-hive\/old-thing$/)]);
});
