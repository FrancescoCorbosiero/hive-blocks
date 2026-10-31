// Heroes and calls to action (hive/hero, hive/hero-carousel, hive/cta): the
// built CSS and script modules in a real browser, in fixture pages with
// render.php's markup, under a theme that styles content elements.
import { expect, test } from '@playwright/test';
import { fileURLToPath } from 'node:url';

const HERO = '/tests/browser/fixtures/hero.html';
const CAROUSEL = '/tests/browser/fixtures/hero-carousel.html';
const CTA = '/tests/browser/fixtures/cta.html';
const VIDEO = fileURLToPath(new URL('./fixtures/hero.webm', import.meta.url));

async function open(page, url, options) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(url, options);
    return errors;
}

/** Computed styles inside a block: { key: [selector, property, pseudo-element?] } → { key: value }. */
function styles(root, wanted) {
    return root.evaluate(
        (element, list) =>
            Object.fromEntries(
                Object.entries(list).map(([key, [selector, property, pseudo]]) => {
                    const target = selector ? element.querySelector(selector) : element;
                    return [key, getComputedStyle(target, pseudo || null).getPropertyValue(property)];
                })
            ),
        wanted
    );
}

/** The left edges of two elements, to tell which side each is on. */
const left = (locator) => locator.evaluate((element) => Math.round(element.getBoundingClientRect().left));
const top = (locator) => locator.evaluate((element) => Math.round(element.getBoundingClientRect().top));

/** Serves the test video (as video/webm) and records each request for it. */
async function serveVideo(page) {
    const requests = [];
    await page.route('**/hero.webm', (route) => {
        requests.push(route.request().url());
        return route.fulfill({ path: VIDEO, contentType: 'video/webm' });
    });
    return requests;
}

/* Hero ------------------------------------------------------------------ */

const cover = (page) => page.locator('.hive-hero--cover');

test('hero video: nothing loads before the page has, then it plays muted, and its button pauses it', async ({ page }) => {
    const requests = await serveVideo(page);
    // Hold the poster: the page can't finish loading until it arrives.
    let release;
    const poster = new Promise((resolve) => (release = resolve));
    await page.route('**/sneaker.svg', async (route) => {
        await poster;
        await route.continue();
    });
    const errors = await open(page, HERO, { waitUntil: 'domcontentloaded' });
    const video = cover(page).locator('video');

    await expect(cover(page).getByRole('button', { name: 'Metti in pausa il video' })).toBeVisible();
    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    expect(requests).toEqual([]);
    expect(await video.getAttribute('src')).toBeNull();

    release();
    await expect(cover(page)).toHaveClass(/is-playing/);
    expect(requests.length).toBeGreaterThan(0);
    expect(await video.evaluate((element) => ({ muted: element.muted, paused: element.paused }))).toEqual({ muted: true, paused: false });

    await cover(page).getByRole('button', { name: 'Metti in pausa il video' }).click();
    await expect(cover(page).getByRole('button', { name: 'Riproduci il video' })).toBeVisible();
    expect(await video.evaluate((element) => element.paused)).toBe(true);

    await cover(page).getByRole('button', { name: 'Riproduci il video' }).click();
    await expect.poll(() => video.evaluate((element) => element.paused)).toBe(false);
    expect(errors).toEqual([]);
});

test('hero video: with reduced motion the poster stays, and the video loads only when asked', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const requests = await serveVideo(page);
    const errors = await open(page, HERO);
    const play = cover(page).getByRole('button', { name: 'Riproduci il video' });

    await expect(play).toBeVisible();
    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    expect(requests).toEqual([]);
    expect(await cover(page).locator('video').getAttribute('src')).toBeNull();
    await expect(cover(page)).not.toHaveClass(/is-playing/);

    await play.click();
    await expect(cover(page)).toHaveClass(/is-playing/);
    expect(requests.length).toBeGreaterThan(0);
    await expect(cover(page).getByRole('button', { name: 'Metti in pausa il video' })).toBeVisible();
    expect(errors).toEqual([]);
});

test('hero video: it rests while out of view', async ({ page }) => {
    await serveVideo(page);
    await open(page, HERO);
    const video = cover(page).locator('video');
    await expect(cover(page)).toHaveClass(/is-playing/);

    await page.locator('#contatti').scrollIntoViewIfNeeded();
    await expect.poll(() => video.evaluate((element) => element.paused)).toBe(true);
    await page.evaluate(() => window.scrollTo(0, 0));
    await expect.poll(() => video.evaluate((element) => element.paused)).toBe(false);
});

