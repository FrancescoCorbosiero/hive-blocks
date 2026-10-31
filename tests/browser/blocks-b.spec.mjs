// The store and trust blocks (Contatti, Vieni in negozio, Mappa, Badge di
// fiducia, Garanzia di autenticità, Legit check, Domande frequenti,
// Trustpilot) built, in a real browser: fixture pages with render.php's own
// output and a theme whose content rules (.entry-content h2, ul, dd, a,
// button, iframe, details…) would change the blocks if they won.
import { expect, test } from '@playwright/test';

const fixture = (name) => `/tests/browser/fixtures/${name}.html`;

/**
 * Opens a fixture, recording page errors and every request that leaves the
 * test server. Google's map answers with a stand-in page, so nothing goes out.
 */
async function open(page, name, { time } = {}) {
    const errors = [];
    const external = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('request', (request) => {
        if (!request.url().startsWith('http://127.0.0.1')) external.push(request.url());
    });
    await page.route(/^https:\/\/(www\.)?google\.[a-z.]+\//, (route) =>
        route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>Mappa</title><p>Mappa</p>' })
    );
    if (time) await page.clock.install({ time: new Date(time) });
    await page.goto(fixture(name));
    return { errors, external };
}

/** Computed styles of elements in a block: { key: [selector, property] } → { key: value }. */
function styles(block, wanted) {
    return block.evaluate((root, list) => {
        const out = {};
        for (const [key, [selector, property]] of Object.entries(list)) {
            const node = selector ? root.querySelector(selector) : root;
            out[key] = node ? getComputedStyle(node).getPropertyValue(property) : null;
        }
        return out;
    }, wanted);
}

const THEME_BLUE = 'rgb(6, 69, 173)';

test.describe('Mappa', () => {
    test('the map loads only when asked: nothing from Google before the click, then the iframe, focused', async ({ page }) => {
        const { errors, external } = await open(page, 'map-embed');
        const [first, second] = [page.locator('.hive-map-embed').nth(0), page.locator('.hive-map-embed').nth(1)];

        await expect(first.locator('[data-hive-map-load]')).toHaveText('Mostra la mappa');
        await expect(page.locator('iframe')).toHaveCount(0);
        // Nothing reached Google, even after the page settled.
        await page.waitForLoadState('networkidle');
        expect(external).toEqual([]);

        await first.locator('[data-hive-map-load]').click();
        const frame = first.locator('iframe.hive-map__frame');
        await expect(frame).toHaveCount(1);
        await expect(frame).toHaveAttribute('src', /^https:\/\/www\.google\.com\/maps\/embed\?pb=!1m18/);
        await expect(frame).toHaveAttribute('title', 'Mappa');
        await expect(frame).toBeFocused();
        await expect(first.locator('[data-hive-map-cover]')).toHaveCount(0);
        await expect(first.locator('.hive-map')).toHaveClass(/is-loaded/);
        await expect.poll(() => external.filter((url) => url.startsWith('https://www.google.com/maps/embed')).length).toBe(1);

        // The other map on the page waits for its own click.
        await expect(second.locator('[data-hive-map-load]')).toBeVisible();
        await expect(page.locator('iframe')).toHaveCount(1);
        expect(errors).toEqual([]);
    });

    test('"Apri in Google Maps": the address when there is one, else the map itself', async ({ page }) => {
        await open(page, 'map-embed');
        const links = page.locator('.hive-map__open');
        await expect(links.nth(0)).toHaveAttribute('href', /^https:\/\/www\.google\.com\/maps\/embed\?pb=/);
        await expect(links.nth(1)).toHaveAttribute('href', 'https://www.google.com/maps/search/?api=1&query=Stradone%20Farnese%203%20-%2029121%20-%20Piacenza%20%28PC%29');
        await expect(links.nth(1)).toHaveAttribute('target', '_blank');
        await expect(page.locator('.hive-map-embed').nth(1).locator('.hive-map__address')).toHaveText('Stradone Farnese 3 - 29121 - Piacenza (PC)');
    });

    test("the map's own rules win over the theme's, before and after loading; the block's height and corners", async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await open(page, 'map-embed');
        const [first, second] = [page.locator('.hive-map-embed').nth(0), page.locator('.hive-map-embed').nth(1)];

        expect(
            await styles(first, {
                height: ['.hive-map', 'height'],
                radius: ['.hive-map', 'border-top-left-radius'],
                button: ['.hive-map__load', 'background-color'],
                buttonCase: ['.hive-map__load', 'text-transform'],
                buttonRadius: ['.hive-map__load', 'border-top-left-radius'],
                noteMargin: ['.hive-map__note', 'margin-bottom'],
                openColor: ['.hive-map__open', 'color'],
            })
        ).toEqual({
            height: '450px',
            radius: '12px',
            button: 'rgb(10, 10, 10)',
            buttonCase: 'none',
            buttonRadius: '10px',
            noteMargin: '0px',
            openColor: 'rgb(10, 10, 10)',
        });
        expect(await styles(second, { height: ['.hive-map', 'height'], radius: ['.hive-map', 'border-top-left-radius'] })).toEqual({ height: '300px', radius: '0px' });

        await first.locator('[data-hive-map-load]').click();
        const fills = await first.evaluate((root) => {
            const box = root.querySelector('.hive-map').getBoundingClientRect();
            const frame = root.querySelector('iframe').getBoundingClientRect();
            return Math.abs(box.width - frame.width) < 1 && Math.abs(box.height - frame.height) < 1 && box.top === frame.top;
        });
        expect(fills).toBe(true);
        expect(
            await styles(first, {
                height: ['iframe', 'height'],
                border: ['iframe', 'border-top-style'],
                margin: ['iframe', 'margin-bottom'],
            })
        ).toEqual({ height: '450px', border: 'none', margin: '0px' });
    });
});

