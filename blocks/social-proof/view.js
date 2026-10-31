/**
 * Acquisti recenti — the toast (script module, loaded only with the block).
 *
 * Once the page has loaded and the browser is idle, and after the block's
 * delay, it asks the feed (a public GET the CDN keeps), then shows one
 * purchase every interval for a few seconds. Politely:
 *
 *   a few toasts per session, each purchase once (sessionStorage); none at
 *   all, and no request, once the session's share is used up
 *   the × closes them for the rest of the session
 *   it waits while a dialog is open, while the product page's add-to-cart
 *   bar is up or while the tab is hidden, and steps away if one of them
 *   comes up while it shows
 *   it stops for good on this page once something goes into the cart
 *   it stays while the mouse or the focus is on it
 *   one toast per page, moved under <body> so it sits in the screen's corner
 *   whatever wraps the block
 *
 * The cart and checkout pages don't print the block at all (render.php).
 */
import { fresh, keyOf, meta, parseState } from './feed.js';

const KEY = 'hive_social_proof';

function load() {
    try {
        return parseState(sessionStorage.getItem(KEY));
    } catch {
        return parseState('');
    }
}

function save(state) {
    try {
        sessionStorage.setItem(KEY, JSON.stringify({ ...state, seen: state.seen.slice(-50) }));
    } catch {
        // Storage off: at worst a purchase shows again.
    }
}

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** Resolves once the page has loaded and the browser has a quiet moment. */
function settled() {
    return new Promise((resolve) => {
        const idle = () => ('requestIdleCallback' in window ? requestIdleCallback(() => resolve(), { timeout: 3000 }) : setTimeout(resolve, 200));
        if (document.readyState === 'complete') idle();
        else window.addEventListener('load', idle, { once: true });
    });
}

/** Something the toast must not cover or interrupt: a dialog, the product page's add-to-cart bar, a hidden tab. */
function busy() {
    return document.hidden || document.documentElement.classList.contains('hive-dialog-open') || Boolean(document.querySelector('dialog[open], [data-hive-sticky].is-visible'));
}

function setup(root) {
    let config;
    try {
        config = JSON.parse(root.dataset.hiveSocialProof);
    } catch {
        return;
    }
    const state = load();
    const max = Math.max(1, Number(config.max) || 3);
    if (!config.src || state.off || state.shown >= max) return;

    const link = root.querySelector('[data-hive-social-proof-link]');
    const media = root.querySelector('[data-hive-social-proof-media]');
    const product = root.querySelector('[data-hive-social-proof-product]');
    const line = root.querySelector('[data-hive-social-proof-meta]');
    const close = root.querySelector('[data-hive-social-proof-close]');
    if (!link || !media || !product || !line) return;
    // Straight under <body>: a theme wrapper with a transform would otherwise pin the toast to itself, not to the screen.
    if (root.parentElement !== document.body) document.body.append(root);

    let queue = [];
    let stopped = false;
    let held = false;
    let hideTimer = 0;
    let nextTimer = 0;
    let watch = 0;

    const hide = () => {
        clearTimeout(hideTimer);
        clearInterval(watch);
        root.classList.remove('is-visible');
    };
    const stop = () => {
        stopped = true;
        clearTimeout(nextTimer);
        hide();
    };
    const hideLater = () => {
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => !held && hide(), Number(config.duration) || 6000);
    };

    function show() {
        if (stopped) return;
        // Not now: try again in a while (it doesn't count as shown).
        if (busy()) {
            nextTimer = setTimeout(show, 5000);
            return;
        }
        const item = queue.shift();
        if (!item || state.shown >= max) {
            stop();
            return;
        }
        state.shown++;
        state.seen.push(keyOf(item));
        save(state);

        product.textContent = item.product;
        line.textContent = meta(item, Date.now());
        media.replaceChildren();
        media.hidden = !item.image;
        if (item.image) {
            const image = document.createElement('img');
            image.className = 'hive-social-proof__image';
            image.alt = '';
            image.width = 56;
            image.height = 56;
            image.decoding = 'async';
            image.src = item.image;
            media.append(image);
        }
        if (item.url) {
            link.href = item.url;
            link.setAttribute('aria-label', `${item.product}: vedi il prodotto`);
        } else {
            link.removeAttribute('href');
            link.removeAttribute('aria-label');
        }

        root.classList.add('is-visible');
        hideLater();
        // Something came up meanwhile (a dialog, the add-to-cart bar): step away.
        watch = setInterval(() => busy() && hide(), 500);
        if (state.shown < max && queue.length) nextTimer = setTimeout(show, Math.max(5000, Number(config.interval) || 25000));
    }

    close?.addEventListener('click', () => {
        state.off = true;
        save(state);
        stop();
    });
    const hold = () => {
        held = true;
        clearTimeout(hideTimer);
    };
    const release = () => {
        held = false;
        if (root.classList.contains('is-visible')) hideLater();
    };
    root.addEventListener('pointerenter', hold);
    root.addEventListener('pointerleave', release);
    root.addEventListener('focusin', hold);
    root.addEventListener('focusout', release);

    // Something went into the cart: the visitor is buying (and a cart drawer may be opening).
    document.addEventListener('hive:added-to-cart', stop);
    document.body.addEventListener('wc-blocks_added_to_cart', stop);
    window.jQuery?.(document.body).on('added_to_cart', stop);

    settled()
        .then(() => wait(Number(config.delay) || 0))
        .then(() => {
            if (stopped) return null;
            // No cookies: the answer is the same for everyone, so the CDN can keep it.
            return fetch(config.src, { credentials: 'omit', headers: { Accept: 'application/json' } }).then((response) => (response.ok ? response.json() : null));
        })
        .then((data) => {
            if (stopped || !data) return;
            queue = fresh(data.items, { days: Number(config.days) || 14, seen: state.seen });
            if (queue.length) show();
        })
        .catch(() => {});
}

// One toast per page, even with the block in a template part and in the content.
const first = document.querySelector('[data-hive-social-proof]');
if (first) setup(first);
