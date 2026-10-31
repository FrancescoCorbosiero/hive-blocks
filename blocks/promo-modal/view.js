/**
 * Popup promozionale — when the offer opens (script module, loaded only
 * with the block). The offer is the block's <template>; it goes into a
 * dialog (@hive/dialog, loaded the first time it opens):
 *
 *   by itself, after the delay, or on exit intent (the mouse leaving through
 *   the top of the page, not before the delay; on touch screens, where
 *   nobody's mouse leaves, after the delay), once per visitor (localStorage)
 *   or once per visit (sessionStorage), never over another dialog
 *   whenever a link to #modalId is clicked
 */
import { delayMs, isExitIntent, opensPromo, seenKey } from './promo.js';

function storage(once) {
    try {
        return once ? window.localStorage : window.sessionStorage;
    } catch {
        return null;
    }
}

/** "Copia": the code to the clipboard; without a clipboard, the code selected to copy by hand. */
function copyButton(button) {
    button.addEventListener('click', async () => {
        const code = button.dataset.hivePromoCopy || '';
        try {
            await navigator.clipboard.writeText(code);
        } catch {
            const value = button.parentElement?.querySelector('.hive-promo__code-value');
            const selection = window.getSelection();
            if (value && selection) {
                const range = document.createRange();
                range.selectNodeContents(value);
                selection.removeAllRanges();
                selection.addRange(range);
            }
            return;
        }
        button.textContent = 'Copiato';
        setTimeout(() => (button.textContent = 'Copia'), 2000);
    });
}

function setup(root) {
    let config;
    try {
        config = JSON.parse(root.dataset.hivePromoModal);
    } catch {
        return;
    }
    const template = root.querySelector('template[data-hive-promo-template]');
    if (!template || !config.id) return;

    const store = storage(config.once);
    const key = seenKey(config.id);
    const seen = () => {
        try {
            return Boolean(store?.getItem(key));
        } catch {
            return false;
        }
    };
    const remember = () => {
        try {
            store?.setItem(key, String(Date.now()));
        } catch {
            // Storage off: it may show again on the next page.
        }
    };

    let view = null;
    async function open(trigger = null) {
        const { dialog } = await import('@hive/dialog');
        if (!view) {
            view = dialog(`promo-${config.id}`, { label: config.label || '', sheet: !config.image });
            view.body.replaceChildren(template.content.cloneNode(true));
            view.body.querySelectorAll('[data-hive-promo-copy]').forEach(copyButton);
        }
        await view.open(trigger);
    }

    // A link to #id opens it, whenever, shown before or not.
    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link || !opensPromo(link.getAttribute('href'), config.id, window.location.href)) return;
        event.preventDefault();
        open(link);
    });

    if (!config.auto || seen()) return;

    let tries = 0;
    const show = () => {
        if (seen()) return;
        // Another dialog is open (Quick View, a size guide…): not on top of it; again in a while, three times at most.
        if (document.querySelector('dialog[open]') || document.documentElement.classList.contains('hive-dialog-open')) {
            if (++tries <= 3) setTimeout(show, 5000);
            return;
        }
        remember();
        open();
    };

    const delay = delayMs(config.delay);
    if (config.trigger === 'exit' && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        const leave = (event) => {
            if (!isExitIntent(event)) return;
            document.removeEventListener('mouseout', leave);
            show();
        };
        setTimeout(() => document.addEventListener('mouseout', leave), delay);
    } else {
        setTimeout(show, delay);
    }
}

document.querySelectorAll('[data-hive-promo-modal]').forEach(setup);
