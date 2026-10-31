// The social and utility blocks in a real browser: render.php's output in
// fixture pages (one per block), the built CSS and script modules, and a
// theme's content rules the blocks' own must beat.
//
//   Pulsante WhatsApp    hive/whatsapp-button
//   Pulsanti social      hive/social-buttons
//   Instagram            hive/instagram-feed
//   Guida alle taglie    hive/size-chart-modal
//   Sezione con shortcode hive/shortcode-wrapper
import { expect, test } from '@playwright/test';

async function visit(page, name) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(`/tests/browser/fixtures/${name}.html`);
    return errors;
}

/** Computed styles under a block: {label: [selector (null: the block itself), property]} → {label: value}. */
function styles(locator, wanted) {
    return locator.evaluate(
        (root, wanted) =>
            Object.fromEntries(
                Object.entries(wanted).map(([label, [selector, property]]) => [label, getComputedStyle(selector ? root.querySelector(selector) : root).getPropertyValue(property)])
            ),
        wanted
    );
}

const ACCENT = 'rgb(114, 17, 36)';
const WHITE = 'rgb(255, 255, 255)';
const INK = 'rgb(10, 10, 10)';

test.describe('Pulsante WhatsApp', () => {
    const button = (page) => page.locator('.hive-whatsapp-button').first();
    const opacity = (page) => button(page).evaluate((node) => Number(getComputedStyle(node).opacity));

    test('the chat with the store’s number, the message already written', async ({ page }) => {
        const errors = await visit(page, 'whatsapp-button');
        await expect(button(page)).toHaveAttribute('href', 'https://wa.me/393331234567?text=Ciao%21%20Vorrei%20avere%20informazioni%20su...');
        await expect(button(page)).toHaveAttribute('target', '_blank');
        await expect(button(page)).toHaveAttribute('rel', 'noopener noreferrer');
        await expect(button(page)).toHaveAccessibleName('Chatta con noi su WhatsApp');
        await expect(button(page)).toHaveText('Chatta con noi');

        // Its own number, written with + and spaces; no message; just the icon, the name still there for screen readers.
        const round = page.locator('.hive-whatsapp-button--icon');
        await expect(round).toHaveAttribute('href', 'https://wa.me/393517603869');
        await expect(round).toHaveAccessibleName('Chatta con noi su WhatsApp');
        expect(await round.locator('.hive-whatsapp-button__text').evaluate((node) => node.getBoundingClientRect().width)).toBeLessThanOrEqual(1);
        expect(errors).toEqual([]);
    });

    test('it comes in as the page scrolls, and when reached with the keyboard', async ({ page }) => {
        await visit(page, 'whatsapp-button');
        expect(await opacity(page)).toBe(0);
        expect(await styles(button(page), { events: [null, 'pointer-events'] })).toEqual({ events: 'none' });

        await page.mouse.wheel(0, 400);
        await expect.poll(() => opacity(page)).toBe(1);
        expect(await styles(button(page), { events: [null, 'pointer-events'] })).toEqual({ events: 'auto' });

        await page.evaluate(() => window.scrollTo(0, 0));
        await expect.poll(() => opacity(page)).toBe(0);
        await page.keyboard.press('Tab');
        await expect(button(page)).toBeFocused();
        await expect.poll(() => opacity(page)).toBe(1);
    });

    test('on a short page it’s there halfway down; on a page that doesn’t scroll, at once', async ({ page }) => {
        await visit(page, 'whatsapp-button');
        // 200px of page to scroll, give or take the theme's margins.
        const range = await page.evaluate(() => {
            const filler = document.querySelector('.page-filler');
            filler.style.height = '0px';
            filler.style.height = `${window.innerHeight + 200 - document.querySelector('main').getBoundingClientRect().height}px`;
            return document.documentElement.scrollHeight - window.innerHeight;
        });
        expect(range).toBeGreaterThan(100);
        expect(range).toBeLessThan(300);
        expect(await opacity(page)).toBe(0);
        await page.evaluate((y) => window.scrollTo(0, y), Math.ceil(range / 2));
        await expect.poll(() => opacity(page)).toBe(1);

        await page.evaluate(() => {
            window.scrollTo(0, 0);
            document.querySelector('.page-filler').style.height = '0px';
        });
        await expect.poll(() => opacity(page)).toBe(1);
    });

    test('fixed in the bottom corner, in the accent: the theme’s link rules don’t reach it', async ({ page }) => {
        await visit(page, 'whatsapp-button');
        await page.mouse.wheel(0, 600);
        await expect.poll(() => opacity(page)).toBe(1);

        const place = await button(page).evaluate((node) => {
            const box = node.getBoundingClientRect();
            return { right: Math.round(document.documentElement.clientWidth - box.right), bottom: Math.round(window.innerHeight - box.bottom) };
        });
        expect(place).toEqual({ right: 24, bottom: 24 });

        const own = {
            position: [null, 'position'],
            background: [null, 'background-color'],
            color: [null, 'color'],
            underline: [null, 'text-decoration-line'],
            paddingLeft: [null, 'padding-left'],
            icon: ['.hive-whatsapp-button__icon', 'fill'],
        };
        const expected = { position: 'fixed', background: ACCENT, color: WHITE, underline: 'none', paddingLeft: '20px', icon: WHITE };
        expect(await styles(button(page), own)).toEqual(expected);
        // The theme's a:hover (yellow, red text) doesn't win either.
        await button(page).hover();
        expect(await styles(button(page), own)).toEqual(expected);
    });
});