test.describe('Contatti', () => {
    test('phone, email and WhatsApp as links (the number from the settings when the block has none); the map on click', async ({ page }) => {
        const { errors, external } = await open(page, 'contact-info');
        const [plain, full] = [page.locator('.hive-contact').nth(0), page.locator('.hive-contact').nth(1)];

        // The default attributes: the address and the VAT number, nothing to click.
        await expect(plain.locator('.hive-contact__title')).toHaveText('Vieni a Trovarci');
        await expect(plain.locator('.hive-contact__item')).toHaveText(['Stradone Farnese 3 - 29121 - Piacenza (PC)', 'P.IVA: 01875650333']);
        await expect(plain.locator('a, .hive-map')).toHaveCount(0);

        await expect(full.locator('a[href="tel:+390523123456"]')).toHaveText('+39 0523 123456');
        await expect(full.locator('a[href="mailto:info@resellpiacenza.it"]')).toHaveText('info@resellpiacenza.it');
        const whatsapp = full.locator('a[href^="https://wa.me/"]');
        await expect(whatsapp).toHaveText('Scrivici su WhatsApp');
        await expect(whatsapp).toHaveAttribute('href', 'https://wa.me/393331234567?text=Ciao%21%20Vorrei%20qualche%20informazione.');
        await expect(whatsapp).toHaveAttribute('target', '_blank');

        await expect(full.locator('iframe')).toHaveCount(0);
        await full.locator('[data-hive-map-load]').click();
        await expect(full.locator('iframe')).toHaveAttribute('title', 'Mappa: Stradone Farnese 3 - 29121 - Piacenza (PC)');
        expect(external.every((url) => url.startsWith('https://www.google.com/maps/embed'))).toBe(true);
        expect(errors).toEqual([]);
    });

    test('details and map side by side on a wide screen, one under the other on a phone', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await open(page, 'contact-info');
        const full = page.locator('.hive-contact').nth(1);
        let details = await full.locator('.hive-contact__details').boundingBox();
        let map = await full.locator('.hive-contact__map').boundingBox();
        expect(map.x).toBeGreaterThanOrEqual(details.x + details.width);
        expect(map.height).toBeGreaterThanOrEqual(400);

        await page.setViewportSize({ width: 390, height: 844 });
        details = await full.locator('.hive-contact__details').boundingBox();
        map = await full.locator('.hive-contact__map').boundingBox();
        expect(map.y).toBeGreaterThanOrEqual(details.y + details.height);
    });

    test("the block's own rules win over the theme's content rules", async ({ page }) => {
        await open(page, 'contact-info');
        const block = page.locator('.hive-contact').nth(1);
        const own = await styles(block, {
            titleMargin: ['.hive-contact__title', 'margin-top'],
            titleAlign: ['.hive-contact__title', 'text-align'],
            addressStyle: ['.hive-contact__details', 'font-style'],
            listMargin: ['.hive-contact__list', 'margin-left'],
            listStyle: ['.hive-contact__list', 'list-style-type'],
            itemMargin: ['.hive-contact__item', 'margin-bottom'],
            linkLine: ['.hive-contact__link', 'text-decoration-line'],
            linkColor: ['.hive-contact__link', 'color'],
        });
        expect(own.linkColor).not.toBe(THEME_BLUE);
        delete own.linkColor;
        expect(own).toEqual({
            titleMargin: '0px',
            titleAlign: 'center',
            addressStyle: 'normal',
            listMargin: '0px',
            listStyle: 'none',
            itemMargin: '0px',
            linkLine: 'none',
        });
    });
});

