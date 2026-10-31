/**
 * @hive/size-rail — "Nella tua taglia" (Hive\Shop\SizeRail): the size this
 * browser remembers fills the rail, from GET hive/v1/size-rail?size=…, a
 * read the CDN keeps per size. Picking a size here, on a product page or in
 * the newsletter remembers it. Nothing in that size: the size alert's form
 * (wc-ajax=hive_alerts).
 */
import { h, moduleData } from '@hive/dom';

const config = moduleData('@hive/size-rail');
const KEY = 'hive_size';

/** The size this browser remembers: {key, label}, or null. */
export function remembered() {
    try {
        const saved = JSON.parse(localStorage.getItem(KEY) || 'null');
        return saved && typeof saved.key === 'string' && saved.key ? saved : null;
    } catch {
        return null;
    }
}

export function remember(key, label) {
    try {
        localStorage.setItem(KEY, JSON.stringify({ key, label }));
    } catch {
        // No storage: the size is asked again next time.
    }
}

for (const root of document.querySelectorAll('[data-hive-size-rail]')) setup(root);

function setup(root) {
    const picker = root.querySelector('[data-hive-size-picker]');
    const body = root.querySelector('[data-hive-size-body]');
    const current = root.querySelector('[data-hive-size-current]');
    const change = root.querySelector('[data-hive-size-change]');
    const chips = [...root.querySelectorAll('.hive-size-rail__chip')];

    const mark = (key) => chips.forEach((chip) => chip.setAttribute('aria-pressed', String(chip.dataset.key === key)));
    const showPicker = (on) => {
        picker.hidden = !on;
        change.hidden = on;
    };

    async function load(key, label) {
        mark(key);
        body.setAttribute('aria-busy', 'true');
        const url = new URL(`${config.api || '/wp-json/hive/v1/'}size-rail`, location.href);
        url.searchParams.set('size', key);
        url.searchParams.set('limit', root.dataset.limit || '12');
        url.searchParams.set('columns', root.dataset.columns || '4');
        if (root.dataset.v) url.searchParams.set('v', root.dataset.v);
        try {
            const response = await fetch(url, { credentials: 'omit', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            current.textContent = `· ${data.label || label}`;
            current.hidden = false;
            showPicker(false);
            if (data.html) {
                // Hive's own rail markup, built by the server for every visitor.
                body.innerHTML = data.html;
                const rail = body.querySelector('.hive-rail');
                if (rail) import('@hive/rail').then(({ setup: rails }) => rails(rail));
            } else {
                body.replaceChildren(empty(data.label || label, key, data.alerts));
            }
        } catch {
            // The size no longer exists, or the read failed: ask again.
            showPicker(true);
            body.replaceChildren();
        } finally {
            body.removeAttribute('aria-busy');
        }
    }

    root.addEventListener('click', (event) => {
        const chip = event.target instanceof Element ? event.target.closest('.hive-size-rail__chip') : null;
        if (!chip) return;
        remember(chip.dataset.key, chip.textContent.trim());
        load(chip.dataset.key, chip.textContent.trim());
    });
    change.addEventListener('click', () => {
        showPicker(true);
        chips.find((chip) => chip.getAttribute('aria-pressed') === 'true')?.focus();
    });

    const saved = remembered();
    if (!saved || !chips.some((chip) => chip.dataset.key === saved.key)) return;
    // Only once it nears the screen: below the fold, nothing is fetched for nothing.
    if (!('IntersectionObserver' in window)) {
        load(saved.key, saved.label);
        return;
    }
    const near = new IntersectionObserver(
        (entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) return;
            near.disconnect();
            load(saved.key, saved.label);
        },
        { rootMargin: '600px 0px' }
    );
    near.observe(root);
}

/** Nothing in this size: say so, and offer the size alert. */
function empty(label, key, alerts) {
    const box = h('div', { class: 'hive-size-rail__empty' }, h('p', { class: 'hive-size-rail__empty-title', text: `Nessun paio disponibile in taglia ${label}, per ora.` }));
    if (!alerts || !config.alerts) return box;
    const status = h('p', { class: 'hive-size-rail__status', role: 'status' });
    const email = h('input', { class: 'hive-size-rail__email', type: 'email', name: 'email', placeholder: 'La tua email', autocomplete: 'email', required: true, 'aria-label': 'La tua email' });
    const form = h(
        'form',
        { class: 'hive-size-rail__alert', novalidate: true },
        h('input', { type: 'hidden', name: 'sizes[]', value: key }),
        h('input', { class: 'hive-size-rail__trap', type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', 'aria-hidden': 'true' }),
        email,
        h('button', { type: 'submit', class: 'hive-button', text: 'Avvisami' })
    );
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!email.value || !email.checkValidity()) {
            status.textContent = 'Scrivi un indirizzo email valido.';
            return;
        }
        status.textContent = 'Un attimo…';
        try {
            const response = await fetch(config.alerts, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json();
            status.textContent = data.message || 'Qualcosa non ha funzionato: riprova.';
            if (data.ok) form.hidden = true;
        } catch {
            status.textContent = 'Qualcosa non ha funzionato: riprova.';
        }
    });
    box.append(h('p', { class: 'hive-size-rail__empty-text', text: 'Lasciaci la tua email: ti scriviamo quando arriva un paio nella tua taglia.' }), form, status);
    return box;
}
