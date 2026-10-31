// The commerce and marketing blocks (batch C) in a real browser: the built
// CSS and script modules on fixture pages with each render.php's markup,
// the clock set where time matters, the network answered by the test.
import { expect, test } from '@playwright/test';

const fixture = (name) => `/tests/browser/fixtures/${name}.html`;

/** Collects the page's script errors. */
function watchErrors(page) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    return errors;
}

/** Computed values of [selector, property] pairs inside a root. */
function styles(locator, pairs) {
    return locator.evaluate(
        (root, list) =>
            Object.fromEntries(
                Object.entries(list).map(([name, [selector, property]]) => {
                    const node = selector ? root.querySelector(selector) : root;
                    return [name, node ? getComputedStyle(node).getPropertyValue(property) : 'missing'];
                })
            ),
        pairs
    );
}

/* Prodotto in evidenza ------------------------------------------------- */

test.describe('Prodotto in evidenza', () => {
    const product = {
        id: 101,
        type: 'variable',
        title: 'Nike Dunk Low Panda',
        url: '/prodotto/nike-dunk/',
        brand: 'Nike',
        categories: ['Sneakers'],
        sku: 'DD1391',
        price_html: '<span class="amount">150,00 €</span> – <span class="amount">180,00 €</span>',
        summary: '',
        images: [{ src: '/tests/browser/fixtures/sneaker.svg', srcset: '', thumb: '/tests/browser/fixtures/sneaker.svg', alt: '', width: 600, height: 600 }],
        attributes: [],
        in_stock: true,
        purchasable: true,
        state: { kind: 'variable', label: 'Aggiungi al carrello' },
        choices: [{ name: 'pa_taglia', label: 'Taglia', options: [{ value: '40', label: '40' }, { value: '42', label: '42' }] }],
        variations: [
            { id: 1011, attributes: { pa_taglia: '40' }, in_stock: true, price_html: '<span class="amount">150,00 €</span>', price_text: '150,00 €', max_qty: 2, image: '' },
            { id: 1013, attributes: { pa_taglia: '42' }, in_stock: true, price_html: '<span class="amount">180,00 €</span>', price_text: '180,00 €', max_qty: -1, image: '' },
        ],
    };

    async function open(page) {
        const errors = watchErrors(page);
        const posts = [];
        await page.route('**/api/product/101*', (route) => route.fulfill({ json: product }));
        await page.route('**/cart-endpoint', async (route) => {
            const fields = {};
            for (const [, name, value] of route.request().postDataBuffer().toString().matchAll(/name="([^"]+)"\r\n\r\n([^\r]*)/g)) fields[name] = value;
            posts.push(fields);
            await route.fulfill({ json: { fragments: { 'span.cart-count': `<span class="cart-count">${posts.length}</span>` }, cart_hash: 'abc' } });
        });
        await page.goto(fixture('product-spotlight'));
        return { errors, posts };
    }

    test("a simple product goes into the cart from the spotlight's button (@hive/cards)", async ({ page }) => {
        const { errors, posts } = await open(page);
        const button = page.locator('#simple .hive-product-spotlight__add');
        await expect(button).toHaveText('Aggiungi al carrello');
        await button.click();
        await expect(button).toHaveText('✓ Aggiunto');
        expect(posts).toEqual([{ product_id: '102', quantity: '1' }]);
        await expect(page.locator('header .cart-count')).toHaveText('1');
        expect(errors).toEqual([]);
    });

    test('a product with sizes opens Quick Add to choose one', async ({ page }) => {
        const { errors, posts } = await open(page);
        await page.locator('#variable').getByRole('button', { name: 'Scegli la tua taglia' }).click();
        const sheet = page.locator('dialog[data-hive-dialog="quick-add"]');
        await expect(sheet).toBeVisible();
        await sheet.getByRole('button', { name: '42' }).click();
        await sheet.locator('.hive-buy__add').click();
        await expect(sheet).toBeHidden({ timeout: 4000 });
        expect(posts).toEqual([{ product_id: '1013', quantity: '1' }]);
        expect(errors).toEqual([]);
    });

    test("the block's own rules win over the theme's content rules", async ({ page }) => {
        await open(page);
        expect(
            await styles(page.locator('#simple'), {
                titleMarginTop: ['.hive-product-spotlight__title', 'margin-top'],
                titleAlign: ['.hive-product-spotlight__title', 'text-align'],
                linkColor: ['.hive-product-spotlight__link', 'color'],
                linkLine: ['.hive-product-spotlight__link', 'text-decoration-line'],
                detailsMarginLeft: ['.hive-product-spotlight__details', 'margin-left'],
                valueMarginLeft: ['.hive-product-spotlight__value', 'margin-left'],
                imageRadius: ['.hive-product-spotlight__image', 'border-top-left-radius'],
                buttonBackground: ['.hive-product-spotlight__add', 'background-color'],
                saleBadge: ['.hive-product-spotlight__badge.is-sale', 'background-color'],
            })
        ).toEqual({
            titleMarginTop: '0px',
            titleAlign: 'start',
            linkColor: 'rgb(34, 34, 34)',
            linkLine: 'none',
            detailsMarginLeft: '0px',
            valueMarginLeft: '0px',
            imageRadius: '0px',
            buttonBackground: 'rgb(10, 10, 10)',
            saleBadge: 'rgb(114, 17, 36)',
        });
    });
});