test.describe('Vieni in negozio', () => {
    test('Monday 11:00 in Rome: open, and Monday is today (the page was cached on a Sunday)', async ({ page }) => {
        const { errors } = await open(page, 'store-visit', { time: '2026-10-19T09:00:00Z' });
        const block = page.locator('.hive-visit');
        const status = block.locator('[data-hive-visit-status]');

        await expect(status).toBeVisible();
        await expect(status).toHaveText('Aperto ora · chiude alle 13:00');
        await expect(status).toHaveClass(/is-open/);
        await expect(block.locator('.hive-visit__row.is-today')).toHaveCount(1);
        await expect(block.locator('.hive-visit__row.is-today')).toHaveAttribute('data-hive-day', '1');
        await expect(block.locator('[data-hive-today-badge]:visible')).toHaveCount(1);
        expect(errors).toEqual([]);
    });

    test('the status keeps right as the minutes go by: closing soon, then the lunch break', async ({ page }) => {
        await open(page, 'store-visit', { time: '2026-10-19T10:59:00Z' });
        const status = page.locator('[data-hive-visit-status]');
        await expect(status).toHaveText('Aperto · chiude tra 1 min');
        await expect(status).toHaveClass(/is-soon/);

        await page.clock.runFor(61_000);
        await expect(status).toHaveText('Chiuso ora · riapre alle 15:30');
        await expect(status).not.toHaveClass(/is-open|is-soon/);
    });

    test('a holiday: closed, and when it opens again', async ({ page }) => {
        await open(page, 'store-visit', { time: '2026-12-08T10:00:00Z' });
        await expect(page.locator('[data-hive-visit-text]')).toHaveText('Chiuso per festività · apre domani alle 10:30');
    });

    test('directions, WhatsApp on the number from the settings, the phone; the map on click', async ({ page }) => {
        const { errors, external } = await open(page, 'store-visit', { time: '2026-10-19T09:00:00Z' });
        const actions = page.locator('.hive-visit__action');
        await expect(actions).toHaveText(['Indicazioni', 'WhatsApp', '+39 0523 123456']);
        await expect(actions.nth(0)).toHaveAttribute('href', 'https://www.google.com/maps/dir/?api=1&destination=Stradone%20Farnese%203%20-%2029121%20-%20Piacenza%20%28PC%29');
        await expect(actions.nth(1)).toHaveAttribute('href', 'https://wa.me/393331234567?text=Ciao%21%20Vorrei%20passare%20in%20negozio.');
        await expect(actions.nth(2)).toHaveAttribute('href', 'tel:+390523123456');

        await page.waitForLoadState('networkidle');
        expect(external).toEqual([]);
        await page.locator('.hive-visit [data-hive-map-load]').click();
        await expect(page.locator('.hive-visit__map iframe')).toBeFocused();
        expect(errors).toEqual([]);
    });

    test("the block's own rules win over the theme's content rules", async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await open(page, 'store-visit', { time: '2026-10-19T09:00:00Z' });
        const block = page.locator('.hive-visit');
        const own = await styles(block, {
            titleMargin: ['.hive-visit__title', 'margin-top'],
            titleAlign: ['.hive-visit__title', 'text-align'],
            hoursMargin: ['.hive-visit__hours', 'margin-left'],
            timeMargin: ['.hive-visit__time', 'margin-left'],
            dayWeight: ['.hive-visit__row.is-today .hive-visit__day', 'font-weight'],
            actionLine: ['.hive-visit__action', 'text-decoration-line'],
            primary: ['.hive-visit__action.is-primary', 'background-color'],
            primaryText: ['.hive-visit__action.is-primary', 'color'],
            badge: ['.hive-visit__row.is-today .hive-visit__badge', 'background-color'],
        });
        expect(own).toEqual({
            titleMargin: '0px',
            titleAlign: 'start',
            hoursMargin: '0px',
            timeMargin: '0px',
            dayWeight: '700',
            actionLine: 'none',
            primary: 'rgb(10, 10, 10)',
            primaryText: 'rgb(255, 255, 255)',
            badge: 'rgb(114, 17, 36)',
        });
        // The map beside the information, as tall as the column.
        const info = await block.locator('.hive-visit__info').boundingBox();
        const map = await block.locator('.hive-map').boundingBox();
        expect(map.x).toBeGreaterThan(info.x + info.width);
        expect(map.height).toBeGreaterThanOrEqual(416);
    });
});

