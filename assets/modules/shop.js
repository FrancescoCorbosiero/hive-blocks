/**
 * @hive/shop — the shop's shared browser code:
 *
 *   getProduct(id)    a product's data (Quick View, Quick Add) from Hive's
 *                     cacheable REST read, fetched once per page
 *   addToCart(id, n)  through WooCommerce's own wc-ajax=add_to_cart (a
 *                     variation id works too), then the mini cart refreshes
 *                     with the fragments WooCommerce returns
 *   cartUpdated()     the same refresh, for an answer got another way (the
 *                     product page's form)
 *   settings          cart URL, WhatsApp number
 *
 * Site data comes from the module data WordPress prints
 * (Hive\Shop\ProductData::module_data()).
 */
import { moduleData } from '@hive/dom';

const config = moduleData('@hive/shop');

export const settings = Object.freeze({
    cartUrl: config.cartUrl || '',
    whatsapp: config.whatsapp || '',
});

const products = new Map();

/** A product's data, fetched once per page (and shared by every caller). */
export function getProduct(id) {
    const key = String(id);
    if (!products.has(key)) {
        const url = new URL(`${config.api || '/wp-json/hive/v1/'}product/${encodeURIComponent(key)}`, location.href);
        if (config.v) url.searchParams.set('v', String(config.v));
        // No cookies: the answer is the same for everyone, so caches can share it.
        const request = fetch(url, { credentials: 'omit', headers: { Accept: 'application/json' } }).then((response) => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        });
        request.catch(() => products.delete(key)); // a failed fetch can be tried again
        products.set(key, request);
    }
    return products.get(key);
}

/**
 * Adds to the cart. Resolves with WooCommerce's answer; rejects when
 * WooCommerce refuses, with error.productUrl pointing to the product page
 * that explains why.
 *
 * @param {number} id A product or variation id.
 * @param {number} quantity
 * @param {Element|null} source The button, passed on to WooCommerce's added_to_cart event.
 */
export async function addToCart(id, quantity = 1, source = null) {
    if (!config.cart) throw new Error('WooCommerce cart endpoint missing');
    const body = new FormData();
    body.append('product_id', String(id));
    body.append('quantity', String(quantity));

    const response = await fetch(config.cart, { method: 'POST', body, credentials: 'same-origin' });
    const data = await response.json().catch(() => null);
    if (!data || data.error) {
        const error = new Error('WooCommerce refused the product');
        error.productUrl = data?.product_url || '';
        throw error;
    }

    cartUpdated(data, source, { id, quantity });
    return data;
}

/**
 * After a product went into the cart: WooCommerce's fresh fragments in
 * place (mini cart, cart count), then the events the theme's mini cart,
 * WooCommerce's scripts and Hive listen to.
 *
 * @param {{fragments?: Object, cart_hash?: string}} data WooCommerce's answer.
 * @param {Element|null} source The button that added it.
 * @param {Object} detail For the hive:added-to-cart event.
 */
export function cartUpdated(data, source = null, detail = {}) {
    applyFragments(data.fragments);
    document.dispatchEvent(new CustomEvent('hive:added-to-cart', { detail: { ...detail, data } }));
    const $ = window.jQuery;
    if ($) $(document.body).trigger('added_to_cart', [data.fragments, data.cart_hash, source ? $(source) : undefined]);
}

/** Puts WooCommerce's fresh fragments (mini cart, cart count) in place of the old ones. */
function applyFragments(fragments) {
    for (const [selector, html] of Object.entries(fragments || {})) {
        let nodes = [];
        try {
            nodes = document.querySelectorAll(selector);
        } catch {
            continue;
        }
        for (const node of nodes) {
            const template = document.createElement('template');
            template.innerHTML = html;
            const replacement = template.content.firstElementChild;
            if (replacement) node.replaceWith(replacement);
            else node.remove();
        }
    }
}