/** Waits until a smooth scroll is over (the same position twice, 150 ms apart); its position. */
async function settled(track) {
    let last = -1;
    await expect
        .poll(
            async () => {
                const now = await track.evaluate((node) => node.scrollLeft);
                const still = now === last;
                last = now;
                return still;
            },
            { intervals: [150] }
        )
        .toBe(true);
    return last;
}

/* Slider categorie ------------------------------------------------------ */

test.describe('Slider categorie', () => {
    test('arrows: shown when there is more, a page at a time, off at the ends', async ({ page }) => {
        await page.setViewportSize({ width: 1200, height: 900 });
        const errors = watchErrors(page);
        await page.goto(fixture('category-slider'));
        const slider = page.locator('.hive-category-slider');
        const track = slider.locator('.hive-category-slider__track');
        const prev = slider.getByRole('button', { name: 'Categorie precedenti' });
        const next = slider.getByRole('button', { name: 'Categorie successive' });

        await expect(next).toBeVisible();
        await expect(prev).toBeDisabled();
        await next.click();
        expect(await settled(track)).toBeGreaterThan(300);
        await expect(prev).toBeEnabled();
        // A page at a time to the end, and back: each click once the scroll before it is over.
        for (let i = 0; i < 10 && (await next.isEnabled()); i++) {
            await next.click();
            await settled(track);
        }
        await expect(next).toBeDisabled();
        for (let i = 0; i < 10 && (await prev.isEnabled()); i++) {
            await prev.click();
            await settled(track);
        }
        await expect(prev).toBeDisabled();
        expect(await track.evaluate((node) => node.scrollLeft)).toBeLessThanOrEqual(1);
        expect(errors).toEqual([]);
    });

    test('no arrows when every category fits', async ({ page }) => {
        await page.setViewportSize({ width: 1200, height: 900 });
        // The server drew three categories only: they fit, nothing to scroll.
        await page.route('**/fixtures/category-slider.html', async (route) => {
            const response = await route.fetch();
            const html = await response.text();
            const start = html.indexOf('<li class="hive-category-slider__item">');
            const end = html.indexOf('</ul>');
            const items = html.slice(start, end).split(/(?=<li class="hive-category-slider__item">)/);
            await route.fulfill({ response, body: html.slice(0, start) + items.slice(0, 3).join('') + html.slice(end) });
        });
        await page.goto(fixture('category-slider'));
        await expect(page.locator('.hive-category-slider__item')).toHaveCount(3);
        await expect(page.locator('.hive-category-slider__nav')).toHaveCount(2);
        await expect(page.locator('.hive-category-slider__nav:visible')).toHaveCount(0);
    });

    test("the block's own rules win over the theme's list, link and image rules", async ({ page }) => {
        await page.setViewportSize({ width: 1200, height: 900 });
        await page.goto(fixture('category-slider'));
        const slider = page.locator('.hive-category-slider');
        await expect(slider.locator('.hive-category-slider__name')).toHaveText(['Sneakers', 'Streetwear', 'Accessori', 'Felpe', 'T-shirt', 'Cappelli', 'Borse', 'Limited Edition']);
        await expect(slider.locator('.hive-category-slider__initial')).toHaveText('C');
        expect(
            await styles(slider, {
                titleMarginTop: ['.hive-category-slider__title', 'margin-top'],
                titleSize: ['.hive-category-slider__title', 'font-size'],
                trackMarginLeft: ['.hive-category-slider__track', 'margin-left'],
                trackPaddingLeft: ['.hive-category-slider__track', 'padding-left'],
                itemListStyle: ['.hive-category-slider__item', 'list-style-type'],
                itemMarginBottom: ['.hive-category-slider__item', 'margin-bottom'],
                linkColor: ['.hive-category-slider__link', 'color'],
                linkLine: ['.hive-category-slider__link', 'text-decoration-line'],
                imageRadius: ['.hive-category-slider__image', 'border-top-left-radius'],
                imageFit: ['.hive-category-slider__image', 'object-fit'],
                snap: ['.hive-category-slider__track', 'scroll-snap-type'],
            })
        ).toEqual({
            titleMarginTop: '0px',
            titleSize: '24px',
            trackMarginLeft: '0px',
            trackPaddingLeft: '0px',
            itemListStyle: 'none',
            itemMarginBottom: '0px',
            linkColor: 'rgb(34, 34, 34)',
            linkLine: 'none',
            imageRadius: '0px',
            imageFit: 'cover',
            snap: 'x mandatory',
        });
    });
});

