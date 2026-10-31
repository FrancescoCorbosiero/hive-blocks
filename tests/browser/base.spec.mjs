// The shared rules in assets/base.css, against a theme's content rules: a
// block's button (<button class="hive-button">) keeps Hive's look where
// .entry-content button would restyle it. The legit-check fixture carries
// such a theme; the button is added to its block.
import { expect, test } from '@playwright/test';

test("a block's button keeps Hive's look against the theme's button rule", async ({ page }) => {
    await page.goto('/tests/browser/fixtures/legit-check.html');
    const look = await page.locator('.hive-legit').evaluate((block) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'hive-button';
        button.textContent = 'Prova';
        block.append(button);
        const style = getComputedStyle(button);
        const token = document.createElement('span');
        token.style.color = 'var(--hive-button)';
        block.append(token);
        return {
            padding: style.padding,
            borderWidth: style.borderTopWidth,
            borderStyle: style.borderTopStyle,
            radiusSet: style.borderTopLeftRadius !== '0px',
            background: style.backgroundColor === getComputedStyle(token).color,
            textTransform: style.textTransform,
        };
    });
    expect(look).toEqual({ padding: '10px 20px', borderWidth: '1px', borderStyle: 'solid', radiusSet: true, background: true, textTransform: 'none' });
});

// Modern Image Formats (and plugins like it) can wrap a picture in content in
// <picture> with AVIF/WebP <source>s, in the HTML the server sends: Hive's
// pictures keep their place and size. The same fixture is loaded as it is,
// then with every <img> wrapped as the plugin would; the <source>'s type is
// one no browser takes, so the same file loads and only the wrapper differs.
async function pictureBoxes(page, url) {
    await page.goto(url);
    await page.waitForLoadState('networkidle');
    return page.locator('.hive-block img').evaluateAll((images) =>
        images.map((image) => {
            const box = image.getBoundingClientRect();
            return [image.getAttribute('src'), Math.round(box.x), Math.round(box.y), Math.round(box.width), Math.round(box.height)];
        })
    );
}

for (const fixture of ['hero', 'hero-carousel', 'cta', 'shop', 'product']) {
    test(`pictures wrapped in <picture> keep their layout: ${fixture}`, async ({ page }) => {
        await page.setViewportSize({ width: 1200, height: 900 });
        const url = `/tests/browser/fixtures/${fixture}.html`;
        const plain = await pictureBoxes(page, url);
        expect(plain.length).toBeGreaterThan(0);

        await page.route(`**${url}`, async (route) => {
            const response = await route.fetch();
            const body = (await response.text()).replace(/<img\b[^>]*>/g, (tag) => {
                const src = /\ssrc="([^"]*)"/.exec(tag)?.[1] ?? '';
                return `<picture><source type="image/x-hive-test" srcset="${src}">${tag}</picture>`;
            });
            await route.fulfill({ response, body });
        });
        const wrapped = await pictureBoxes(page, url);
        expect(await page.locator('.hive-block picture > img').count()).toBe(plain.length);
        expect(wrapped).toEqual(plain);
    });
}