test.describe('Domande frequenti', () => {
    const items = (page, block) => page.locator('.hive-faq').nth(block).locator('.hive-faq__item');

    test('one answer open at a time: opening a question closes the open one', async ({ page }) => {
        const { errors } = await open(page, 'faq-schema');
        const faq = items(page, 0);
        await expect(faq).toHaveCount(3);
        await expect(page.locator('.hive-faq').nth(0).locator('.hive-faq__answer')).toHaveCount(3);
        for (const answer of await page.locator('.hive-faq').nth(0).locator('.hive-faq__answer').all()) {
            await expect(answer).toBeHidden();
        }

        await faq.nth(0).locator('summary').click();
        await expect(faq.nth(0)).toHaveJSProperty('open', true);
        await expect(faq.nth(0).locator('.hive-faq__answer')).toBeVisible();
        await expect(faq.nth(0).locator('.hive-faq__answer')).toContainText('Garantiamo il 100% di autenticità');

        await faq.nth(1).locator('summary').click();
        await expect(faq.nth(1)).toHaveJSProperty('open', true);
        await expect(faq.nth(0)).toHaveJSProperty('open', false);
        await expect(faq.nth(0).locator('.hive-faq__answer')).toBeHidden();

        await faq.nth(1).locator('summary').click();
        await expect(faq.nth(1)).toHaveJSProperty('open', false);
        expect(errors).toEqual([]);
    });

    test('from the keyboard: Tab to a question, Enter opens it, Space closes it', async ({ page }) => {
        await open(page, 'faq-schema');
        const first = items(page, 0).nth(0);
        await page.keyboard.press('Tab');
        await expect(first.locator('summary')).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(first).toHaveJSProperty('open', true);
        await page.keyboard.press(' ');
        await expect(first).toHaveJSProperty('open', false);
    });

    test('a block that allows several answers open keeps them open', async ({ page }) => {
        await open(page, 'faq-schema');
        const faq = items(page, 1);
        await faq.nth(0).locator('summary').click();
        await faq.nth(1).locator('summary').click();
        await expect(faq.nth(0)).toHaveJSProperty('open', true);
        await expect(faq.nth(1)).toHaveJSProperty('open', true);
        // Its answer's HTML is kept: the link works.
        await expect(faq.nth(0).locator('.hive-faq__answer a')).toHaveAttribute('href', '/spedizioni');
        // Opening in this block leaves the first block alone.
        await expect(items(page, 0).nth(0)).toHaveJSProperty('open', false);
    });

    test("the block's own rules win over the theme's content rules", async ({ page }) => {
        await open(page, 'faq-schema');
        const block = page.locator('.hive-faq').nth(0);
        await block.locator('summary').first().click();
        expect(
            await styles(block, {
                titleMargin: ['.hive-faq__title', 'margin-top'],
                titleAlign: ['.hive-faq__title', 'text-align'],
                itemBorder: ['.hive-faq__item', 'border-top-style'],
                itemPadding: ['.hive-faq__item', 'padding-top'],
                itemMargin: ['.hive-faq__item', 'margin-bottom'],
                summaryDisplay: ['.hive-faq__question', 'display'],
                summaryWeight: ['.hive-faq__question', 'font-weight'],
                answerMargin: ['.hive-faq__answer p', 'margin-bottom'],
            })
        ).toEqual({
            titleMargin: '0px',
            titleAlign: 'center',
            itemBorder: 'none',
            itemPadding: '0px',
            itemMargin: '0px',
            summaryDisplay: 'flex',
            summaryWeight: '600',
            answerMargin: '0px',
        });
    });
});

