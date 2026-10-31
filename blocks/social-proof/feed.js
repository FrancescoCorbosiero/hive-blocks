/**
 * Acquisti recenti — reading the feed (Hive\Shop\SocialProof: GET
 * /wp-json/hive/v1/social-proof) in the browser: which purchases are worth
 * showing and how long ago each was, from the payment time, so a cached
 * page still says "2 ore fa" right. Pure: tested by
 * tests/js/social-proof.test.mjs.
 */

/** A purchase's key, to show it once per session. */
export const keyOf = (item) => `${item.ts}|${item.product}`;

/**
 * "adesso", "1 minuto fa", "12 minuti fa", "1 ora fa", "3 ore fa", "ieri", "5 giorni fa".
 *
 * @param {number} ts  When it was paid, UTC seconds.
 * @param {number} now Now, in milliseconds.
 */
export function ago(ts, now = Date.now()) {
    const seconds = Math.max(0, Math.floor(now / 1000 - Number(ts)));
    if (seconds < 60) return 'adesso';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return minutes === 1 ? '1 minuto fa' : `${minutes} minuti fa`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return hours === 1 ? '1 ora fa' : `${hours} ore fa`;
    const days = Math.floor(hours / 24);
    return days === 1 ? 'ieri' : `${days} giorni fa`;
}

/**
 * The purchases to show, in the feed's order (newest first): those with a
 * product, paid within the last `days` days, not yet seen in this session.
 *
 * @param {Array}  items The feed's items: {product, image, url, city, ts}.
 * @param {{days?: number, seen?: string[], now?: number}} options
 */
export function fresh(items, { days = 14, seen = [], now = Date.now() } = {}) {
    const since = now / 1000 - days * 86400;
    return (Array.isArray(items) ? items : []).filter(
        (item) => item && typeof item.product === 'string' && item.product !== '' && Number(item.ts) >= since && !seen.includes(keyOf(item))
    );
}

/** The toast's second line: "Piacenza · 2 ore fa", or just "2 ore fa". */
export function meta(item, now = Date.now()) {
    return [item.city || '', ago(item.ts, now)].filter(Boolean).join(' · ');
}

/**
 * This session's record, from what sessionStorage holds: how many toasts
 * were shown, which purchases, and whether the visitor closed them. Anything
 * unreadable counts as a fresh session.
 *
 * @return {{shown: number, seen: string[], off: boolean}}
 */
export function parseState(text) {
    try {
        const state = JSON.parse(text || '{}') || {};
        return {
            shown: Math.max(0, Number(state.shown) || 0),
            seen: Array.isArray(state.seen) ? state.seen.map(String) : [],
            off: state.off === true,
        };
    } catch {
        return { shown: 0, seen: [], off: false };
    }
}
