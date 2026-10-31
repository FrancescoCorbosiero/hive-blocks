// The Store Hub bridge (wc-gh/v1, Hive\Hub) on a real WordPress and WooCommerce:
// what the app reads and writes, in golden-hive-blocks' shapes and under its
// names (golden-hive/shortcode-wrapper, [gh_product_rail]), though the page
// holds Hive's. The page is tests/e2e/pages/hub.php; the app signs in with
// WooCommerce's REST keys, the test with WordPress's cookie and nonce.
import { expect, test } from '@playwright/test';
import { login } from './helpers.mjs';

// It logs in to wp-admin, slow with WooCommerce on one PHP worker.
test.slow();

const api = (route) => `/?rest_route=/wc-gh/v1/${route}`;

const FEATURES = [
    'homepage', 'rail', 'rail-visible', 'products', 'block-write', 'history',
    'rail-pin', 'rail-exclude', 'rail-fallback', 'rail-limit', 'block-fields', 'archive-follow',
    'rail-source', 'rail-hidden', 'layout-insert', 'layout-remove', 'layout-arrange',
];

const CARD = [
    'id', 'sku', 'name', 'type', 'status', 'visibility', 'stock_status', 'price', 'price_max',
    'on_sale', 'image', 'permalink', 'edit_link', 'created',
];

test('the Vetrina reads a page of sections and pins a product, under the names the app knows', async ({ page }) => {
    await login(page);
    const nonce = (await (await page.request.get('/wp-admin/admin-ajax.php?action=rest-nonce')).text()).trim();
    const headers = { 'X-WP-Nonce': nonce };
    const get = async (route) => {
        const response = await page.request.get(api(route), { headers });
        expect(response.status(), route).toBe(200);
        return response.json();
    };
    const write = (data) => page.request.post(api('homepage/block'), { headers, data });

    // What the bridge can do: golden-hive-blocks' contract.
    const capabilities = await get('capabilities');
    expect(capabilities.api).toBe(1);
    expect(capabilities.features).toEqual(FEATURES);
    expect(capabilities.rail_types).toEqual(['recent', 'best_selling', 'sale', 'featured', 'top_rated']);
    expect(capabilities.fallbacks).toEqual(['menu_order', 'date', 'popularity', 'price', 'price-desc', 'rating']);
    expect(capabilities.max_ids).toBe(100);
    expect(typeof capabilities.hide_out_of_stock).toBe('boolean');
    expect(Object.keys(capabilities.fields)).toEqual([
        'golden-hive/shortcode-wrapper',
        'golden-hive/category-slider',
        'golden-hive/brand-marquee',
        'golden-hive/faq-schema',
        'golden-hive/social-proof',
        'golden-hive/whatsapp-button',
    ]);
    expect(capabilities.fields['golden-hive/shortcode-wrapper'].hidden).toEqual({ type: 'enum', options: ['', 'yes'] });

    // The page: its one section, as golden-hive-blocks' block holding [gh_product_rail].
    const [vetrina] = await (await page.request.get('/?rest_route=/wp/v2/pages&slug=vetrina', { headers })).json();
    const home = await get(`homepage&page_id=${vetrina.id}`);
    expect(home.page_id).toBe(vetrina.id);
    expect(home.features).toEqual(FEATURES);
    expect(home.blocks).toHaveLength(1);
    const [block] = home.blocks;
    expect([block.path, block.name, block.kind]).toEqual(['0', 'golden-hive/shortcode-wrapper', 'rail']);
    const { rail } = block;
    expect(rail.key).toBe('type:recent#0');
    expect(rail.shortcode).toMatch(/^\[gh_product_rail type=/);
    expect(rail.atts).toMatchObject({ type: 'recent', limit: '4' });
    expect([rail.title, rail.fields.title, rail.limit, rail.editable, rail.switched_off]).toEqual(['Novità', 'Novità', 4, true, false]);
    expect(Object.keys(rail.fields)).toEqual(['eyebrow', 'title', 'backgroundColor', 'buttonText', 'buttonUrl', 'hidden']);
    expect(rail.products.length).toBeGreaterThan(0);
    expect(rail.products.length).toBeLessThanOrEqual(4);
    expect(Object.keys(rail.products[0])).toEqual(CARD);

    // Every product the section can show, the newest first: pinned, the oldest comes first.
    const all = await get(`rail&page_id=${vetrina.id}&key=${encodeURIComponent(rail.key)}`);
    expect(all.total).toBe(all.visible.length);
    expect(all.items).toHaveLength(Math.min(all.total, 60));
    const oldest = all.visible.at(-1);

    const pin = {
        page_id: vetrina.id,
        path: block.path,
        block_name: block.name,
        expected_attrs_hash: rail.attrs_hash,
        expected_modified_gmt: home.modified_gmt,
        rail: { pin: [oldest], exclude: [], fallback: '' },
    };
    try {
        const pinned = await write(pin);
        expect(pinned.status()).toBe(200);
        const answer = await pinned.json();
        expect([answer.changed, answer.key, answer.after]).toEqual([true, 'type:recent#0', `[gh_product_rail type="recent" limit="4" pin="${oldest}"]`]);
        expect(answer.rendered[0]).toBe(oldest);

        // Read back.
        const after = (await get(`homepage&page_id=${vetrina.id}`)).blocks[0].rail;
        expect(after.pin).toEqual([oldest]);
        expect(after.shortcode).toBe(`[gh_product_rail type="recent" limit="4" pin="${oldest}"]`);
        expect(after.products[0].id).toBe(oldest);
        expect(after.attrs_hash).toBe(answer.attrs_hash);

        // What was read before the pin is out of date now: refused, not guessed.
        const stale = await write(pin);
        expect(stale.status()).toBe(409);
        expect((await stale.json()).code).toBe('ghb_stale_page');
    } finally {
        // Unpinned again whatever happened above, naming the block as Hive does.
        const now = await get(`homepage&page_id=${vetrina.id}`);
        const cleared = await write({
            ...pin,
            block_name: 'hive/shortcode-wrapper',
            expected_attrs_hash: now.blocks[0].rail.attrs_hash,
            expected_modified_gmt: now.modified_gmt,
            rail: { pin: [], exclude: [], fallback: '' },
        });
        expect(cleared.status()).toBe(200);
    }
    expect((await get(`homepage&page_id=${vetrina.id}`)).blocks[0].rail.pin).toEqual([]);
});

test('without manage_woocommerce the bridge answers nothing', async ({ page }) => {
    const response = await page.request.get(api('capabilities'));
    expect(response.status()).toBe(401);
    expect((await response.json()).code).toBe('ghb_forbidden');
});