test.describe('Badge di fiducia', () => {
    test('the badges, light and dark; nothing moves by itself, nothing is doubled', async ({ page }) => {
        const { errors } = await open(page, 'trust-badges');
        const [light, dark] = [page.locator('.hive-badges').nth(0), page.locator('.hive-badges').nth(1)];
        const labels = ['100% Autentico', 'Spedizione Gratuita', 'Reso Facile', 'Pagamento Sicuro', 'Supporto 24/7'];
        await expect(light.locator('.hive-badges__item')).toHaveText(labels);
        await expect(dark.locator('.hive-badges__item')).toHaveText(labels);
        await expect(dark).toHaveClass(/hive-badges--dark/);
        await expect(page.locator('.hive-badges [aria-hidden="true"]:not(svg)')).toHaveCount(0);

        const motion = await dark.evaluate((root) =>
            [root, ...root.querySelectorAll('*')].map((node) => getComputedStyle(node).animationName).filter((name) => name !== 'none')
        );
        expect(motion).toEqual([]);
        expect(await styles(dark, { background: ['', 'background-color'], color: ['', 'color'] })).toEqual({ background: 'rgb(10, 10, 10)', color: 'rgb(255, 255, 255)' });
        expect(errors).toEqual([]);
    });

    test('on a phone the strip scrolls sideways; on a wide screen it is one centred row', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 800 });
        await open(page, 'trust-badges');
        const list = page.locator('.hive-badges__list').first();
        const narrow = await list.evaluate((node) => ({ overflow: getComputedStyle(node).overflowX, scrolls: node.scrollWidth > node.clientWidth }));
        expect(narrow).toEqual({ overflow: 'auto', scrolls: true });

        await page.setViewportSize({ width: 1280, height: 800 });
        const wide = await list.evaluate((node) => ({ overflow: getComputedStyle(node).overflowX, scrolls: node.scrollWidth > node.clientWidth, justify: getComputedStyle(node).justifyContent }));
        expect(wide).toEqual({ overflow: 'visible', scrolls: false, justify: 'center' });
    });

    test("the block's own rules win over the theme's content rules", async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await open(page, 'trust-badges');
        expect(
            await styles(page.locator('.hive-badges').first(), {
                // The list is centred (max-width, auto margins): the theme's 3em left margin would break that.
                listLeft: ['.hive-badges__list', 'margin-left'],
                listRight: ['.hive-badges__list', 'margin-right'],
                listBottom: ['.hive-badges__list', 'margin-bottom'],
                listStyle: ['.hive-badges__list', 'list-style-type'],
                itemMargin: ['.hive-badges__item', 'margin-bottom'],
                itemPadding: ['.hive-badges__item', 'padding-left'],
            })
        ).toEqual({ listLeft: '48px', listRight: '48px', listBottom: '0px', listStyle: 'none', itemMargin: '0px', itemPadding: '24px' });
    });
});