/* Brand in scorrimento -------------------------------------------------- */

test.describe('Brand in scorrimento', () => {
    test('the band slides by itself, stops under the mouse, and only the first copy reaches the keyboard', async ({ page }) => {
        await page.setViewportSize({ width: 1200, height: 900 });
        await page.goto(fixture('brand-marquee'));
        const block = page.locator('.hive-brand-marquee');
        const track = block.locator('.hive-brand-marquee__track').first();
        const animation = () => track.evaluate((node) => ({ name: getComputedStyle(node).animationName, duration: getComputedStyle(node).animationDuration, state: getComputedStyle(node).animationPlayState }));

        expect(await animation()).toEqual({ name: 'hive-brand-marquee', duration: '48s', state: 'running' });
        await expect.poll(() => track.evaluate((node) => new DOMMatrix(getComputedStyle(node).transform).m41)).toBeLessThan(0);

        await block.locator('.hive-brand-marquee__viewport').hover();
        expect((await animation()).state).toBe('paused');
        await page.mouse.move(5, 5);
        expect((await animation()).state).toBe('running');

        // Two of the three brands have a page; the copies are hidden from screen readers and the keyboard.
        await expect(block.getByRole('link')).toHaveCount(2);
        await expect(block.getByRole('link', { name: 'Nike' })).toHaveAttribute('href', '/marca/nike/');
        await expect(block.getByRole('img', { name: 'Jordan' })).toHaveCount(1);
        await expect(block.locator('a[href]:not([tabindex="-1"])')).toHaveCount(2);
    });

    test('with reduced motion the brands stand still, once', async ({ page }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto(fixture('brand-marquee'));
        const block = page.locator('.hive-brand-marquee');
        expect(await block.locator('.hive-brand-marquee__track').first().evaluate((node) => getComputedStyle(node).animationName)).toBe('none');
        await expect(block.locator('.hive-brand-marquee__item:visible')).toHaveCount(3);
        await expect(block.locator('.hive-brand-marquee__name:visible')).toHaveText('New Balance');
    });

    test("the block's own rules win over the theme's list, link and image rules", async ({ page }) => {
        await page.goto(fixture('brand-marquee'));
        expect(
            await styles(page.locator('.hive-brand-marquee'), {
                titleMarginTop: ['.hive-brand-marquee__title', 'margin-top'],
                titleAlign: ['.hive-brand-marquee__title', 'text-align'],
                trackMarginLeft: ['.hive-brand-marquee__track', 'margin-left'],
                itemListStyle: ['.hive-brand-marquee__item', 'list-style-type'],
                itemMarginBottom: ['.hive-brand-marquee__item', 'margin-bottom'],
                linkLine: ['.hive-brand-marquee__brand', 'text-decoration-line'],
                logoRadius: ['.hive-brand-marquee__logo', 'border-top-left-radius'],
                logoHeight: ['.hive-brand-marquee__logo', 'max-height'],
            })
        ).toEqual({
            titleMarginTop: '0px',
            titleAlign: 'center',
            trackMarginLeft: '0px',
            itemListStyle: 'none',
            itemMarginBottom: '0px',
            linkLine: 'none',
            logoRadius: '0px',
            logoHeight: '56px',
        });
    });
});

