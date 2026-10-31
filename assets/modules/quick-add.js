/**
 * @hive/quick-add — "Aggiungi al carrello" on a card of a product with
 * sizes: a sheet (a dialog on desktop) with the product, the size choice,
 * the quantity and the add button (@hive/buy). It closes itself a moment
 * after the add. Loaded by @hive/cards on the first click.
 */
import { getProduct } from '@hive/shop';
import { dialog, preload } from '@hive/dialog';
import { h } from '@hive/dom';
import { buyBox } from '@hive/buy';

const sheet = dialog('quick-add', { label: 'Scegli la taglia', sheet: true });
let latest = 0;
let closing = 0;

export function prefetch(id) {
    preload();
    getProduct(id).catch(() => {});
}

/**
 * @param {number} id
 * @param {{size?: number|null, trigger?: Element|null}} options size: a variation to start from.
 */
export async function open(id, { size = null, trigger = null } = {}) {
    clearTimeout(closing);
    const request = ++latest;
    sheet.body.replaceChildren(h('p', { class: 'hive-dialog__message', text: 'Caricamento…' }));
    await sheet.open(trigger);
    try {
        const product = await getProduct(id);
        if (request === latest && sheet.isOpen) sheet.body.replaceChildren(render(product, size));
    } catch {
        if (request === latest) sheet.body.replaceChildren(h('p', { class: 'hive-dialog__message', text: 'Non è stato possibile caricare il prodotto. Riprova.' }));
    }
}

function render(product, size) {
    const image = product.images?.[0];
    const thumb = h('img', { class: 'hive-qa__image', src: image?.thumb || image?.src || '', alt: '' });
    const price = h('div', { class: 'hive-qa__price', html: product.price_html });

    const box = buyBox(product, {
        size,
        quantity: true,
        onVariation: (variation) => {
            price.innerHTML = variation?.price_html || product.price_html;
            thumb.src = variation?.image || image?.thumb || image?.src || '';
        },
        onAdded: () => {
            closing = setTimeout(() => sheet.close(), 1200);
        },
    });

    return h(
        'div',
        { class: 'hive-qa' },
        h(
            'div',
            { class: 'hive-qa__head' },
            thumb,
            h('div', { class: 'hive-qa__about' }, h('h2', { class: 'hive-qa__title', text: product.title }), price)
        ),
        box
    );
}
