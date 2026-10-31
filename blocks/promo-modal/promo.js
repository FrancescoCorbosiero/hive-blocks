/**
 * Popup promozionale — the rules of when it opens, apart from the page.
 * Pure: tested by tests/js/promo-modal.test.mjs.
 */

/** Longest delay the block accepts: two minutes. */
const MAX_DELAY = 120_000;

/**
 * The block's delay in milliseconds. Blocks saved while golden-hive-blocks'
 * editor stored seconds carry 5–60: a delay under 100 is in seconds.
 */
export function delayMs(value) {
    const n = Math.max(0, Number(value) || 0);
    return Math.min(MAX_DELAY, n > 0 && n < 100 ? n * 1000 : n);
}

/** What the browser keeps to remember a popup was shown. */
export const seenKey = (id) => `hive_promo_${id}`;

/** A mouseout that leaves the page through its top edge: on the way to the tabs or the address bar. */
export function isExitIntent(event) {
    return !event.relatedTarget && Number(event.clientY) <= 0;
}

/**
 * Whether a link opens the popup: "#promo-popup", or this very page's
 * address with that hash.
 *
 * @param {string} href The link's href attribute.
 * @param {string} id   The popup's id (modalId).
 * @param {string} page This page's address.
 */
export function opensPromo(href, id, page) {
    if (!href || !id) return false;
    try {
        const target = new URL(href, page);
        const here = new URL(page);
        return target.hash === `#${id}` && target.origin === here.origin && target.pathname === here.pathname && target.search === here.search;
    } catch {
        return false;
    }
}