/* Conto alla rovescia del drop ----------------------------------------- */

test.describe('Conto alla rovescia del drop', () => {
    const cells = (block) => block.locator('[data-unit]');

    test('the time left on a fixed clock, ticking every second', async ({ page }) => {
        const errors = watchErrors(page);
        // The drop is on 2026-11-07 at 09:00 UTC: 3 days 2 h 3 min 10 s before, the clock held still.
        await page.clock.install({ time: new Date('2026-11-04T06:56:40Z') });
        await page.clock.pauseAt(new Date('2026-11-04T06:56:50Z'));
        await page.goto(fixture('drop-countdown'));
        const block = page.locator('#product');
        await expect(cells(block)).toHaveText(['03', '02', '03', '10']);
        await page.clock.runFor('00:04');
        await expect(cells(block)).toHaveText(['03', '02', '03', '06']);
        await expect(block.locator('[data-hive-drop-after]')).toBeHidden();
        expect(errors).toEqual([]);
    });

    test('at the release: "Disponibile ora" and "Acquista ora", without reloading the page', async ({ page }) => {
        await page.clock.install({ time: new Date('2026-11-07T08:59:40Z') });
        await page.clock.pauseAt(new Date('2026-11-07T08:59:50Z'));
        await page.goto(fixture('drop-countdown'));
        await page.evaluate(() => (window.hiveSamePage = true));
        const block = page.locator('#product');
        await expect(cells(block)).toHaveText(['00', '00', '00', '10']);
        await expect(block.getByText('Ricordamelo')).toBeVisible();

        await page.clock.runFor('00:11');
        await expect(block).toHaveClass(/is-released/);
        await expect(block.locator('.hive-drop-countdown__timer')).toBeHidden();
        await expect(block.getByText('Ricordamelo')).toBeHidden();
        await expect(block.getByText('Disponibile ora')).toBeVisible();
        await expect(block.getByRole('link', { name: 'Acquista ora' })).toHaveAttribute('href', '/prodotto/jordan-4/');
        await page.clock.runFor('00:10');
        expect(await page.evaluate(() => window.hiveSamePage)).toBe(true);
    });

    test('a date typed in the block, on a page cached before it came: already out', async ({ page }) => {
        await page.clock.install({ time: new Date('2026-11-04T12:00:00Z') });
        await page.goto(fixture('drop-countdown'));
        const block = page.locator('#typed');
        await expect(block).toHaveClass(/is-released/);
        await expect(block.locator('.hive-drop-countdown__timer')).toBeHidden();
        await expect(block.getByRole('link', { name: 'Scopri di più' })).toHaveClass(/hive-drop-countdown__buy/);
        // The product's drop, still ahead, is untouched.
        await expect(page.locator('#product')).not.toHaveClass(/is-released/);
    });

    test('"Ricordamelo": Google Calendar or the .ics file', async ({ page }) => {
        await page.clock.install({ time: new Date('2026-11-04T12:00:00Z') });
        await page.goto(fixture('drop-countdown'));
        const block = page.locator('#product');
        await block.getByText('Ricordamelo').click();
        await expect(block.getByRole('link', { name: 'Google Calendar' })).toHaveAttribute('href', /^https:\/\/calendar\.google\.com\/calendar\/render\?action=TEMPLATE&text=Drop%3A%20Jordan/);
        await expect(block.getByRole('link', { name: /\.ics/ })).toHaveAttribute('href', '/?hive_drop_ics=12&ts=1794042000');
    });

    test("the block's own rules win over the theme's content rules", async ({ page }) => {
        await page.clock.install({ time: new Date('2026-11-04T12:00:00Z') });
        await page.goto(fixture('drop-countdown'));
        expect(
            await styles(page.locator('#product'), {
                titleMarginTop: ['.hive-drop-countdown__title', 'margin-top'],
                titleAlign: ['.hive-drop-countdown__title', 'text-align'],
                linkLine: ['.hive-drop-countdown__link', 'text-decoration-line'],
                linkColor: ['.hive-drop-countdown__link', 'color'],
                valueWeight: ['.hive-drop-countdown__value', 'font-weight'],
                valueNumbers: ['.hive-drop-countdown__value', 'font-variant-numeric'],
                remindDisplay: ['.hive-remind__button', 'display'],
                imageRadius: ['.hive-drop-countdown__image', 'border-top-left-radius'],
                metaMarginBottom: ['.hive-drop-countdown__meta', 'margin-bottom'],
            })
        ).toEqual({
            titleMarginTop: '0px',
            titleAlign: 'start',
            linkLine: 'none',
            linkColor: 'rgb(34, 34, 34)',
            valueWeight: '800',
            valueNumbers: 'tabular-nums',
            remindDisplay: 'inline-flex',
            imageRadius: '0px',
            metaMarginBottom: '0px',
        });
    });
});

