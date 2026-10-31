/**
 * @hive/dialog — modal dialogs on the native <dialog> element: the browser
 * keeps focus inside and the page inert behind it; Esc and a click on the
 * backdrop close it; focus goes back to what opened it. A centred panel, a
 * sheet rising from the bottom on phones (sheet: true), or the whole screen
 * (full: true).
 *
 * dialog.css comes with the first dialog that opens, so pages where nobody
 * opens one never load it. preload() fetches it ahead (on hover, say).
 */
import { h, icon, loadStyle, moduleData } from '@hive/dom';

const { style = '' } = moduleData('@hive/dialog');
let openCount = 0;

/** Fetches dialog.css now, so the first dialog opens without waiting for it. */
export function preload() {
    return loadStyle(style);
}

/**
 * The dialog called `name`, created on first use and reused after.
 *
 * @param {string} name
 * @param {{label: string, sheet?: boolean, full?: boolean}} options
 * @return {{node: HTMLDialogElement, body: HTMLElement, open: Function, close: Function, isOpen: boolean}}
 */
export function dialog(name, { label, sheet = false, full = false }) {
    let node = document.querySelector(`dialog[data-hive-dialog="${name}"]`);
    if (!node) {
        node = create(name, label, full ? 'full' : sheet ? 'sheet' : '');
    } else if (label) {
        node.setAttribute('aria-label', label);
    }
    const body = node.querySelector('.hive-dialog__body');

    return {
        node,
        body,
        /** Opens it (after dialog.css has loaded); `trigger` gets the focus back on close. */
        async open(trigger = null) {
            await preload();
            if (node.open) return;
            node.hiveReturnFocus = trigger || document.activeElement;
            node.showModal();
            if (openCount++ === 0) document.documentElement.classList.add('hive-dialog-open');
        },
        close() {
            if (node.open) node.close();
        },
        get isOpen() {
            return node.open;
        },
    };
}

function create(name, label, variant) {
    const close = h('button', { type: 'button', class: 'hive-dialog__close', 'aria-label': 'Chiudi' }, icon('M6.4 5 5 6.4 10.6 12 5 17.6 6.4 19l5.6-5.6 5.6 5.6 1.4-1.4-5.6-5.6L19 6.4 17.6 5 12 10.6z'));
    const node = h(
        'dialog',
        { class: `hive-dialog${variant ? ` hive-dialog--${variant}` : ''}`, 'data-hive-dialog': name, 'aria-label': label },
        h('div', { class: 'hive-dialog__panel' }, close, h('div', { class: 'hive-dialog__body' }))
    );

    close.addEventListener('click', () => node.close());
    // A click on the dialog element itself is a click on its backdrop.
    node.addEventListener('click', (event) => {
        if (event.target === node) node.close();
    });
    node.addEventListener('close', () => {
        if (--openCount <= 0) {
            openCount = 0;
            document.documentElement.classList.remove('hive-dialog-open');
        }
        const back = node.hiveReturnFocus;
        node.hiveReturnFocus = null;
        if (back && typeof back.focus === 'function' && document.contains(back)) back.focus();
    });

    document.body.append(node);
    return node;
}
