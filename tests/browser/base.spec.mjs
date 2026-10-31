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