/* Calendario dei drop --------------------------------------------------- */

test.describe('Calendario dei drop', () => {
    test('each drop says when it comes, worked out in the browser, and switches at its release', async ({ page }) => {
        const errors = watchErrors(page);
        await page.clock.install({ time: new Date('2026-10-31T11:59:50Z') });
        await page.clock.pauseAt(new Date('2026-10-31T12:00:00Z'));
        await page.goto(fixture('drop-calendar'));
        const rows = page.locator('.hive-drop-calendar__item');
        await expect(rows.locator('.hive-drop-calendar__status')).toHaveText(['Disponibile ora', 'Esce tra 10 s', 'Esce tra 3 giorni']);

        const soon = rows.nth(1);
        await expect(soon.getByText('Ricordamelo')).toBeVisible();
        await expect(soon.getByRole('link', { name: 'Acquista ora' })).toBeHidden();
        await expect(rows.nth(0).getByRole('link', { name: 'Acquista ora' })).toBeVisible();

        await page.clock.runFor('00:11');
        await expect(soon).toHaveClass(/is-released/);
        await expect(soon.locator('.hive-drop-calendar__status')).toHaveText('Disponibile ora');
        await expect(soon.getByRole('link', { name: 'Acquista ora' })).toBeVisible();
        await expect(soon.getByText('Ricordamelo')).toBeHidden();
        await expect(rows.nth(2).locator('.hive-drop-calendar__status')).toHaveText('Esce tra 3 giorni');
        expect(errors).toEqual([]);
    });

    test("the block's own rules win over the theme's list and heading rules", async ({ page }) => {
        await page.setViewportSize({ width: 1200, height: 900 });
        await page.goto(fixture('drop-calendar'));
        expect(
            await styles(page.locator('.hive-drop-calendar'), {
                titleMarginTop: ['.hive-drop-calendar__title', 'margin-top'],
                titleAlign: ['.hive-drop-calendar__title', 'text-align'],
                listPaddingLeft: ['.hive-drop-calendar__list', 'padding-left'],
                listStyle: ['.hive-drop-calendar__list', 'list-style-type'],
                itemDisplay: ['.hive-drop-calendar__item', 'display'],
                itemMarginBottom: ['.hive-drop-calendar__item', 'margin-bottom'],
                nameMarginTop: ['.hive-drop-calendar__name', 'margin-top'],
                linkLine: ['.hive-drop-calendar__link', 'text-decoration-line'],
                imageRadius: ['.hive-drop-calendar__image', 'border-top-left-radius'],
                buyColor: ['.hive-drop-calendar__buy', 'color'],
            })
        ).toEqual({
            titleMarginTop: '0px',
            titleAlign: 'center',
            listPaddingLeft: '0px',
            listStyle: 'none',
            itemDisplay: 'grid',
            itemMarginBottom: '0px',
            nameMarginTop: '0px',
            linkLine: 'none',
            imageRadius: '0px',
            buyColor: 'rgb(255, 255, 255)',
        });
    });
});