test.describe('Garanzia di autenticità', () => {
    test('the seal signed with the site name, the numbered steps, the button', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const { errors } = await open(page, 'authenticity-guarantee');
        const block = page.locator('.hive-authenticity');
        await expect(block.locator('.hive-authenticity__badge')).toHaveText('Autenticità certificata');
        await expect(block.locator('.hive-authenticity__signature')).toHaveText('ResellPiacenza');
        await expect(block.locator('.hive-authenticity__title')).toHaveText('100% Autenticità Garantita');
        await expect(block.locator('.hive-authenticity__number')).toHaveText(['1', '2', '3']);
        await expect(block.locator('.hive-authenticity__step-title')).toHaveText(['Ispezione Visiva', 'Verifica Materiali', 'Certificazione']);
        await expect(block.getByRole('link', { name: 'Scopri di Più' })).toHaveAttribute('href', '/autenticita');

        // The seal beside the promise on a wide screen.
        const seal = await block.locator('.hive-authenticity__seal').boundingBox();
        const content = await block.locator('.hive-authenticity__content').boundingBox();
        expect(content.x).toBeGreaterThan(seal.x + seal.width);
        expect(errors).toEqual([]);
    });

    test("the block's own rules win over the theme's content rules", async ({ page }) => {
        await open(page, 'authenticity-guarantee');
        expect(
            await styles(page.locator('.hive-authenticity'), {
                titleMargin: ['.hive-authenticity__title', 'margin-top'],
                stepsMargin: ['.hive-authenticity__steps', 'margin-left'],
                stepsStyle: ['.hive-authenticity__steps', 'list-style-type'],
                stepTitleMargin: ['.hive-authenticity__step-title', 'margin-top'],
                stepTitleSpacing: ['.hive-authenticity__step-title', 'letter-spacing'],
                stepTitleCase: ['.hive-authenticity__step-title', 'text-transform'],
                buttonLine: ['.hive-authenticity__button', 'text-decoration-line'],
                buttonColor: ['.hive-authenticity__button', 'color'],
            })
        ).toEqual({
            titleMargin: '0px',
            stepsMargin: '0px',
            stepsStyle: 'none',
            stepTitleMargin: '0px',
            stepTitleSpacing: 'normal',
            stepTitleCase: 'none',
            buttonLine: 'none',
            buttonColor: 'rgb(255, 255, 255)',
        });
    });
});

