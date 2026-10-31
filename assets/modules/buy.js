/**
 * @hive/buy — the buying part of Quick View and Quick Add: the size picker
 * (variable products), an optional quantity, the add-to-cart button with
 * the picked size's price, and what happened ("Aggiunto al carrello · Vai al
 * carrello", or why not). What the product allows comes from its state (see
 * Hive\Shop\Card::state()): sold out and not-yet-released products get a
 * disabled button, other types a link to their page.
 */
import { addToCart, settings } from '@hive/shop';
import { h } from '@hive/dom';
import { picker } from '@hive/picker';

const ADD = 'Aggiungi al carrello';

/**
 * @param {Object} product @hive/shop getProduct() data.
 * @param {{size?: number|null, quantity?: boolean, onVariation?: Function, onAdded?: Function}} options
 *   size         variation to start from
 *   quantity     show the quantity stepper
 *   onVariation  (variation|null) when the pick changes: show its price and image
 *   onAdded      () after a successful add
 * @return {HTMLElement}
 */
export function buyBox(product, { size = null, quantity = false, onVariation = () => {}, onAdded = () => {} } = {}) {
    const box = h('div', { class: 'hive-buy' });
    const { kind, label } = product.state || { kind: 'link', label: 'Vedi prodotto' };

    if (kind === 'link') {
        box.append(h('a', { class: 'hive-button hive-buy__add', href: product.url, text: label }));
        return box;
    }
    if (kind !== 'simple' && kind !== 'variable') {
        box.append(h('button', { type: 'button', class: 'hive-button hive-buy__add', disabled: true, text: label }));
        return box;
    }

    const status = h('p', { class: 'hive-buy__status', role: 'status' });
    const button = h('button', { type: 'button', class: 'hive-button hive-buy__add', text: ADD });
    let pick = null;
    let count = null;

    if (kind === 'variable') {
        pick = picker({
            choices: product.choices || [],
            variations: product.variations || [],
            preselect: size,
            onChange: (variation) => {
                say('');
                sync(variation);
            },
        });
        box.append(pick.node);
    }
    if (quantity) {
        count = stepper();
        box.append(count.node);
    }
    box.append(button, status);

    function sync(variation) {
        button.textContent = variation?.price_text ? `${ADD} – ${variation.price_text}` : ADD;
        count?.limit(variation?.max_qty > 0 ? variation.max_qty : Infinity);
        onVariation(variation);
    }

    function say(text, { error = false, link = null } = {}) {
        status.replaceChildren(text);
        if (link) status.append(' ', h('a', { href: link.href, text: link.text }));
        status.classList.toggle('is-error', error);
    }

    button.addEventListener('click', async () => {
        const variation = pick ? pick.variation : null;
        if (pick && !variation) {
            say('Scegli una taglia.', { error: true });
            pick.nudge();
            return;
        }
        const id = variation ? variation.id : product.id;
        const before = button.textContent;
        button.disabled = true;
        button.textContent = 'Aggiunta…';
        say('');
        try {
            await addToCart(id, count ? count.value : 1, button);
            button.textContent = '✓ Aggiunto';
            say('Aggiunto al carrello.', { link: settings.cartUrl ? { href: settings.cartUrl, text: 'Vai al carrello' } : null });
            onAdded();
            setTimeout(() => {
                button.disabled = false;
                button.textContent = before;
            }, 1800);
        } catch (error) {
            button.disabled = false;
            button.textContent = before;
            if (error.productUrl) {
                say('Non è stato possibile aggiungerlo.', { error: true, link: { href: error.productUrl, text: 'Vedi il prodotto' } });
            } else {
                say('Qualcosa non ha funzionato: riprova.', { error: true });
            }
        }
    });

    if (pick?.variation) sync(pick.variation);
    return box;
}

/** − n + : a quantity between 1 and the variation's limit. */
function stepper() {
    let max = Infinity;
    const input = h('input', { class: 'hive-buy__qty-value', type: 'number', inputmode: 'numeric', min: '1', value: '1', 'aria-label': 'Quantità' });
    const clamp = (n) => Math.max(1, Math.min(max, Number.isFinite(n) ? Math.trunc(n) : 1));
    const set = (n) => {
        input.value = String(clamp(n));
    };
    input.addEventListener('change', () => set(Number(input.value)));
    const node = h(
        'div',
        { class: 'hive-buy__qty' },
        h('span', { class: 'hive-buy__qty-label', text: 'Quantità' }),
        h('button', { type: 'button', class: 'hive-buy__qty-step', 'aria-label': 'Diminuisci', text: '−', onclick: () => set(Number(input.value) - 1) }),
        input,
        h('button', { type: 'button', class: 'hive-buy__qty-step', 'aria-label': 'Aumenta', text: '+', onclick: () => set(Number(input.value) + 1) })
    );
    return {
        node,
        get value() {
            return clamp(Number(input.value));
        },
        limit(n) {
            max = n;
            input.max = Number.isFinite(n) ? String(n) : '';
            set(Number(input.value));
        },
    };
}