/* Acquisti recenti (social proof) --------------------------------------- */

test.describe('Acquisti recenti', () => {
    // Noon, 31 October 2026 (UTC), in seconds.
    const NOON = Date.parse('2026-10-31T12:00:00Z') / 1000;
    const feed = {
        items: [
            { product: 'Nike Dunk Low Panda', image: '/tests/browser/fixtures/sneaker.svg', url: '/prodotto/nike-dunk/', city: 'Piacenza', ts: NOON - 2 * 3600 },
            { product: 'Calzini Hive', image: '', url: '/prodotto/calzini/', city: '', ts: NOON - 26 * 3600 },
            { product: 'Felpa Hive', image: '', url: '/prodotto/felpa/', city: 'Lodi', ts: NOON - 3 * 86400 },
        ],
    };

    /** The page on a held clock, the feed answered by the test; returns the feed requests. */
    async function open(page) {
        const errors = watchErrors(page);
        const asked = [];
        await page.route('**/api/social-proof*', (route) => {
            asked.push({ url: route.request().url(), cookies: route.request().headers().cookie ?? '' });
            route.fulfill({ json: feed });
        });
        await page.clock.install({ time: new Date('2026-10-31T11:59:58Z') });
        await page.clock.pauseAt(new Date('2026-10-31T12:00:00Z'));
        await page.goto(fixture('social-proof'));
        return { errors, asked };
    }

    const toast = (page) => page.locator('.hive-social-proof');

    test('a few purchases from the routed feed, "2 ore fa" worked out in the browser, then no more this session', async ({ page }) => {
        const { errors, asked } = await open(page);
        await expect(toast(page)).toBeHidden();
        expect(asked).toEqual([]);

        // The page has settled, then the block's 1 s delay: the feed is asked, the first purchase shows.
        await page.clock.runFor(1500);
        await expect(toast(page)).toBeVisible();
        expect(asked).toHaveLength(1);
        expect(new URL(asked[0].url).search).toBe('?days=14&limit=10&city=1');
        await expect(toast(page).locator('.hive-social-proof__product')).toHaveText('Nike Dunk Low Panda');
        await expect(toast(page).locator('.hive-social-proof__meta')).toHaveText('Piacenza · 2 ore fa');
        await expect(toast(page).locator('.hive-social-proof__link')).toHaveAttribute('href', '/prodotto/nike-dunk/');
        await expect(toast(page).locator('.hive-social-proof__image')).toHaveAttribute('src', '/tests/browser/fixtures/sneaker.svg');
        // In the bottom corner of the screen, though the theme's footer has a transform.
        const box = await toast(page).boundingBox();
        const viewport = page.viewportSize();
        expect(box.y + box.height).toBeGreaterThan(viewport.height - 40);
        expect(box.y + box.height).toBeLessThanOrEqual(viewport.height);
        expect(box.x).toBeLessThan(40);

        // Four seconds on screen, then away; the next one ten seconds after the first.
        await page.clock.runFor(4100);
        await expect(toast(page)).toBeHidden();
        await page.clock.runFor(6000);
        await expect(toast(page)).toBeVisible();
        await expect(toast(page).locator('.hive-social-proof__product')).toHaveText('Calzini Hive');
        await expect(toast(page).locator('.hive-social-proof__meta')).toHaveText('ieri');
        await expect(toast(page).locator('.hive-social-proof__media')).toBeHidden();

        // Two per session: the third never comes, here or on the next page.
        await page.clock.runFor(4100);
        await expect(toast(page)).toBeHidden();
        await page.clock.runFor(30000);
        await expect(toast(page)).toBeHidden();
        expect(await page.evaluate(() => JSON.parse(sessionStorage.getItem('hive_social_proof')).shown)).toBe(2);
        await page.reload();
        await page.clock.runFor(5000);
        await expect(toast(page)).toBeHidden();
        expect(asked).toHaveLength(1);
        expect(errors).toEqual([]);
    });

    test('the × closes it for the rest of the session', async ({ page }) => {
        const { asked } = await open(page);
        await page.clock.runFor(1500);
        await expect(toast(page)).toBeVisible();
        await toast(page).getByRole('button', { name: 'Chiudi' }).click();
        await expect(toast(page)).toBeHidden();
        await page.clock.runFor(30000);
        await expect(toast(page)).toBeHidden();
        await page.reload();
        await page.clock.runFor(5000);
        expect(asked).toHaveLength(1);
        await expect(toast(page)).toBeHidden();
    });

    test('it waits while a dialog is open, and stops once something goes into the cart', async ({ page }) => {
        await open(page);
        await page.evaluate(() => document.getElementById('other').showModal());
        await page.clock.runFor(1500);
        await page.clock.runFor(5000);
        await expect(toast(page)).toBeHidden();

        await page.evaluate(() => document.getElementById('other').close());
        await page.clock.runFor(5000);
        await expect(toast(page)).toBeVisible();
        await expect(toast(page).locator('.hive-social-proof__product')).toHaveText('Nike Dunk Low Panda');

        await page.evaluate(() => document.dispatchEvent(new CustomEvent('hive:added-to-cart', { detail: { id: 102, quantity: 1 } })));
        await expect(toast(page)).toBeHidden();
        await page.clock.runFor(30000);
        await expect(toast(page)).toBeHidden();
    });

    test("the toast's own rules win over the theme's", async ({ page }) => {
        await open(page);
        await page.clock.runFor(1500);
        await expect(toast(page)).toBeVisible();
        expect(
            await styles(toast(page), {
                position: ['', 'position'],
                background: ['', 'background-color'],
                productWeight: ['.hive-social-proof__product', 'font-weight'],
                linkLine: ['.hive-social-proof__link', 'text-decoration-line'],
                linkColor: ['.hive-social-proof__link', 'color'],
                closeBackground: ['.hive-social-proof__close', 'background-color'],
            })
        ).toEqual({
            position: 'fixed',
            background: 'rgb(255, 255, 255)',
            productWeight: '700',
            linkLine: 'none',
            linkColor: 'rgb(10, 10, 10)',
            closeBackground: 'rgba(0, 0, 0, 0)',
        });
    });
});