test.describe('Legit check', () => {
    test('each detail: the real one and the fake one, side by side', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const { errors } = await open(page, 'legit-check');
        const block = page.locator('.hive-legit');
        await expect(block.locator('.hive-legit__area')).toHaveText(['Cuciture', 'Etichetta Interna', 'Suola', 'Scatola']);
        const first = block.locator('.hive-legit__card').first();
        await expect(first.locator('dt')).toHaveText(['Autentico', 'Falso']);
        await expect(first.locator('dd')).toHaveText(['Cuciture uniformi e precise, spaziatura regolare', 'Cuciture irregolari, filo sporgente, spaziatura non uniforme']);
        const real = await first.locator('.hive-legit__side.is-real').boundingBox();
        const fake = await first.locator('.hive-legit__side.is-fake').boundingBox();
        expect(fake.x).toBeGreaterThan(real.x + real.width - 1);
        await expect(block.getByRole('link', { name: 'Scopri il Processo' })).toHaveAttribute('href', '/legit-check');
        expect(errors).toEqual([]);
    });

    test("the block's own rules win over the theme's content rules", async ({ page }) => {
        await open(page, 'legit-check');
        expect(
            await styles(page.locator('.hive-legit'), {
                titleMargin: ['.hive-legit__title', 'margin-top'],
                gridMargin: ['.hive-legit__grid', 'margin-left'],
                gridStyle: ['.hive-legit__grid', 'list-style-type'],
                areaCase: ['.hive-legit__area', 'text-transform'],
                areaMargin: ['.hive-legit__area', 'margin-top'],
                sidesMargin: ['.hive-legit__sides', 'margin-left'],
                textMargin: ['.hive-legit__text', 'margin-left'],
                labelCase: ['.hive-legit__label', 'text-transform'],
                labelWeight: ['.hive-legit__label', 'font-weight'],
            })
        ).toEqual({
            titleMargin: '0px',
            gridMargin: '0px',
            gridStyle: 'none',
            areaCase: 'uppercase',
            areaMargin: '0px',
            sidesMargin: '0px',
            textMargin: '0px',
            labelCase: 'uppercase',
            labelWeight: '700',
        });
    });
});

test.describe('Trustpilot', () => {
    test('the rating with its stars, Italian numbers, a link only when there is one; nothing from Trustpilot', async ({ page }) => {
        const { errors, external } = await open(page, 'trustpilot-widget');
        const [plain, linked] = [page.locator('.hive-trustpilot').nth(0), page.locator('.hive-trustpilot').nth(1)];
        const states = (block) => block.locator('.hive-trustpilot__star').evaluateAll((stars) => stars.map((star) => star.className.replace('hive-trustpilot__star ', '')));

        await expect(plain.locator('.hive-trustpilot__label')).toHaveText('Eccezionale');
        await expect(plain.getByRole('img', { name: '4,8 su 5' })).toBeVisible();
        expect(await states(plain)).toEqual(['is-full', 'is-full', 'is-full', 'is-full', 'is-half']);
        await expect(plain.locator('.hive-trustpilot__score')).toHaveText('4,8 / 5');
        await expect(plain.locator('.hive-trustpilot__count')).toHaveText('Basato su 250 recensioni');
        await expect(plain.locator('a')).toHaveCount(0);

        const link = linked.locator('a.hive-trustpilot__inner');
        await expect(link).toHaveAttribute('href', 'https://it.trustpilot.com/review/resellpiacenza.it');
        await expect(link).toHaveAttribute('target', '_blank');
        expect(await states(linked)).toEqual(['is-full', 'is-full', 'is-full', 'is-full', 'is-empty']);
        await expect(linked.locator('.hive-trustpilot__count')).toHaveText('Basato su 1.250 recensioni');

        await page.waitForLoadState('networkidle');
        expect(external).toEqual([]);
        expect(await page.locator('script[src], iframe').count()).toBe(0);
        expect(errors).toEqual([]);
    });

    test("the block's own rules win over the theme's content rules", async ({ page }) => {
        await open(page, 'trustpilot-widget');
        const own = await styles(page.locator('.hive-trustpilot').nth(1), {
            linkLine: ['.hive-trustpilot__inner', 'text-decoration-line'],
            linkColor: ['.hive-trustpilot__inner', 'color'],
            strong: ['.hive-trustpilot__count strong', 'color'],
            count: ['.hive-trustpilot__count', 'color'],
            star: ['.hive-trustpilot__star.is-full', 'background-color'],
        });
        expect(own.strong).toBe(own.count);
        expect(own).toMatchObject({ linkLine: 'none', linkColor: 'rgb(34, 34, 34)', star: 'rgb(0, 182, 122)' });
    });
});
