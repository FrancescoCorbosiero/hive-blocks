/**
 * @hive/checkout — the checkout's Hive parts:
 *
 *   the order bump (Hive\Shop\OrderBumps): one click adds its product.
 *   On the Checkout block through WooCommerce's cart store, which updates
 *   the block; on the classic checkout through wc-ajax=add_to_cart, then
 *   WooCommerce's own order review refresh (update_checkout), which also
 *   brings the next bump that fits
 *   the bank details (Hive\Shop\BankDetails): a dialog (@hive/dialog,
 *   loaded on first use), the IBAN and BIC one tap from the clipboard
 */
import { addToCart } from '@hive/shop';
import { h } from '@hive/dom';

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const add = target?.closest('[data-hive-bump-add]');
    if (add) {
        addBump(add);
        return;
    }
    const bank = target?.closest('[data-hive-bank]');
    if (bank) {
        openBank(bank);
        return;
    }
    const copy = target?.closest('[data-hive-copy]');
    if (copy) copyValue(copy);
});

// Warm the dialog up before the click.
document.addEventListener(
    'pointerover',
    (event) => {
        if (event.target instanceof Element && event.target.closest('[data-hive-bank]')) {
            import('@hive/dialog').then(({ preload }) => preload()).catch(() => {});
        }
    },
    { passive: true }
);

async function addBump(button) {
    const bump = button.closest('[data-hive-bump]');
    const status = bump?.querySelector('[data-hive-bump-status]');
    const id = Number(button.dataset.hiveBumpAdd);
    const label = button.textContent;
    button.disabled = true;
    button.textContent = 'Aggiunta…';
    if (status) status.textContent = '';
    try {
        const store = window.wp?.data?.dispatch?.('wc/store/cart');
        if (store?.addItemToCart) {
            // The Checkout block: its own cart store adds it and the block updates.
            await store.addItemToCart(id, 1);
        } else {
            await addToCart(id, 1, button);
            // The classic checkout: WooCommerce's own refresh of totals (and of the bump).
            window.jQuery?.(document.body).trigger('update_checkout');
        }
        button.textContent = '✓ Aggiunto';
        if (status) status.textContent = 'Aggiunto all’ordine.';
        bump?.classList.add('is-added');
    } catch {
        button.disabled = false;
        button.textContent = label;
        if (status) status.textContent = 'Non è stato possibile aggiungerlo: riprova.';
    }
}

async function openBank(button) {
    const template = button.parentElement?.querySelector('template[data-hive-bank-details]');
    if (!(template instanceof HTMLTemplateElement)) return;
    const { dialog } = await import('@hive/dialog');
    const title = template.dataset.title || 'Coordinate bancarie';
    const view = dialog('bank-details', { label: title, sheet: true });
    view.body.replaceChildren(h('div', { class: 'hive-bank-sheet' }, h('h2', { class: 'hive-bank-sheet__title', text: title }), template.content.cloneNode(true)));
    view.open(button);
}

async function copyValue(button) {
    const status = button.closest('.hive-bank-sheet')?.querySelector('[data-hive-copy-status]');
    try {
        await navigator.clipboard.writeText(button.dataset.hiveCopy || '');
        button.textContent = 'Copiato';
        if (status) status.textContent = `${button.dataset.label || 'Testo'} copiato`;
        setTimeout(() => (button.textContent = 'Copia'), 2000);
    } catch {
        // No clipboard here: the value is on screen to copy by hand.
        if (status) status.textContent = 'Copia non riuscita: seleziona il testo e copialo.';
    }
}
