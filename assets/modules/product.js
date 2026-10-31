/**
 * @hive/product — the product page (Hive\Shop\ProductPage):
 *
 *   the photos (@hive/gallery)
 *   the size buttons: they set WooCommerce's own <select>s, and WooCommerce's
 *   variation script (wc-add-to-cart-variation) does the rest; its events
 *   bring the picked size's price to the top, its photo to the gallery and
 *   its stock under the buttons
 *   add to cart without leaving the page: the form, as it is, to
 *   wc-ajax=hive_add_to_cart (WooCommerce's own form handler adds it), then
 *   the mini cart refreshes. A form that can't go that way posts normally
 *   the sticky bar, once the add-to-cart button has scrolled away
 *   the size guide, in a dialog (@hive/dialog, loaded on first use)
 *   "Avvisami" for the sold-out size picked (or the sold-out product), to
 *   wc-ajax=hive_alerts
 *   "Chiedi su WhatsApp" naming the size picked; "Condividi"
 *   the delivery estimate (@hive/delivery), at the visitor's "now"
 *   "Visti di recente": this browser's own products, remembered here and
 *   fetched as a rail when the place nears the screen
 */
import { h, moduleData } from '@hive/dom';
import { cartUpdated } from '@hive/shop';
import { gallery } from '@hive/gallery';

const config = moduleData('@hive/product');
const ADDED = '✓ Aggiunto';
const smooth = () => (window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth');

function init(root) {
    const gallerySlot = root.querySelector('[data-hive-gallery]');
    const photos = gallerySlot ? gallery(gallerySlot) : null;
    const form = root.querySelector('form[data-hive-buy]');
    const priceBox = root.querySelector('[data-hive-price]');
    const basePrice = priceBox ? priceBox.innerHTML : '';
    const bar = stickyBar(root, form);
    const wait = waitForm(root.querySelector('[data-hive-wait]'));
    const delivery = deliveryEstimate(root.querySelector('[data-hive-delivery]'));

    if (form) {
        buyForm(form, {
            onVariation(variation, buyable) {
                if (priceBox) priceBox.innerHTML = variation?.price_html || basePrice;
                photos?.showImage(variation);
                bar.update(variation, buyable, variation?.price_html || basePrice);
                wait.show(variation && !variation.is_in_stock ? pickedSwatch(form) : null);
                delivery.available(!variation || Boolean(variation.is_in_stock));
            },
            onAdded: () => bar.added(),
        });
    }

    sizeGuides(root);
    actions(root.querySelector('[data-hive-actions]'), form);
    recentlyViewed(root);

    // "4,5 su 5 · 12 recensioni" opens the reviews it links to.
    root.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('[data-hive-open-details]') : null;
        const details = link ? document.getElementById(link.dataset.hiveOpenDetails) : null;
        if (details) details.open = true;
    });

    stickySummary(root);
}

/**
 * The buying part: size buttons over WooCommerce's selects, the quantity
 * stepper, and adding to the cart without a page load.
 */
