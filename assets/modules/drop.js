/**
 * @hive/drop — a drop's live countdown (Hive\Shop\Drops): every
 * [data-hive-drop] (the release time, UTC seconds) ticks its
 * [data-unit] cells and its [data-hive-drop-status] line each second. At the
 * release, [data-hive-drop-before] hides and [data-hive-drop-after] shows;
 * with data-hive-drop-reload the page reloads, to show the product on sale
 * (the release empties the page caches).
 *
 * parts() and status() are pure (tests/js/drop.test.mjs).
 */

/** Milliseconds left as whole days, hours, minutes, seconds. */
export function parts(ms) {
    const s = Math.max(0, Math.floor(ms / 1000));
    return { d: Math.floor(s / 86400), h: Math.floor((s % 86400) / 3600), m: Math.floor((s % 3600) / 60), s: s % 60 };
}

const pad = (n) => String(n).padStart(2, '0');

/** "Esce tra 3 giorni", "Esce tra 5 h 20 min", "Esce tra 12 min 05 s", "Esce tra 45 s", "Disponibile ora". */
export function status(ms) {
    if (ms <= 0) return 'Disponibile ora';
    const p = parts(ms);
    if (p.d >= 1) return p.d === 1 ? 'Esce tra 1 giorno' : `Esce tra ${p.d} giorni`;
    if (p.h >= 1) return `Esce tra ${p.h} h ${p.m} min`;
    if (p.m >= 1) return `Esce tra ${p.m} min ${pad(p.s)} s`;
    return `Esce tra ${p.s} s`;
}

/** Starts one countdown. */
export function countdown(root, now = () => Date.now()) {
    const at = parseInt(root.dataset.hiveDrop, 10) * 1000;
    if (!at || root.dataset.hiveDropReady) return;
    root.dataset.hiveDropReady = '1';
    const cells = [...root.querySelectorAll('[data-unit]')];
    const line = root.querySelector('[data-hive-drop-status]');

    const tick = () => {
        const left = at - now();
        const p = parts(left);
        for (const cell of cells) cell.textContent = pad(p[cell.dataset.unit] ?? 0);
        if (line) line.textContent = status(left);
        if (left > 0) return true;
        release(root);
        return false;
    };
    if (!tick()) return;
    const timer = setInterval(() => {
        if (!tick()) clearInterval(timer);
    }, 1000);
}

function release(root) {
    if (root.classList.contains('is-released')) return;
    root.classList.add('is-released');
    root.querySelectorAll('[data-hive-drop-before]').forEach((node) => (node.hidden = true));
    root.querySelectorAll('[data-hive-drop-after]').forEach((node) => (node.hidden = false));
    if (root.hasAttribute('data-hive-drop-reload')) {
        // A moment after the release, so the server sees it as out.
        setTimeout(() => location.reload(), 4000 + Math.random() * 3000);
    }
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-hive-drop]').forEach((root) => countdown(root));
}
