// The store and trust blocks on a real WordPress + WooCommerce site, on the
// theme under test, with their default attributes (tests/e2e/pages/blocks-b.php).
import { expect, test } from '@playwright/test';
import { hiveErrors } from './helpers.mjs';

// Each block's root and a text it shows with the default attributes.
const BLOCKS = {
    '.hive-contact': 'Vieni a Trovarci',
    '.hive-visit': 'Vieni in negozio',
    '.hive-map-embed': 'Mostra la mappa',
    '.hive-badges': '100% Autentico',
    '.hive-authenticity': '100% Autenticità Garantita',
    '.hive-legit': 'Autentico o Falso?',
    '.hive-faq': 'Domande Frequenti',
    '.hive-trustpilot': 'Basato su 250 recensioni',
};

test('every block is rendered by the server, with its texts, and nothing fails', async ({ page }) => {
    const errors = hiveErrors(page);
    await page.goto('/?pagename=blocchi-b');

    for (const [selector, text] of Object.entries(BLOCKS)) {
        const block = page.locator(`main ${selector}`);
        await expect(block).toHaveCount(1);
        await expect(block).toBeVisible();
        await expect(block).toContainText(text);
    }

    // Vieni in negozio: the status worked out in the browser, WhatsApp on the number in Hive's settings.
    const visit = page.locator('main .hive-visit');
    await expect(visit.locator('[data-hive-visit-text]')).toHaveText(/^(Aperto|Chiuso)/);
    await expect(visit.locator('.hive-visit__row.is-today')).toHaveCount(1);
    await expect(visit.locator('a[href^="https://wa.me/393331234567"]')).toHaveCount(1);

    // The blocks' CSS is on the page: the FAQ's questions are rows, the map has the block's height.
    await expect(page.locator('main .hive-faq__question').first()).toHaveCSS('display', 'flex');
    await expect(page.locator('main .hive-map-embed .hive-map')).toHaveCSS('min-height', '450px');

    expect(errors).toEqual([]);
});

test('the FAQPage data is printed once, with the questions', async ({ page }) => {
    await page.goto('/?pagename=blocchi-b');
    const faqs = await page.evaluate(() =>
        [...document.querySelectorAll('script[type="application/ld+json"]')]
            .map((node) => {
                try {
                    return JSON.parse(node.textContent);
                } catch {
                    return null;
                }
            })
            .filter((data) => data?.['@type'] === 'FAQPage')
    );
    expect(faqs).toHaveLength(1);
    expect(faqs[0].mainEntity.map((question) => question.name)).toEqual([
        'I prodotti sono autentici?',
        'Quali sono i tempi di spedizione?',
        'Posso effettuare un reso?',
    ]);
});

test('the map loads only on the click, and the answers open', async ({ page }) => {
    const errors = hiveErrors(page);
    const google = [];
    page.on('request', (request) => {
        if (/^https:\/\/(www\.)?google\./.test(request.url())) google.push(request.url());
    });
    // Google's map answers with a stand-in page: the test doesn't depend on it.
    await page.route(/^https:\/\/(www\.)?google\.[a-z.]+\//, (route) => route.fulfill({ contentType: 'text/html', body: '<p>Mappa</p>' }));
    await page.goto('/?pagename=blocchi-b');

    const map = page.locator('main .hive-map-embed');
    await expect(map.locator('iframe')).toHaveCount(0);
    expect(google).toEqual([]);
    await map.getByRole('button', { name: 'Mostra la mappa' }).click();
    await expect(map.locator('iframe')).toHaveAttribute('src', /^https:\/\/www\.google\.com\/maps\/embed/);

    const question = page.locator('main .hive-faq__item').first();
    await question.locator('summary').click();
    await expect(question.locator('.hive-faq__answer')).toBeVisible();
    expect(errors).toEqual([]);
});