test.describe('Pulsanti social', () => {
    const bar = (page) => page.locator('.hive-social-buttons');

    test('a button for each profile with a link, each in a new tab', async ({ page }) => {
        const errors = await visit(page, 'social-buttons');
        await expect(bar(page).getByRole('link')).toHaveCount(3);
        await expect(page.getByRole('link', { name: 'Seguici su Instagram' })).toHaveAttribute('href', 'https://www.instagram.com/resellpiacenza/');
        await expect(page.getByRole('link', { name: 'Seguici su Facebook' })).toHaveAttribute('href', 'https://www.facebook.com/resellpiacenza');
        await expect(page.getByRole('link', { name: 'Seguici su TikTok' })).toHaveAttribute('href', 'https://www.tiktok.com/@resellpiacenza');
        for (const link of await bar(page).getByRole('link').all()) {
            await expect(link).toHaveAttribute('target', '_blank');
            await expect(link).toHaveAttribute('rel', 'noopener noreferrer');
        }
        expect(errors).toEqual([]);
    });

    test('a column fixed on the side, round buttons in their own colours: the theme’s list and link rules don’t reach them', async ({ page }) => {
        await visit(page, 'social-buttons');
        const place = await bar(page).evaluate((node) => {
            const box = node.getBoundingClientRect();
            return { right: Math.round(document.documentElement.clientWidth - box.right), middle: Math.abs(box.top + box.height / 2 - window.innerHeight / 2) < 1 };
        });
        expect(place).toEqual({ right: 24, middle: true });

        const own = {
            position: [null, 'position'],
            listStyle: [null, 'list-style-type'],
            marginLeft: [null, 'margin-left'],
            paddingLeft: [null, 'padding-left'],
            itemMargin: ['.hive-social-buttons__item', 'margin-bottom'],
            itemPadding: ['.hive-social-buttons__item', 'padding-top'],
            width: ['.hive-social-buttons__link', 'width'],
            background: ['.hive-social-buttons__link', 'background-color'],
            color: ['.hive-social-buttons__link', 'color'],
            underline: ['.hive-social-buttons__link', 'text-decoration-line'],
            radius: ['.hive-social-buttons__link', 'border-top-left-radius'],
        };
        expect(await styles(bar(page), own)).toEqual({
            position: 'fixed',
            listStyle: 'none',
            marginLeft: '0px',
            paddingLeft: '0px',
            itemMargin: '0px',
            itemPadding: '0px',
            width: '44px',
            background: WHITE,
            color: INK,
            underline: 'none',
            radius: '999px',
        });

        // Hovered: the accent, not the theme's yellow.
        const instagram = page.getByRole('link', { name: 'Seguici su Instagram' });
        await instagram.hover();
        await expect.poll(() => instagram.evaluate((node) => [getComputedStyle(node).backgroundColor, getComputedStyle(node).color].join(' / '))).toBe(`${ACCENT} / ${WHITE}`);
    });
});