test("hero: the block's own rules win over the theme's content rules", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await serveVideo(page);
    await open(page, HERO);

    expect(
        await styles(cover(page), {
            titleMarginTop: ['.hive-hero__title', 'margin-top'],
            titleAlign: ['.hive-hero__title', 'text-align'],
            titleCase: ['.hive-hero__title', 'text-transform'],
            titleColor: ['.hive-hero__title', 'color'],
            imageRadius: ['.hive-hero__image', 'border-radius'],
            imageMaxWidth: ['.hive-hero__image', 'max-width'],
            imageFit: ['.hive-hero__image', 'object-fit'],
            foregroundShadow: ['.hive-hero__foreground', 'box-shadow'],
            mainBackground: ['.hive-hero__button', 'background-color'],
            mainColor: ['.hive-hero__button', 'color'],
            mainUnderline: ['.hive-hero__button', 'text-decoration-line'],
            outlineBackground: ['.hive-hero__button--outline', 'background-color'],
            outlineColor: ['.hive-hero__button--outline', 'color'],
            togglePadding: ['.hive-hero__toggle', 'padding-top'],
            toggleColor: ['.hive-hero__toggle', 'color'],
        })
    ).toEqual({
        titleMarginTop: '0px',
        titleAlign: 'center',
        titleCase: 'uppercase',
        titleColor: 'rgb(255, 255, 255)',
        imageRadius: '0px',
        imageMaxWidth: 'none',
        imageFit: 'cover',
        foregroundShadow: 'none',
        mainBackground: 'rgb(255, 255, 255)',
        mainColor: 'rgb(10, 10, 10)',
        mainUnderline: 'none',
        outlineBackground: 'rgba(0, 0, 0, 0)',
        outlineColor: 'rgb(255, 255, 255)',
        togglePadding: '0px',
        toggleColor: 'rgb(255, 255, 255)',
    });

    // The accent is the brand colour pulled towards the white text: neither of the two.
    const accent = await styles(cover(page), { color: ['.hive-hero__accent', 'color'] });
    expect(accent.color).not.toBe('rgb(255, 255, 255)');
    expect(accent.color).not.toBe('rgb(114, 17, 36)');

    // Two columns on the theme's colours: the text keeps the page's colour, the outlined button too.
    const split = page.locator('.hive-hero--split');
    expect(
        await styles(split, {
            background: ['', 'background-color'],
            titleColor: ['.hive-hero__title', 'color'],
            titleMarginTop: ['.hive-hero__title', 'margin-top'],
            subtitleMargin: ['.hive-hero__subtitle', 'margin-bottom'],
            outlineColor: ['.hive-hero__button--outline', 'color'],
            outlineUnderline: ['.hive-hero__button--outline', 'text-decoration-line'],
            imagePosition: ['.hive-hero__image', 'position'],
        })
    ).toEqual({
        background: 'rgba(0, 0, 0, 0)',
        titleColor: 'rgb(34, 34, 34)',
        titleMarginTop: '0px',
        subtitleMargin: '32px',
        outlineColor: 'rgb(34, 34, 34)',
        outlineUnderline: 'none',
        imagePosition: 'absolute',
    });

    const about = page.locator('.hive-hero--about');
    expect(
        await styles(about, {
            listStyle: ['.hive-hero__values', 'list-style-type'],
            listMargin: ['.hive-hero__values', 'margin-left'],
            listPadding: ['.hive-hero__values', 'padding-left'],
            bullet: ['.hive-hero__value', 'content', '::before'],
            frame: ['.hive-hero__media', 'border-top-left-radius'],
        })
    ).toEqual({ listStyle: 'none', listMargin: '0px', listPadding: '0px', bullet: 'none', frame: '16px' });
});

test('hero: two columns from tablets up, the picture on its side; one column on phones', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await serveVideo(page);
    await open(page, HERO);
    const split = page.locator('.hive-hero--split');
    const about = page.locator('.hive-hero--about');

    // Diviso: the picture on the left; Chi siamo: on the right.
    expect(await left(split.locator('.hive-hero__media'))).toBeLessThan(await left(split.locator('.hive-hero__body')));
    expect(await left(about.locator('.hive-hero__media'))).toBeGreaterThan(await left(about.locator('.hive-hero__body')));
    await expect(about.locator('.hive-hero__value')).toHaveCount(2);

    // Phones: Diviso puts the picture first, Chi siamo the text.
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await top(split.locator('.hive-hero__media'))).toBeLessThan(await top(split.locator('.hive-hero__body')));
    expect(await top(about.locator('.hive-hero__media'))).toBeGreaterThan(await top(about.locator('.hive-hero__body')));
    // Nothing wider than the screen.
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
});