function buyForm(form, { onVariation, onAdded }) {
    const rows = [...form.querySelectorAll('[data-hive-swatches]')];
    const add = form.querySelector('[data-hive-buy-add]');
    const status = form.querySelector('[data-hive-buy-status]');
    const variable = form.classList.contains('variations_form');
    const label = add ? add.textContent : '';
    let picked = null;
    let buyable = !variable;
    let busy = false;
    let added = 0;

    const say = (text, { error = false, link = null } = {}) => {
        if (!status) return;
        status.replaceChildren(text);
        if (link) status.append(' ', h('a', { href: link.href, text: link.text }));
        status.classList.toggle('is-error', error);
    };

    /** The buttons follow the selects: picked, and still possible with the other picks. */
    const sync = () => {
        for (const row of rows) {
            const select = row.querySelector('select');
            if (!select) continue;
            const options = new Map([...select.options].map((option) => [option.value, option]));
            let pickedLabel = '';
            for (const button of row.querySelectorAll('.hive-swatch')) {
                const option = options.get(button.dataset.value);
                const on = select.value !== '' && select.value === button.dataset.value;
                button.setAttribute('aria-pressed', String(on));
                // WooCommerce drops or disables the options the other picks rule out.
                button.disabled = !option || option.disabled;
                if (on) pickedLabel = button.querySelector('.hive-swatch__value')?.textContent || '';
            }
            const shown = row.querySelector('[data-hive-swatches-picked]');
            if (shown) shown.textContent = pickedLabel ? `: ${pickedLabel}` : '';
        }
    };

    const missing = () => rows.filter((row) => !row.querySelector('select')?.value);

    const nudge = (left) => {
        for (const row of left) {
            row.classList.remove('is-invalid');
            void row.offsetWidth; // restart the animation
            row.classList.add('is-invalid');
        }
        left[0]?.querySelector('.hive-swatch:not(:disabled)')?.focus();
        const names = left.map((row) => row.dataset.label || '').filter(Boolean);
        say(left.length === 1 && left[0].dataset.size !== undefined ? 'Scegli la taglia.' : `Scegli: ${names.join(', ')}.`, { error: true });
    };

    form.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;

        const swatch = target?.closest('.hive-swatch');
        if (swatch && !swatch.disabled) {
            const row = swatch.closest('[data-hive-swatches]');
            const select = row?.querySelector('select');
            if (!select) return;
            // Picking the picked option again unpicks it, when there is more than one choice to make.
            const value = select.value === swatch.dataset.value ? (rows.length > 1 ? '' : select.value) : swatch.dataset.value;
            if (value === select.value) return;
            select.value = value;
            row.classList.remove('is-invalid');
            say('');
            // WooCommerce's variation script listens to the select's change.
            select.dispatchEvent(new Event('change', { bubbles: true }));
            sync();
            return;
        }

        const step = target?.closest('[data-hive-qty]');
        if (step) {
            const input = step.parentElement?.querySelector('input.qty');
            if (!input) return;
            const min = Number(input.min) || 1;
            const max = Number(input.max) > 0 ? Number(input.max) : Infinity;
            const by = Number(input.step) || 1;
            input.value = String(Math.max(min, Math.min(max, (Number(input.value) || min) + Number(step.dataset.hiveQty) * by)));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    // Before WooCommerce's own handler (which would show an alert): say what's missing on the page.
    form.addEventListener(
        'click',
        (event) => {
            const button = event.target instanceof Element ? event.target.closest('.single_add_to_cart_button') : null;
            if (!button || !variable) return;
            const left = missing();
            if (left.length) {
                event.preventDefault();
                event.stopPropagation();
                nudge(left);
            } else if (picked && !buyable) {
                event.preventDefault();
                event.stopPropagation();
                say('Questa taglia è esaurita.', { error: true });
            }
        },
        true
    );

    form.addEventListener(
        'submit',
        async (event) => {
            if (!config.ajax || form.dataset.hiveNative) return;
            const left = variable ? missing() : [];
            if (left.length || (variable && !buyable)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                if (left.length) nudge(left);
                else say('Questa taglia è esaurita.', { error: true });
                return;
            }
            // Hive adds it; nothing else should add it a second time.
            event.preventDefault();
            event.stopImmediatePropagation();
            if (busy) return;
            busy = true;

            const submitter = event.submitter || add;
            let body;
            try {
                body = new FormData(form, submitter);
            } catch {
                body = new FormData(form);
            }
            if (!body.has('add-to-cart')) {
                const named = form.querySelector('[name="add-to-cart"]');
                if (named && named.value) body.set('add-to-cart', named.value);
            }

            add?.classList.add('is-busy');
            add?.setAttribute('aria-busy', 'true');
            if (add) add.textContent = 'Aggiunta…';
            say('');
            try {
                const response = await fetch(config.ajax, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
                // WooCommerce (or a plugin) sends shoppers somewhere after adding: follow.
                if (response.redirected) {
                    location.assign(response.url);
                    return;
                }
                const data = await response.json();
                if (data.error) {
                    say((data.messages || []).join(' ') || 'Non è stato possibile aggiungerlo al carrello.', { error: true });
                    restore();
                    return;
                }
                cartUpdated(data, add, { form: true });
                // Ready for the next one at once; "✓ Aggiunto" stays a moment, unless another add starts.
                restore();
                if (add) add.textContent = ADDED;
                say('Aggiunto al carrello.', { link: config.cartUrl ? { href: config.cartUrl, text: 'Vai al carrello' } : null });
                onAdded();
                const shown = ++added;
                setTimeout(() => {
                    if (shown === added && !busy) restore();
                }, 2000);
            } catch {
                // Something in between failed: post the form the ordinary way.
                restore();
                form.dataset.hiveNative = '1';
                form.requestSubmit(submitter && submitter.form === form ? submitter : null);
            }
        },
        true
    );

    function restore() {
        busy = false;
        add?.classList.remove('is-busy');
        add?.removeAttribute('aria-busy');
        if (add) add.textContent = picked && !buyable ? unavailableLabel(picked) : label;
    }

    // WooCommerce's variation events are jQuery's: listen once jQuery is there (a deferred jQuery
    // arrives after this module; DOMContentLoaded still comes before WooCommerce sets the form up).
    const listen = () => {
        const $ = window.jQuery;
        if (!variable || !$) return;
        $(form)
            .on('found_variation.hive', (event, variation) => {
                picked = variation;
                buyable = Boolean(variation.is_in_stock && variation.is_purchasable && variation.variation_is_visible);
                sync();
                if (add) {
                    add.textContent = buyable ? label : unavailableLabel(variation);
                    add.classList.toggle('is-unavailable', !buyable);
                    // WooCommerce enables the button 300 ms later: a click before then shouldn't be refused.
                    if (buyable) add.classList.remove('disabled', 'wc-variation-selection-needed', 'wc-variation-is-unavailable');
                }
                say('');
                onVariation(variation, buyable);
            })
            .on('reset_data.hive', () => {
                picked = null;
                buyable = false;
                sync();
                if (add) {
                    add.textContent = label;
                    add.classList.remove('is-unavailable');
                }
                onVariation(null, false);
            })
            .on('woocommerce_update_variation_values.hive', sync);
    };
    if (window.jQuery) listen();
    else document.addEventListener('DOMContentLoaded', listen, { once: true });
    sync();

    return {
        get picked() {
            return picked;
        },
        get buyable() {
            return buyable;
        },
        nudge: () => nudge(missing()),
    };
}

function unavailableLabel(variation) {
    return variation && !variation.is_in_stock ? 'Esaurito' : 'Non disponibile';
}

/**
 * The bar at the bottom once the add-to-cart button has scrolled away,
 * gone again at the end of the product's part of the page.
 */
function stickyBar(root, form) {
    const bar = root.querySelector('[data-hive-sticky]');
    const add = form?.querySelector('[data-hive-buy-add]');
    const none = { update() {}, added() {} };
    if (!bar || !add) return none;

    const button = bar.querySelector('[data-hive-sticky-add]');
    const price = bar.querySelector('[data-hive-sticky-price]');
    const variable = form.classList.contains('variations_form');
    const choose = button.textContent;
    let picked = null;
    let buyable = !variable;
    let past = false;
    let end = false;

    bar.hidden = false;
    bar.inert = true;
    const show = () => {
        const on = past && !end;
        bar.classList.toggle('is-visible', on);
        bar.inert = !on;
        // Why it shows or not, for whoever looks at the page.
        bar.dataset.state = `${past ? 'past' : 'before'}${end ? '-end' : ''}`;
    };

    // Where the button and the end of the product's part are, read once a frame while scrolling.
    // (Not an IntersectionObserver: a jump from below the button to past it never "intersects",
    // after an anchor link or a restored scroll position say, and the bar would never show.)
    const sentinel = h('div', { class: 'hive-product__end', 'aria-hidden': 'true' });
    root.append(sentinel);
    let frame = 0;
    const measure = () => {
        frame = 0;
        past = add.getBoundingClientRect().bottom < 0;
        end = sentinel.getBoundingClientRect().top < window.innerHeight;
        show();
    };
    const soon = () => {
        frame ||= requestAnimationFrame(measure);
    };
    window.addEventListener('scroll', soon, { passive: true });
    window.addEventListener('resize', soon, { passive: true });
    measure();

    button.addEventListener('click', () => {
        if (variable && (!picked || !buyable)) {
            form.scrollIntoView({ behavior: smooth(), block: 'center' });
            if (!picked) {
                // The same nudge as the form's own button.
                form.querySelector('.single_add_to_cart_button')?.click();
            }
            return;
        }
        form.requestSubmit(add);
    });

    return {
        update(variation, canBuy, priceHtml) {
            picked = variation;
            buyable = variable ? canBuy : true;
            if (price && priceHtml) price.innerHTML = priceHtml;
            button.textContent = !variable ? choose : !variation ? choose : canBuy ? 'Aggiungi al carrello' : unavailableLabel(variation);
        },
        added() {
            const before = button.textContent;
            button.textContent = ADDED;
            setTimeout(() => (button.textContent = before), 2000);
        },
    };
}

/** "Guida alle taglie": the guide's content (a <template> in the page) in a dialog. */
function sizeGuides(root) {
    const buttons = root.querySelectorAll('[data-hive-size-guide]');
    if (!buttons.length) return;
    const warm = () => import('@hive/dialog').then(({ preload }) => preload()).catch(() => {});
    buttons.forEach((button) => {
        button.addEventListener('pointerover', warm, { once: true, passive: true });
        button.addEventListener('click', async () => {
            const template = document.getElementById(`hive-size-guide-${button.dataset.hiveSizeGuide}`);
            if (!(template instanceof HTMLTemplateElement)) return;
            const { dialog } = await import('@hive/dialog');
            const title = template.dataset.title || 'Guida alle taglie';
            const view = dialog('size-guide', { label: title });
            view.body.replaceChildren(
                h('div', { class: 'hive-guide' }, h('h2', { class: 'hive-guide__title', text: title }), h('div', { class: 'hive-guide__body' }, template.content.cloneNode(true)))
            );
            view.open(button);
        });
    });
}

/** Photos in a grid: the summary stays in view beside them, when it fits the screen. */
function stickySummary(root) {
    const summary = root.querySelector('[data-hive-product-summary]');
    if (!summary || !root.classList.contains('hive-product--grid') || !('ResizeObserver' in window)) return;
    const fit = () => summary.classList.toggle('is-sticky', summary.offsetHeight + 48 < window.innerHeight);
    new ResizeObserver(fit).observe(summary);
    window.addEventListener('resize', fit, { passive: true });
}

/** The size button picked in the form: its label and the waitlist's key. */
function pickedSwatch(form) {
    const button = form.querySelector('[data-size] .hive-swatch[aria-pressed="true"]') || form.querySelector('.hive-swatch[aria-pressed="true"]');
    return button ? { label: button.querySelector('.hive-swatch__value')?.textContent || '', key: button.dataset.key || '*' } : { label: '', key: '*' };
}

/** "Avvisami": shown for a sold-out size (or a sold-out product), posted without leaving the page. */
function waitForm(form) {
    if (!form) return { show() {} };
    const status = form.querySelector('[data-hive-wait-status]');
    const sizeName = form.querySelector('[data-hive-wait-size]');
    const keyField = form.querySelector('[data-hive-wait-key]');
    const button = form.querySelector('button[type="submit"]');
    const say = (text, error = false) => {
        status.textContent = text;
        status.classList.toggle('is-error', error);
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const email = form.elements.email;
        if (!email.value || !email.checkValidity()) {
            say('Scrivi un indirizzo email valido.', true);
            email.focus();
            return;
        }
        if (!config.alerts) return;
        button.disabled = true;
        say('Un attimo…');
        try {
            const response = await fetch(config.alerts, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json();
            say(data.message || (data.ok ? 'Fatto.' : 'Qualcosa non ha funzionato: riprova.'), !data.ok);
            if (data.ok) form.classList.add('is-done');
        } catch {
            say('Qualcosa non ha funzionato: riprova.', true);
        } finally {
            button.disabled = false;
        }
    });

    return {
        /** @param {{label: string, key: string}|null} size The sold-out size picked, or null to hide. */
        show(size) {
            // A sold-out product's form is always there; a size's follows the pick.
            if (!sizeName) return;
            form.hidden = !size;
            if (!size) return;
            if (sizeName) sizeName.textContent = size.label;
            if (keyField) keyField.value = size.key || '*';
            form.classList.remove('is-done');
            say('');
        },
    };
}

/** The delivery estimate, loaded on its own when the page has one. */
function deliveryEstimate(box) {
    let available = true;
    let estimate = null;
    if (box) {
        import('@hive/delivery').then(({ deliveryBox }) => {
            estimate = deliveryBox(box);
            if (estimate) estimate.available = available;
        });
    }
    return {
        available(on) {
            available = on;
            if (estimate) estimate.available = on;
        },
    };
}

/** "Chiedi su WhatsApp" names the size picked by the time it's clicked; "Condividi" shows where it can work. */
function actions(row, form) {
    if (!row) return;
    const name = row.dataset.name || document.title;
    const url = row.dataset.url || location.href;
    const whatsapp = row.querySelector('[data-hive-whatsapp]');
    whatsapp?.addEventListener('click', () => {
        const size = form ? pickedSwatch(form).label : '';
        const text = `Ciao! Vorrei informazioni su ${name}${size ? `, taglia ${size}` : ''}: ${url}`;
        whatsapp.href = `https://wa.me/${whatsapp.dataset.hiveWhatsapp}?text=${encodeURIComponent(text)}`;
    });

    const share = row.querySelector('[data-hive-share]');
    if (!share || !(navigator.share || (navigator.clipboard && window.isSecureContext))) return;
    share.hidden = false;
    const label = share.querySelector('span');
    share.addEventListener('click', async () => {
        if (navigator.share) {
            navigator.share({ title: name, url }).catch(() => {});
            return;
        }
        try {
            await navigator.clipboard.writeText(url);
            label.textContent = 'Link copiato';
            setTimeout(() => (label.textContent = 'Condividi'), 2000);
        } catch {
            // Nothing to say: the button stays as it was.
        }
    });
}

const RECENT = 'hive_recent';
const RECENT_MAX = 12;

/**
 * "Visti di recente": this product goes first in this browser's list; the
 * others come as a rail from Hive's REST read once the place nears the
 * screen. Nothing leaves the browser but product ids.
 */
function recentlyViewed(root) {
    const slot = root.querySelector('[data-hive-recent]');
    const id = Number(root.dataset.hiveProduct);
    let seen = [];
    try {
        seen = JSON.parse(localStorage.getItem(RECENT) || '[]').filter((n) => Number.isInteger(n) && n > 0);
    } catch {
        seen = [];
    }
    const others = seen.filter((n) => n !== id).slice(0, RECENT_MAX);
    try {
        localStorage.setItem(RECENT, JSON.stringify([id, ...others].slice(0, RECENT_MAX + 1)));
    } catch {
        // Storage off (private mode): the rail just stays empty.
    }
    if (!slot || !others.length || !('IntersectionObserver' in window)) return;

    const load = async () => {
        const api = moduleData('@hive/shop').api || '/wp-json/hive/v1/';
        const request = new URL(`${api}recent`, location.href);
        // Sorted, so every visitor with the same products asks the same URL (the CDN keeps one copy).
        request.searchParams.set('ids', [...others].sort((a, b) => a - b).join(','));
        if (slot.dataset.v) request.searchParams.set('v', slot.dataset.v);
        try {
            const response = await fetch(request, { credentials: 'omit', headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!data.html) return;
            const template = document.createElement('template');
            template.innerHTML = data.html;
            const rail = template.content.querySelector('.hive-rail');
            if (!rail) return;
            // The newest first, whatever order the rail came in.
            const track = rail.querySelector('.hive-rail__track');
            const cards = new Map([...track.children].map((card) => [Number(card.dataset.productId), card]));
            track.replaceChildren(...others.map((n) => cards.get(n)).filter(Boolean));
            slot.replaceChildren(rail);
            import('@hive/rail').then(({ setup }) => setup(rail));
        } catch {
            // No rail: the page is complete without it.
        }
    };
    const near = new IntersectionObserver(
        (entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) return;
            near.disconnect();
            load();
        },
        { rootMargin: '600px 0px' }
    );
    near.observe(slot);
}

// Last, once every constant above is set.
for (const root of document.querySelectorAll('[data-hive-product]')) {
    init(root);
}