test.describe('Instagram', () => {
    const feed = (page) => page.locator('.hive-instagram-feed');
    const frames = (page) => feed(page).locator('a.hive-instagram-feed__frame');
    const PROFILE = 'https://www.instagram.com/resellpiacenza/';

    // Quick View's product, as GET /wp-json/hive/v1/product/101 sends it.
    const product = {
        id: 101,
        type: 'variable',
        title: 'Nike Dunk Low Panda',
        url: '/prodotto/nike-dunk-low-panda/',
        brand: 'Nike',
        categories: ['Sneakers'],
        sku: 'DD1391',
        price_html: '<span class="amount">150,00 €</span> – <span class="amount">180,00 €</span>',
        summary: '<p>La Dunk più amata.</p>',
        images: [{ src: '/tests/browser/fixtures/sneaker.svg', srcset: '', thumb: '/tests/browser/fixtures/sneaker.svg', alt: 'Dunk di lato', width: 600, height: 600 }],
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

    test('the photos link to their posts, or to the profile, with no Instagram script', async ({ page }) => {
        const requests = [];
        page.on('request', (request) => requests.push(request.url()));
        const errors = await visit(page, 'instagram-feed');

        await expect(feed(page).locator('.hive-instagram-feed__title')).toHaveText('Seguici su Instagram');
        await expect(feed(page).locator('.hive-instagram-feed__item')).toHaveCount(4);
        // The first photo's post; an old photo with no post of its own and the empty cell: the profile.
        await expect(frames(page)).toHaveCount(3);
        await expect(frames(page).nth(0)).toHaveAttribute('href', 'https://www.instagram.com/p/dunk-panda/');
        await expect(frames(page).nth(1)).toHaveAttribute('href', PROFILE);
        await expect(frames(page).nth(2)).toHaveAttribute('href', PROFILE);
        await expect(frames(page).nth(2).locator('.hive-instagram-feed__placeholder')).toHaveCount(1);
        for (const link of await feed(page).locator('a[href*="instagram.com"]').all()) {
            await expect(link).toHaveAttribute('target', '_blank');
            await expect(link).toHaveAttribute('rel', 'noopener noreferrer');
        }
        await expect(frames(page).nth(0)).toHaveAccessibleName(/Dunk Panda in negozio/);
        for (const image of await feed(page).locator('img').all()) {
            await expect(image).toHaveAttribute('loading', 'lazy');
        }
        await expect(feed(page).getByRole('link', { name: '@resellpiacenza' })).toHaveAttribute('href', PROFILE);

        expect(requests.filter((url) => !url.startsWith('http://127.0.0.1'))).toEqual([]);
        expect(errors).toEqual([]);
    });

    test('“Acquista il look”: the tags sit where they were put, and open their product in Quick View', async ({ page }) => {
        await page.route('**/api/product/101*', (route) => route.fulfill({ json: product }));
        const errors = await visit(page, 'instagram-feed');
        const shop = feed(page).locator('.hive-instagram-feed__frame--shop');

        // Not one big link: the tags, and the post from the corner.
        await expect(feed(page).locator('.hive-instagram-feed__item').nth(2).locator('> a')).toHaveCount(0);
        await expect(shop.getByRole('link', { name: 'Vedi il post su Instagram' })).toHaveAttribute('href', 'https://www.instagram.com/p/look/');
        await expect(shop.locator('.hive-instagram-feed__spot')).toHaveCount(2);

        // Each tag's centre at its x and y, in % of the photo.
        const centres = await shop.evaluate((frame) => {
            const box = frame.getBoundingClientRect();
            return [...frame.querySelectorAll('.hive-instagram-feed__spot')].map((spot) => {
                const dot = spot.getBoundingClientRect();
                return [Math.round(((dot.left + dot.width / 2 - box.left) / box.width) * 100), Math.round(((dot.top + dot.height / 2 - box.top) / box.height) * 100)];
            });
        });
        expect(centres).toEqual([
            [30, 60],
            [80, 20],
        ]);

        // The product's name and price, shown on hover; a click opens it in Quick View.
        const tag = shop.getByRole('link', { name: /^Nike Dunk Low Panda, da 150,00/ });
        await expect(tag).toHaveAttribute('href', '/prodotto/nike-dunk-low-panda/');
        await tag.hover();
        await expect(tag.locator('.hive-instagram-feed__tag')).toHaveCSS('opacity', '1');
        await tag.click();
        const dialog = page.locator('dialog[data-hive-dialog="quick-view"]');
        await expect(dialog).toBeVisible();
        await expect(dialog.locator('.hive-qv__title')).toHaveText('Nike Dunk Low Panda');
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        await expect(tag).toBeFocused();
        expect(errors).toEqual([]);
    });

    test('as many columns as set; two on a phone', async ({ page }) => {
        await visit(page, 'instagram-feed');
        const columns = () => feed(page).locator('.hive-instagram-feed__grid').evaluate((grid) => getComputedStyle(grid).gridTemplateColumns.split(' ').length);
        expect(await columns()).toBe(4);
        await page.setViewportSize({ width: 390, height: 844 });
        expect(await columns()).toBe(2);
    });

    test('the block’s own rules win over the theme’s content rules', async ({ page }) => {
        await visit(page, 'instagram-feed');
        const own = {
            titleMarginTop: ['.hive-instagram-feed__title', 'margin-top'],
            titleAlign: ['.hive-instagram-feed__title', 'text-align'],
            gridMarginLeft: ['.hive-instagram-feed__grid', 'margin-left'],
            gridPaddingLeft: ['.hive-instagram-feed__grid', 'padding-left'],
            gridListStyle: ['.hive-instagram-feed__grid', 'list-style-type'],
            itemMargin: ['.hive-instagram-feed__item', 'margin-bottom'],
            imageBorder: ['.hive-instagram-feed__image', 'border-top-width'],
            imageRadius: ['.hive-instagram-feed__image', 'border-top-left-radius'],
            imageFit: ['.hive-instagram-feed__image', 'object-fit'],
            linkUnderline: ['.hive-instagram-feed__frame', 'text-decoration-line'],
            frameRadius: ['.hive-instagram-feed__frame', 'border-top-left-radius'],
            overlay: ['.hive-instagram-feed__overlay', 'opacity'],
        };
        expect(await styles(feed(page), own)).toEqual({
            titleMarginTop: '0px',
            titleAlign: 'center',
            gridMarginLeft: '0px',
            gridPaddingLeft: '0px',
            gridListStyle: 'none',
            itemMargin: '0px',
            imageBorder: '0px',
            imageRadius: '0px',
            imageFit: 'cover',
            linkUnderline: 'none',
            frameRadius: '10px',
            overlay: '0',
        });
        // Square photos, however tall the picture.
        const box = await feed(page).locator('.hive-instagram-feed__image').first().boundingBox();
        expect(Math.round(box.width)).toBe(Math.round(box.height));

        // A photo hovered: "Vedi su Instagram" over it, and no theme yellow behind; the button keeps its colours.
        await frames(page).nth(2).hover();
        await expect(frames(page).nth(2).locator('.hive-instagram-feed__overlay')).toHaveCSS('opacity', '1');
        await expect(frames(page).nth(2)).not.toHaveCSS('background-color', 'rgb(255, 255, 0)');
        const follow = feed(page).locator('.hive-instagram-feed__button');
        await follow.hover();
        await expect(follow).not.toHaveCSS('background-color', 'rgb(255, 255, 0)');
        await expect(follow).toHaveCSS('color', WHITE);
    });
});

test.describe('Guida alle taglie', () => {
    const guide = (page) => page.locator('dialog[data-hive-dialog="size-guide"]');

    test('the button opens the table in a dialog; Esc closes it and the focus goes back', async ({ page }) => {
        const errors = await visit(page, 'size-chart-modal');
        await expect(page.locator('dialog')).toHaveCount(0);
        const button = page.getByRole('button', { name: 'Guida Taglie' });
        await expect(button).toHaveAttribute('aria-haspopup', 'dialog');
        await button.click();

        await expect(guide(page)).toBeVisible();
        await expect(guide(page)).toHaveAttribute('aria-label', 'Guida Taglie');
        await expect(guide(page).getByRole('heading', { name: 'Guida Taglie' })).toBeVisible();
        await expect(guide(page).getByText('Trova la tua taglia perfetta')).toBeVisible();
        await expect(guide(page).locator('thead th')).toHaveText(['US', 'UK', 'EU', 'CM']);
        await expect(guide(page).locator('tbody tr')).toHaveCount(13);
        await expect(guide(page).locator('tbody tr').nth(5).locator('td')).toHaveText(['8.5', '7.5', '42', '26.5']);
        await expect(guide(page).getByText('Se sei tra due taglie')).toBeVisible();
        await expect(page.locator('link[href="/assets/build/dialog.css"]')).toHaveCount(1);

        await page.keyboard.press('Escape');
        await expect(guide(page)).toBeHidden();
        await expect(button).toBeFocused();
        expect(errors).toEqual([]);
    });

    test('a guide from Hive → Guide alle taglie, under its own title; the close button', async ({ page }) => {
        await visit(page, 'size-chart-modal');
        const button = page.getByRole('button', { name: 'Guida alle taglie Nike' });
        await button.click();
        await expect(guide(page).getByRole('heading', { name: 'Guida taglie Nike' })).toBeVisible();
        await expect(guide(page).locator('tbody tr')).toHaveCount(3);
        await expect(guide(page).getByText('Le Dunk calzano regolari.')).toBeVisible();
        await expect(guide(page).getByText('Trova la tua taglia perfetta')).toHaveCount(0);

        await guide(page).getByRole('button', { name: 'Chiudi' }).click();
        await expect(guide(page)).toBeHidden();
        await expect(button).toBeFocused();

        // Opened again, the other guide: one dialog, its content replaced.
        await page.getByRole('button', { name: 'Guida Taglie' }).click();
        await expect(guide(page).getByRole('heading', { name: 'Guida Taglie' })).toBeVisible();
        await expect(guide(page).locator('tbody tr')).toHaveCount(13);
        await expect(page.locator('dialog')).toHaveCount(1);
    });

    test('a link to the guide’s name opens it, with no button in the page', async ({ page }) => {
        await visit(page, 'size-chart-modal');
        await expect(page.locator('[data-hive-size-chart="guida-abbigliamento"]')).toBeHidden();
        const link = page.getByRole('link', { name: 'Apri la guida alle taglie' });
        await link.click();
        await expect(guide(page).getByRole('heading', { name: 'Taglie abbigliamento' })).toBeVisible();
        await expect(guide(page).locator('tbody tr')).toHaveCount(13);
        expect(new URL(page.url()).hash).toBe('');

        await page.keyboard.press('Escape');
        await expect(guide(page)).toBeHidden();
        await expect(link).toBeFocused();
    });

    test('the button keeps its own look over the theme’s button rules', async ({ page }) => {
        await visit(page, 'size-chart-modal');
        const own = {
            background: ['.hive-size-chart-modal__button', 'background-color'],
            color: ['.hive-size-chart-modal__button', 'color'],
            border: ['.hive-size-chart-modal__button', 'border-top-style'],
            radius: ['.hive-size-chart-modal__button', 'border-top-left-radius'],
            paddingLeft: ['.hive-size-chart-modal__button', 'padding-left'],
            font: ['.hive-size-chart-modal__button', 'font-family'],
            textCase: ['.hive-size-chart-modal__button', 'text-transform'],
            spacing: ['.hive-size-chart-modal__button', 'letter-spacing'],
        };
        const expected = {
            background: 'rgba(0, 0, 0, 0)',
            color: 'rgb(34, 34, 34)',
            border: 'solid',
            radius: '10px',
            paddingLeft: '20px',
            font: 'Georgia, serif',
            textCase: 'none',
            spacing: 'normal',
        };
        const chart = page.locator('.hive-size-chart-modal').first();
        expect(await styles(chart, own)).toEqual(expected);
        await chart.locator('.hive-size-chart-modal__button').hover();
        await expect(chart.locator('.hive-size-chart-modal__button')).not.toHaveCSS('background-color', 'rgb(255, 0, 0)');
    });
});

test.describe('Sezione con shortcode', () => {
    const sections = (page) => page.locator('.hive-shortcode-wrapper');

    test('eyebrow, title, what the shortcode printed, the button', async ({ page }) => {
        const errors = await visit(page, 'shortcode-wrapper');
        await expect(sections(page)).toHaveCount(3);
        const first = sections(page).first();
        await expect(first.locator('.hive-shortcode-wrapper__eyebrow')).toHaveText('Selezione Esclusiva');
        await expect(first.getByRole('heading', { level: 2 })).toHaveText('Prodotti in tendenza');
        await expect(first.locator('.hive-shortcode-wrapper__content .fixture-shortcode')).toBeVisible();
        await expect(first.getByRole('link', { name: 'Esplora le tendenze' })).toHaveAttribute('href', '/featured-sneakers');
        // A title only: no eyebrow, no button.
        await expect(sections(page).nth(2).locator('.hive-shortcode-wrapper__eyebrow, .hive-shortcode-wrapper__cta')).toHaveCount(0);
        expect(errors).toEqual([]);
    });

    test('the bands: a light one mixed from the text colour, a dark one with a light button', async ({ page }) => {
        await visit(page, 'shortcode-wrapper');
        const background = (section) => section.evaluate((node) => getComputedStyle(node).backgroundColor);
        // 5% of the text colour (#222) over the page.
        expect(await background(sections(page).nth(0))).toMatch(/^(color\(srgb 0\.13\d* 0\.13\d* 0\.13\d* \/ 0\.05\)|rgba\(34, 34, 34, 0\.05\))$/);
        expect(await background(sections(page).nth(2))).toBe('rgba(0, 0, 0, 0)');

        const dark = sections(page).nth(1);
        expect(
            await styles(dark, {
                background: [null, 'background-color'],
                color: [null, 'color'],
                title: ['.hive-shortcode-wrapper__title', 'color'],
                buttonBackground: ['.hive-shortcode-wrapper__button', 'background-color'],
                buttonColor: ['.hive-shortcode-wrapper__button', 'color'],
            })
        ).toEqual({ background: INK, color: WHITE, title: WHITE, buttonBackground: WHITE, buttonColor: INK });
    });

    test('the heading and the button keep their own look over the theme’s rules', async ({ page }) => {
        await visit(page, 'shortcode-wrapper');
        const first = sections(page).first();
        expect(
            await styles(first, {
                titleMargin: ['.hive-shortcode-wrapper__title', 'margin-top'],
                titleAlign: ['.hive-shortcode-wrapper__title', 'text-align'],
                titleCase: ['.hive-shortcode-wrapper__title', 'text-transform'],
                titleColor: ['.hive-shortcode-wrapper__title', 'color'],
                eyebrowMargin: ['.hive-shortcode-wrapper__eyebrow', 'margin-bottom'],
                eyebrowCase: ['.hive-shortcode-wrapper__eyebrow', 'text-transform'],
                buttonUnderline: ['.hive-shortcode-wrapper__button', 'text-decoration-line'],
                buttonBackground: ['.hive-shortcode-wrapper__button', 'background-color'],
                buttonColor: ['.hive-shortcode-wrapper__button', 'color'],
            })
        ).toEqual({
            titleMargin: '0px',
            titleAlign: 'center',
            titleCase: 'uppercase',
            titleColor: 'rgb(34, 34, 34)',
            eyebrowMargin: '8px',
            eyebrowCase: 'uppercase',
            buttonUnderline: 'none',
            buttonBackground: INK,
            buttonColor: WHITE,
        });
        const button = first.getByRole('link', { name: 'Esplora le tendenze' });
        await button.hover();
        await expect(button).not.toHaveCSS('background-color', 'rgb(255, 255, 0)');
        await expect(button).toHaveCSS('color', WHITE);
    });
});
