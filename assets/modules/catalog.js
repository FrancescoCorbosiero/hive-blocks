/**
 * @hive/catalog — the shop's pages (Hive\Shop\Catalog) without full
 * reloads, and the filters panel.
 *
 * Every filter option, chip and page link is a plain link to the filtered
 * URL; the sort and the price are GET forms. With this module they swap the
 * results in place: fetch the URL (the same page the CDN caches), take the
 * new results and filters out of it, and put the URL in the address bar.
 * Back and forward work the same way. Anything that goes wrong falls back
 * to a normal page load.
 */

const root = document.querySelector('[data-hive-catalog]');
if (root) setup(root);

function setup(root) {
    const panel = root.querySelector('[data-hive-filters]');
    const backdrop = root.querySelector('.hive-catalog__backdrop');
    const pages = new Map();
    let latest = null;

    /* The panel (phones, or desktop when the filters are a panel) -------------- */

    const toggle = () => root.querySelector('[data-hive-filters-open]');

    function openPanel() {
        if (!panel) return;
        panel.classList.add('is-open');
        if (backdrop) backdrop.hidden = false;
        toggle()?.setAttribute('aria-expanded', 'true');
        document.documentElement.classList.add('hive-filters-open');
        panel.querySelector('.hive-filters__close')?.focus();
    }

    function closePanel() {
        if (!panel?.classList.contains('is-open')) return;
        panel.classList.remove('is-open');
        if (backdrop) backdrop.hidden = true;
        document.documentElement.classList.remove('hive-filters-open');
        const button = toggle();
        button?.setAttribute('aria-expanded', 'false');
        button?.focus();
    }

    /* Navigation ---------------------------------------------------------------- */

    function load(url) {
        if (!pages.has(url)) {
            const request = fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } }).then((response) => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.text();
            });
            request.catch(() => pages.delete(url));
            pages.set(url, request);
        }
        return pages.get(url);
    }

    async function navigate(url, push = true) {
        const ticket = {};
        latest = ticket;
        root.setAttribute('aria-busy', 'true');
        try {
            const html = await load(url);
            if (latest !== ticket) return;
            const next = new DOMParser().parseFromString(html, 'text/html');
            const fresh = next.querySelector('[data-hive-catalog]');
            if (!fresh) throw new Error('No catalog in the page');
            for (const part of ['[data-hive-catalog-results]', '[data-hive-filters-body]', '[data-hive-filters-foot]']) {
                const here = root.querySelector(part);
                const there = fresh.querySelector(part);
                if (here && there) here.replaceWith(document.adoptNode(there));
            }
            document.title = next.title;
            if (push) history.pushState({ hiveCatalog: true }, '', url);
            prepare();
            if (panel?.classList.contains('is-open')) return;
            const results = root.querySelector('[data-hive-catalog-results]');
            if (results && results.getBoundingClientRect().top < 0) results.scrollIntoView({ block: 'start' });
        } catch {
            if (latest === ticket) location.assign(url);
        } finally {
            if (latest === ticket) root.removeAttribute('aria-busy');
        }
    }

    /** A GET form's URL, keeping its action's own parameters (?post_type=product). */
    function formUrl(form, skip = () => false) {
        const url = new URL(form.action, location.href);
        for (const [name, value] of new FormData(form)) {
            if (typeof value !== 'string' || value === '' || skip(name, value)) url.searchParams.delete(name);
            else url.searchParams.set(name, value);
        }
        return url.href;
    }

    /** The price form's URL: a bound left at its end isn't a filter. */
    function priceUrl(form) {
        const { floor, ceil } = form.dataset;
        return formUrl(form, (name, value) => (name === 'min_price' && value === floor) || (name === 'max_price' && value === ceil));
    }

    /* Prices: the two handles and the two fields move together ------------------ */

    function syncPrice(form, source) {
        const [low, high] = form.querySelectorAll('.hive-price__range');
        const fields = { min: form.querySelector('[data-hive-price-field="min"]'), max: form.querySelector('[data-hive-price-field="max"]') };
        if (!low || !high) return;
        if (source?.dataset.hivePriceField) {
            (source.dataset.hivePriceField === 'min' ? low : high).value = source.value;
        }
        if (Number(low.value) > Number(high.value)) {
            if (source === low || source === fields.min) high.value = low.value;
            else low.value = high.value;
        }
        if (fields.min) fields.min.value = low.value;
        if (fields.max) fields.max.value = high.value;
        const span = Number(form.dataset.ceil) - Number(form.dataset.floor) || 1;
        const at = (input) => `${((Number(input.value) - Number(form.dataset.floor)) / span) * 100}%`;
        form.style.setProperty('--hive-price-from', at(low));
        form.style.setProperty('--hive-price-to', at(high));
    }

    function prepare() {
        root.querySelectorAll('[data-hive-price]').forEach((form) => syncPrice(form));
    }

    /* Events ---------------------------------------------------------------------- */

    root.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        if (target.closest('[data-hive-filters-open]')) return openPanel();
        if (target.closest('[data-hive-filters-close]')) return closePanel();

        const more = target.closest('[data-hive-facet-more]');
        if (more) {
            const facet = more.closest('[data-hive-facet]');
            const all = facet.classList.toggle('is-all');
            more.setAttribute('aria-expanded', String(all));
            more.dataset.more ??= more.textContent;
            more.textContent = all ? more.dataset.less : more.dataset.more;
            return;
        }

        const link = target.closest('a[data-hive-nav]');
        if (link && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey) {
            event.preventDefault();
            navigate(link.href);
        }
    });

    root.addEventListener('change', (event) => {
        const target = event.target;
        if (target.matches?.('[data-hive-sort]')) navigate(formUrl(target.form));
        else if (target.matches?.('.hive-price__range, [data-hive-price-field]')) navigate(priceUrl(target.form));
    });

    root.addEventListener('submit', (event) => {
        const form = event.target;
        if (form.matches('[data-hive-sort-form]')) {
            event.preventDefault();
            navigate(formUrl(form));
        } else if (form.matches('[data-hive-price]')) {
            event.preventDefault();
            navigate(priceUrl(form));
        }
    });

    root.addEventListener('input', (event) => {
        const target = event.target;
        if (target.matches?.('.hive-price__range, [data-hive-price-field]')) {
            syncPrice(target.form, target);
            return;
        }
        if (target.matches?.('[data-hive-facet-search]')) {
            const facet = target.closest('[data-hive-facet]');
            const query = target.value.trim().toLowerCase();
            facet.classList.toggle('is-searching', query !== '');
            for (const item of facet.querySelectorAll('.hive-facet__options > li')) {
                item.classList.toggle('is-hidden', query !== '' && !item.textContent.toLowerCase().includes(query));
            }
        }
    });

    // Hovering a link for a moment fetches its page, so the click lands at once.
    let hover = 0;
    root.addEventListener(
        'pointerover',
        (event) => {
            const link = event.target instanceof Element ? event.target.closest('a[data-hive-nav]') : null;
            clearTimeout(hover);
            if (link) hover = setTimeout(() => load(link.href).catch(() => {}), 120);
        },
        { passive: true }
    );

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closePanel();
    });

    window.addEventListener('popstate', () => navigate(location.href, false));
    history.replaceState({ hiveCatalog: true }, '', location.href);
    prepare();
}