/* Popup promozionale ---------------------------------------------------- */

test.describe('Popup promozionale', () => {
    const promo = (page) => page.locator('dialog[data-hive-dialog="promo-promo-popup"]');

    test('it opens by itself after the delay, once per visitor; a link to #promo-popup opens it again', async ({ page, context }) => {
        await context.grantPermissions(['clipboard-read', 'clipboard-write']);
        const errors = watchErrors(page);
        await page.clock.install({ time: new Date('2026-10-31T11:59:58Z') });
        await page.clock.pauseAt(new Date('2026-10-31T12:00:00Z'));
        await page.goto(fixture('promo-modal'));

        await page.clock.runFor(2900);
        await expect(promo(page)).toHaveCount(0);
        await page.clock.runFor(200);
        await expect(promo(page)).toBeVisible();
        await expect(promo(page).getByRole('heading', { name: '10% di sconto' })).toBeVisible();
        await expect(promo(page)).toHaveAttribute('aria-label', '10% di sconto');
        await expect(page.locator('link[href="/assets/build/dialog.css"]')).toHaveCount(1);

        await promo(page).getByRole('button', { name: 'Copia' }).click();
        await expect(promo(page).getByRole('button', { name: 'Copiato' })).toBeVisible();
        expect(await page.evaluate(() => navigator.clipboard.readText())).toBe('WELCOME10');

        await page.keyboard.press('Escape');
        await expect(promo(page)).toBeHidden();
        expect(await page.evaluate(() => Boolean(localStorage.getItem('hive_promo_promo-popup')))).toBe(true);

        // The next page: not again.
        await page.reload();
        await page.clock.runFor(10000);
        await expect(promo(page)).toHaveCount(0);

        // A link to it opens it whenever.
        await page.getByRole('link', { name: 'Vedi l’offerta' }).click();
        await expect(promo(page)).toBeVisible();
        expect(new URL(page.url()).hash).toBe('');
        expect(errors).toEqual([]);
    });

    test('on exit intent: when the mouse leaves through the top of the page, not before the delay', async ({ page }) => {
        await page.clock.install({ time: new Date('2026-10-31T11:59:58Z') });
        await page.clock.pauseAt(new Date('2026-10-31T12:00:00Z'));
        await page.goto(`${fixture('promo-modal')}?exit`);
        const leave = () => page.evaluate(() => document.documentElement.dispatchEvent(new MouseEvent('mouseout', { bubbles: true, clientX: 300, clientY: -4 })));

        await leave();
        await page.clock.runFor(5000);
        await expect(promo(page)).toHaveCount(0);

        // Down and out through the bottom: not leaving.
        await page.evaluate(() => document.documentElement.dispatchEvent(new MouseEvent('mouseout', { bubbles: true, clientX: 300, clientY: 700 })));
        await page.clock.runFor(100);
        await expect(promo(page)).toHaveCount(0);

        await leave();
        await expect(promo(page)).toBeVisible();
    });

    test("the offer's own rules win over the theme's, inside the dialog", async ({ page }) => {
        await page.setViewportSize({ width: 1200, height: 900 });
        await page.clock.install({ time: new Date('2026-10-31T11:59:58Z') });
        await page.clock.pauseAt(new Date('2026-10-31T12:00:00Z'));
        await page.goto(fixture('promo-modal'));
        await page.clock.runFor(3100);
        await expect(promo(page)).toBeVisible();
        expect(
            await styles(promo(page).locator('.hive-promo'), {
                columns: ['', 'grid-template-columns'],
                titleMarginTop: ['.hive-promo__title', 'margin-top'],
                titleSize: ['.hive-promo__title', 'font-size'],
                codeBackground: ['.hive-promo__code-value', 'background-color'],
                codePadding: ['.hive-promo__code-value', 'padding-left'],
                copyBackground: ['.hive-promo__copy', 'background-color'],
                buttonColor: ['.hive-promo__button', 'color'],
                buttonLine: ['.hive-promo__button', 'text-decoration-line'],
                imageRadius: ['.hive-promo__image', 'border-top-left-radius'],
            })
        ).toEqual({
            columns: expect.stringMatching(/^\d+(\.\d+)?px \d+(\.\d+)?px$/),
            titleMarginTop: '0px',
            titleSize: '48px',
            codeBackground: 'rgba(0, 0, 0, 0)',
            codePadding: '0px',
            copyBackground: 'rgba(0, 0, 0, 0)',
            buttonColor: 'rgb(255, 255, 255)',
            buttonLine: 'none',
            imageRadius: '0px',
        });
        // On the page itself the block takes no room.
        await expect(page.locator('.hive-promo-modal')).toBeHidden();
    });
});