/* Carousel -------------------------------------------------------------- */

const carousel = (page) => page.locator('.hive-hero-carousel');
const current = (page) => carousel(page).locator('[data-hive-carousel-go][aria-current="true"]');
const slideInView = (page) => carousel(page).locator('[data-hive-carousel-track]').evaluate((track) => Math.round(track.scrollLeft / track.clientWidth));
const header = (page) => page.locator('.site-header');

test('carousel: arrows and dots move through the slides and wrap around; a swipe moves the dots', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    const errors = await open(page, CAROUSEL);
    const next = carousel(page).getByRole('button', { name: 'Slide successiva' });
    const prev = carousel(page).getByRole('button', { name: 'Slide precedente' });

    await expect(next).toBeVisible();
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '0');

    await next.click();
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '1');
    await expect.poll(() => slideInView(page)).toBe(1);

    await carousel(page).getByRole('button', { name: 'Vai alla slide 3' }).click();
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '2');
    await expect.poll(() => slideInView(page)).toBe(2);

    await next.click();
    await expect.poll(() => slideInView(page)).toBe(0);
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '0');

    await prev.click();
    await expect.poll(() => slideInView(page)).toBe(2);
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '2');

    // A swipe (the track scrolled by hand) moves the dots too.
    await carousel(page).locator('[data-hive-carousel-track]').evaluate((track) => track.scrollTo({ left: track.clientWidth, behavior: 'instant' }));
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '1');
    expect(errors).toEqual([]);
});

test('carousel: autoplay moves on by itself and waits while the pointer is over it', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.clock.install();
    const errors = await open(page, CAROUSEL);
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '0');

    await page.clock.runFor(6500);
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '1');
    await expect.poll(() => slideInView(page)).toBe(1);

    await carousel(page).hover();
    await page.clock.runFor(10000);
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '1');

    await header(page).hover();
    await page.clock.runFor(6500);
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '2');
    await expect.poll(() => slideInView(page)).toBe(2);
    expect(errors).toEqual([]);
});

test('carousel: autoplay waits while the keyboard is in it; its button comes first, and stops it', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.clock.install();
    await open(page, CAROUSEL);
    const stop = carousel(page).getByRole('button', { name: 'Ferma lo scorrimento automatico' });

    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    await expect(stop).toBeFocused();
    await page.clock.runFor(10000);
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '0');

    await page.keyboard.press('Shift+Tab');
    await page.clock.runFor(6500);
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '1');

    // Stopped with its button: it stays put, pointer away or not, until started again.
    await stop.click();
    const start = carousel(page).getByRole('button', { name: 'Avvia lo scorrimento automatico' });
    await expect(start).toBeVisible();
    await header(page).hover();
    await page.clock.runFor(10000);
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '1');

    await start.click();
    await header(page).hover();
    await page.clock.runFor(6500);
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '2');
});

test('carousel: with reduced motion there is no autoplay; the arrows still work', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.clock.install();
    await open(page, CAROUSEL);

    await expect(carousel(page).getByRole('button', { name: 'Slide successiva' })).toBeVisible();
    await expect(carousel(page).locator('[data-hive-carousel-toggle]')).toBeHidden();
    await page.clock.runFor(10000);
    await expect(current(page)).toHaveAttribute('data-hive-carousel-go', '0');

    await carousel(page).getByRole('button', { name: 'Slide successiva' }).click();
    await expect.poll(() => slideInView(page)).toBe(1);
});

test('carousel: only the first slide’s image loads with the page; the next one is asked for once it has loaded', async ({ page }) => {
    await open(page, CAROUSEL);
    const images = carousel(page).locator('.hive-hero-carousel__image');

    await expect(images.nth(0)).toHaveAttribute('fetchpriority', 'high');
    await expect(images.nth(1)).toHaveAttribute('loading', 'eager');
    await expect.poll(() => images.nth(1).evaluate((image) => image.complete && image.naturalWidth > 0)).toBe(true);
    await expect(images.nth(2)).toHaveAttribute('loading', 'lazy');
});

