/**
 * @hive/search — the live search (module "Ricerca istantanea"): a dialog
 * with a search field; products appear as you type, from Hive's cacheable
 * read (GET hive/v1/search?q=…&v=…, Hive\Shop\Search). Enter goes to
 * WooCommerce's search results; the popular searches (the block's) and the
 * visitor's own last ones (this browser's) are a tap away.
 *
 * Opened by the "Cerca" block ([data-hive-search]) and by a theme's own
 * search form when the hive_search_trigger_selector filter names it.
 */
import { h, moduleData } from '@hive/dom';

const config = moduleData('@hive/search');
const RECENT = 'hive_searches';
const MIN = 2;
let view = null;
let ui = null;

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const trigger = target?.closest('[data-hive-search]') || (config.selector && target?.closest(config.selector));
    if (!trigger) return;
    event.preventDefault();
    open(trigger);
});

// A theme's search field opens the dialog when it's focused, with what was typed so far.
if (config.selector) {
    document.addEventListener('focusin', (event) => {
        const field = event.target instanceof HTMLInputElement && event.target.closest(config.selector) ? event.target : null;
        if (field) {
            field.blur();
            open(field.closest(config.selector), field.value);
        }
    });
}

// The dialog's code and CSS ahead of the click.
document.addEventListener(
    'pointerover',
    (event) => {
        if (event.target instanceof Element && event.target.closest('[data-hive-search]')) {
            import('@hive/dialog').then(({ preload }) => preload()).catch(() => {});
        }
    },
    { passive: true, once: true }
);

/** Opens the search, with the trigger's words and popular searches. */
export async function open(trigger = null, text = '') {
    const { dialog } = await import('@hive/dialog');
    view ??= dialog('search', { label: 'Cerca prodotti' });
    ui ??= build();
    const popular = (trigger?.dataset?.popular || '').split('|').filter(Boolean);
    ui.setPopular(popular.length ? popular : config.popular || []);
    ui.input.placeholder = trigger?.dataset?.placeholder || 'Cerca sneakers, marchi, modelli…';
    if (!view.body.contains(ui.node)) view.body.replaceChildren(ui.node);
    await view.open(trigger);
    if (text) ui.input.value = text;
    ui.input.focus();
    ui.input.select();
    ui.search(ui.input.value);
}

function build() {
    const input = h('input', {
        class: 'hive-search__input',
        type: 'search',
        name: 's',
        autocomplete: 'off',
        enterkeyhint: 'search',
        'aria-label': 'Cerca prodotti',
        'aria-controls': 'hive-search-results',
    });
    const form = h(
        'form',
        { class: 'hive-search__form', role: 'search', method: 'get', action: config.home || '/' },
        h('input', { type: 'hidden', name: 'post_type', value: 'product' }),
        input,
        h('button', { type: 'submit', class: 'hive-search__go', text: 'Cerca' })
    );
    const status = h('p', { class: 'hive-search__status', role: 'status' });
    const list = h('ul', { class: 'hive-search__results', id: 'hive-search-results', role: 'list' });
    const all = h('a', { class: 'hive-search__all', hidden: true });
    const suggestions = h('div', { class: 'hive-search__suggestions' });
    const node = h('div', { class: 'hive-search' }, form, status, list, all, suggestions);

    let timer = 0;
    let controller = null;
    const cache = new Map();

    const remember = (q) => {
        try {
            const last = JSON.parse(localStorage.getItem(RECENT) || '[]').filter((term) => typeof term === 'string' && term.toLowerCase() !== q.toLowerCase());
            localStorage.setItem(RECENT, JSON.stringify([q, ...last].slice(0, 5)));
        } catch {
            // No storage: nothing remembered.
        }
    };
    const recent = () => {
        try {
            return JSON.parse(localStorage.getItem(RECENT) || '[]').filter((term) => typeof term === 'string');
        } catch {
            return [];
        }
    };

    let popular = [];
    const chips = (title, terms) =>
        terms.length
            ? h('div', { class: 'hive-search__group' }, h('p', { class: 'hive-search__group-title', text: title }), h('div', { class: 'hive-search__chips' }, terms.map((term) => h('button', { type: 'button', class: 'hive-search__chip', text: term, onclick: () => pick(term) }))))
            : null;
    const showSuggestions = () => {
        suggestions.replaceChildren(...[chips('Ricerche recenti', recent()), chips('Ricerche popolari', popular)].filter(Boolean));
        suggestions.hidden = !suggestions.childElementCount;
    };
    const pick = (term) => {
        input.value = term;
        search(term);
        input.focus();
    };

    async function search(raw) {
        const q = raw.trim();
        clearTimeout(timer);
        controller?.abort();
        if (q.length < MIN) {
            list.replaceChildren();
            all.hidden = true;
            status.textContent = '';
            showSuggestions();
            return;
        }
        suggestions.hidden = true;
        if (cache.has(q)) {
            render(q, cache.get(q));
            return;
        }
        timer = setTimeout(async () => {
            controller = new AbortController();
            const url = new URL(`${config.api || '/wp-json/hive/v1/'}search`, location.href);
            url.searchParams.set('q', q);
            if (config.v !== undefined) url.searchParams.set('v', String(config.v));
            status.textContent = 'Cerco…';
            try {
                const response = await fetch(url, { signal: controller.signal, credentials: 'omit', headers: { Accept: 'application/json' } });
                const data = await response.json();
                cache.set(q, data);
                if (input.value.trim() === q) render(q, data);
            } catch (error) {
                if (error.name !== 'AbortError') status.textContent = 'La ricerca non risponde: premi Invio per cercare comunque.';
            }
        }, 200);
    }

    function render(q, data) {
        const items = Array.isArray(data.items) ? data.items : [];
        status.textContent = items.length ? `${data.total} ${data.total === 1 ? 'prodotto' : 'prodotti'} per «${q}»` : `Nessun prodotto per «${q}».`;
        list.replaceChildren(
            ...items.map((item) =>
                h(
                    'li',
                    {},
                    h(
                        'a',
                        { class: 'hive-search__result', href: item.url, onclick: () => remember(q) },
                        h('img', { class: 'hive-search__image', src: item.image, alt: '', width: 64, height: 64, loading: 'lazy', decoding: 'async' }),
                        h(
                            'span',
                            { class: 'hive-search__text' },
                            item.brand ? h('span', { class: 'hive-search__brand', text: item.brand }) : null,
                            h('span', { class: 'hive-search__name', text: item.name }),
                            h('span', { class: 'hive-search__price', html: item.price_html })
                        )
                    )
                )
            )
        );
        all.hidden = !items.length || data.total <= items.length;
        if (!all.hidden) {
            all.href = data.url;
            all.textContent = `Vedi tutti i risultati (${data.total})`;
        }
    }

    input.addEventListener('input', () => search(input.value));
    form.addEventListener('submit', (event) => {
        const q = input.value.trim();
        if (!q) {
            event.preventDefault();
            return;
        }
        remember(q);
    });
    // Arrow keys from the field into the results and back.
    node.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
        const links = [...list.querySelectorAll('.hive-search__result')];
        if (!links.length) return;
        const at = links.indexOf(document.activeElement);
        event.preventDefault();
        if (event.key === 'ArrowDown') links[Math.min(links.length - 1, at + 1)].focus();
        else if (at <= 0) input.focus();
        else links[at - 1].focus();
    });

    return {
        node,
        input,
        search,
        setPopular(terms) {
            popular = terms;
            showSuggestions();
        },
    };
}
