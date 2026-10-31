/**
 * @hive/cards — what the product cards' buttons do (Hive\Shop\Card):
 *
 *   [data-hive-quick-view]       opens Quick View (@hive/quick-view)
 *   [data-hive-add][data-hive-pick]  opens Quick Add to choose a size (@hive/quick-add)
 *   [data-hive-add]              adds a simple product to the cart
 *   data-hive-size               the variation to start from (the shop's size filter)
 *
 * One listener on the document covers every card, including cards added
 * later. Quick View and Quick Add load on first use; hovering or focusing a
 * button fetches the module and the product's data ahead, so the dialog
 * opens at once.
 */
import { addToCart } from '@hive/shop';

const load = {
    view: () => import('@hive/quick-view'),
    add: () => import('@hive/quick-add'),
};

const number = (value) => (value ? Number(value) : null);

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;

    const peek = target?.closest('[data-hive-quick-view]');
    if (peek) {
        event.preventDefault();
        load.view().then((quickView) => quickView.open(Number(peek.dataset.hiveQuickView), { size: number(peek.dataset.hiveSize), trigger: peek }));
        return;
    }

    const add = target?.closest('[data-hive-add]');
    if (!add) return;
    event.preventDefault();
    const id = Number(add.dataset.hiveAdd);
    if (add.hasAttribute('data-hive-pick')) {
        load.add().then((quickAdd) => quickAdd.open(id, { size: number(add.dataset.hiveSize), trigger: add }));
    } else {
        addSimple(add, id);
    }
});

async function addSimple(button, id) {
    if (button.disabled) return;
    const label = button.textContent;
    // Says how it went for two seconds, then the button is back as it was.
    const show = (text) => {
        button.textContent = text;
        setTimeout(() => {
            button.textContent = label;
            button.disabled = false;
        }, 2000);
    };

    button.disabled = true;
    button.textContent = 'Aggiunta…';
    try {
        await addToCart(id, 1, button);
        show('✓ Aggiunto');
    } catch (error) {
        // WooCommerce refused it: its product page says why.
        if (error.productUrl) {
            window.location.assign(error.productUrl);
            return;
        }
        show('Errore, riprova');
    }
}

const warmed = new Set();

function warm(event) {
    const target = event.target instanceof Element ? event.target.closest('[data-hive-quick-view], [data-hive-add][data-hive-pick]') : null;
    if (!target) return;
    const isView = target.hasAttribute('data-hive-quick-view');
    const id = Number(isView ? target.dataset.hiveQuickView : target.dataset.hiveAdd);
    const key = `${isView ? 'view' : 'add'}:${id}`;
    if (warmed.has(key)) return;
    warmed.add(key);
    (isView ? load.view() : load.add()).then((module) => module.prefetch(id)).catch(() => warmed.delete(key));
}

document.addEventListener('pointerover', warm, { passive: true });
document.addEventListener('focusin', warm);