test("carousel: the block's own rules win over the theme's content rules", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await open(page, CAROUSEL);
    await expect(carousel(page).locator('[data-hive-carousel-controls]')).toBeVisible();

    expect(
        await styles(carousel(page), {
            snap: ['.hive-hero-carousel__track', 'scroll-snap-type'],
            titleMarginTop: ['.hive-hero-carousel__title', 'margin-top'],
            titleAlign: ['.hive-hero-carousel__title', 'text-align'],
            titleColor: ['.hive-hero-carousel__title', 'color'],
            subtitleAlign: ['.hive-hero-carousel__subtitle', 'text-align'],
            imageRadius: ['.hive-hero-carousel__image', 'border-radius'],
            imageFit: ['.hive-hero-carousel__image', 'object-fit'],
            buttonBackground: ['.hive-hero-carousel__button', 'background-color'],
            buttonColor: ['.hive-hero-carousel__button', 'color'],
            buttonUnderline: ['.hive-hero-carousel__button', 'text-decoration-line'],
            arrowPadding: ['.hive-hero-carousel__arrow', 'padding-top'],
            arrowWidth: ['.hive-hero-carousel__arrow', 'width'],
            dotWidth: ['.hive-hero-carousel__dot[aria-current="true"]', 'width'],
            dotBackground: ['.hive-hero-carousel__dot[aria-current="true"]', 'background-color'],
        })
    ).toEqual({
        snap: 'x mandatory',
        titleMarginTop: '0px',
        titleAlign: 'start',
        titleColor: 'rgb(255, 255, 255)',
        subtitleAlign: 'start',
        imageRadius: '0px',
        imageFit: 'cover',
        buttonBackground: 'rgb(255, 255, 255)',
        buttonColor: 'rgb(10, 10, 10)',
        buttonUnderline: 'none',
        arrowPadding: '0px',
        arrowWidth: '44px',
        dotWidth: '24px',
        dotBackground: 'rgb(255, 255, 255)',
    });

    // One slide per stop, as wide as the carousel; the picture fills it.
    const sizes = await carousel(page).evaluate((root) => {
        const slide = root.querySelector('.hive-hero-carousel__slide');
        const image = slide.querySelector('img');
        return { carousel: root.clientWidth, slide: slide.clientWidth, imageHeight: image.clientHeight, slideHeight: slide.clientHeight };
    });
    expect(sizes.slide).toBe(sizes.carousel);
    expect(sizes.imageHeight).toBe(sizes.slideHeight);
});

/* Call to action -------------------------------------------------------- */

test("cta: the block's own rules win over the theme's content rules", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    const errors = await open(page, CTA);
    const banner = page.locator('.hive-cta--banner');

    expect(
        await styles(banner, {
            background: ['', 'background-color'],
            titleMarginTop: ['.hive-cta__title', 'margin-top'],
            titleAlign: ['.hive-cta__title', 'text-align'],
            titleCase: ['.hive-cta__title', 'text-transform'],
            titleColor: ['.hive-cta__title', 'color'],
            textAlign: ['.hive-cta__text', 'text-align'],
            imageRadius: ['.hive-cta__image', 'border-radius'],
            imageShadow: ['.hive-cta__image', 'box-shadow'],
            imageFit: ['.hive-cta__image', 'object-fit'],
            backdrop: ['.hive-cta__media', 'opacity'],
            glow: ['.hive-cta__glow', 'height'],
            buttonBackground: ['.hive-cta__button', 'background-color'],
            buttonColor: ['.hive-cta__button', 'color'],
            buttonUnderline: ['.hive-cta__button', 'text-decoration-line'],
        })
    ).toEqual({
        background: 'rgb(10, 10, 10)',
        titleMarginTop: '0px',
        titleAlign: 'center',
        titleCase: 'uppercase',
        titleColor: 'rgb(255, 255, 255)',
        textAlign: 'center',
        imageRadius: '0px',
        imageShadow: 'none',
        imageFit: 'cover',
        backdrop: '0.4',
        glow: '1px',
        buttonBackground: 'rgb(255, 255, 255)',
        buttonColor: 'rgb(10, 10, 10)',
        buttonUnderline: 'none',
    });

    const split = page.locator('.hive-cta--split');
    expect(await styles(split, { titleAlign: ['.hive-cta__title', 'text-align'], titleMarginTop: ['.hive-cta__title', 'margin-top'] })).toEqual({
        titleAlign: 'start',
        titleMarginTop: '0px',
    });
    expect(errors).toEqual([]);
});

test('cta: image and text side by side from tablets up, reversed puts the picture on the right; stacked on phones', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await open(page, CTA);
    const split = page.locator('.hive-cta--split');

    expect(await left(split.locator('.hive-cta__media'))).toBeGreaterThan(await left(split.locator('.hive-cta__body')));
    const desktop = await split.evaluate((root) => root.querySelector('.hive-cta__media').getBoundingClientRect().height);
    expect(desktop).toBeGreaterThan(300);

    await page.setViewportSize({ width: 390, height: 844 });
    expect(await top(split.locator('.hive-cta__media'))).toBeLessThan(await top(split.locator('.hive-cta__body')));
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
});
