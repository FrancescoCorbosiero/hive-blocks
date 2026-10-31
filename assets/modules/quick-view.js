/**
 * @hive/quick-view — a product in a dialog: photos, brand, name, price,
 * summary, details, the size choice and add to cart (@hive/buy), a link to
 * the full page, "Chiedi su WhatsApp" and "Condividi". Loaded by @hive/cards
 * the first time someone reaches for a card's quick view button.
 */
import { getProduct, settings } from '@hive/shop';
import { dialog, preload } from '@hive/dialog';
import { h, icon } from '@hive/dom';
import { buyBox } from '@hive/buy';

const view = dialog('quick-view', { label: 'Anteprima rapida' });
let latest = 0;

/** Gets the product's data and the dialog's CSS ahead of a click. */
export function prefetch(id) {
    preload();
    getProduct(id).catch(() => {});
}

/**
 * @param {number} id
 * @param {{size?: number|null, trigger?: Element|null}} options size: a variation to start from.
 */
export async function open(id, { size = null, trigger = null } = {}) {
    const request = ++latest;
    view.body.replaceChildren(h('p', { class: 'hive-dialog__message', text: 'Caricamento…' }));
    await view.open(trigger);
    try {
        const product = await getProduct(id);
        if (request === latest && view.isOpen) view.body.replaceChildren(render(product, size));
    } catch {
        if (request === latest) view.body.replaceChildren(h('p', { class: 'hive-dialog__message', text: 'Non è stato possibile caricare il prodotto. Riprova.' }));
    }
}

function render(product, size) {
    const images = product.images?.length ? product.images : [{ src: '', srcset: '', alt: '' }];
    const main = h('img', {
        class: 'hive-qv__image',
        src: images[0].src,
        srcset: images[0].srcset || false,
        sizes: '(max-width: 767px) 100vw, 460px',
        alt: images[0].alt || product.title,
        width: images[0].width || false,
        height: images[0].height || false,
    });
    const thumbs = images.map((image, index) =>
        h(
            'button',
            { type: 'button', class: 'hive-qv__thumb', 'aria-pressed': String(index === 0), 'aria-label': `Foto ${index + 1}` },
            h('img', { src: image.thumb || image.src, alt: '', loading: 'lazy' })
        )
    );
    thumbs.forEach((thumb, index) =>
        thumb.addEventListener('click', () => {
            const image = images[index];
            main.srcset = image.srcset || '';
            main.src = image.src;
            main.alt = image.alt || product.title;
            thumbs.forEach((other) => other.setAttribute('aria-pressed', String(other === thumb)));
        })
    );

    const price = h('div', { class: 'hive-qv__price', html: product.price_html });
    let picked = null;
    const box = buyBox(product, {
        size,
        onVariation: (variation) => {
            picked = variation;
            price.innerHTML = variation?.price_html || product.price_html;
        },
    });

    const eyebrow = product.brand || product.categories?.[0] || '';
    const details = product.attributes?.length
        ? h('dl', { class: 'hive-qv__details' }, product.attributes.map((row) => h('div', {}, h('dt', { text: row.label }), h('dd', { text: row.value }))))
        : null;

    return h(
        'article',
        { class: 'hive-qv' },
        h('div', { class: 'hive-qv__gallery' }, main, images.length > 1 ? h('div', { class: 'hive-qv__thumbs' }, thumbs) : null),
        h(
            'div',
            { class: 'hive-qv__info' },
            eyebrow ? h('p', { class: 'hive-qv__eyebrow', text: eyebrow }) : null,
            h('h2', { class: 'hive-qv__title', text: product.title }),
            price,
            product.summary ? h('div', { class: 'hive-qv__summary', html: product.summary }) : null,
            details,
            box,
            h('a', { class: 'hive-qv__more', href: product.url }, 'Vedi il prodotto completo', icon('m9.5 6 6 6-6 6')),
            actions(product, () => picked)
        )
    );
}

/** "Chiedi su WhatsApp" (naming the picked size) and "Condividi". */
function actions(product, pickedVariation) {
    const canShare = Boolean(navigator.share || (navigator.clipboard && window.isSecureContext));
    if (!settings.whatsapp && !canShare) return null;
    const row = h('div', { class: 'hive-qv__actions' });

    if (settings.whatsapp) {
        const link = h('a', { class: 'hive-qv__action', href: whatsapp(product, null), target: '_blank', rel: 'noopener noreferrer', text: 'Chiedi su WhatsApp' });
        // Written on click, so it names the size picked by then.
        link.addEventListener('click', () => {
            link.href = whatsapp(product, pickedVariation());
        });
        row.append(link);
    }
    if (canShare) {
        const button = h('button', { type: 'button', class: 'hive-qv__action', text: 'Condividi' });
        button.addEventListener('click', async () => {
            if (navigator.share) {
                navigator.share({ title: product.title, url: product.url }).catch(() => {});
                return;
            }
            await navigator.clipboard.writeText(product.url).catch(() => {});
            button.textContent = 'Link copiato';
            setTimeout(() => (button.textContent = 'Condividi'), 2000);
        });
        row.append(button);
    }
    return row;
}

function whatsapp(product, variation) {
    const size = variation ? sizeLabel(product, variation) : '';
    const text = `Ciao! Vorrei informazioni su ${product.title}${size ? `, taglia ${size}` : ''}: ${product.url}`;
    return `https://wa.me/${settings.whatsapp}?text=${encodeURIComponent(text)}`;
}

/** The picked options as people read them ("42", "36 2/3"). */
function sizeLabel(product, variation) {
    return (product.choices || [])
        .map((choice) => choice.options.find((option) => option.value === variation.attributes?.[choice.name])?.label)
        .filter(Boolean)
        .join(', ');
}
