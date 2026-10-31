/**
 * @hive/dom — small helpers the other modules share: the data WordPress
 * prints for a module, building elements without HTML strings, and loading
 * a stylesheet on first use.
 */

/** The JSON WordPress prints for a script module (the script_module_data_{id} filter). */
export function moduleData(id) {
    const node = document.getElementById(`wp-script-module-data-${id}`);
    try {
        return JSON.parse(node?.textContent || '{}');
    } catch {
        return {};
    }
}

/**
 * An element: h('button', { class: 'x', type: 'button', text: 'Ok', onclick }, ...children).
 * 'text' sets textContent; 'html' sets innerHTML and is only for markup the
 * server built (WooCommerce's price HTML); on* adds a listener; true makes
 * an empty attribute; false, null and undefined leave it out.
 */
export function h(tag, props = {}, ...children) {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(props || {})) {
        if (value === false || value === null || value === undefined) continue;
        if (key === 'class') node.className = value;
        else if (key === 'text') node.textContent = value;
        else if (key === 'html') node.innerHTML = value;
        else if (key.startsWith('on') && typeof value === 'function') node.addEventListener(key.slice(2), value);
        else node.setAttribute(key, value === true ? '' : String(value));
    }
    node.append(...children.flat(Infinity).filter((child) => child !== null && child !== undefined && child !== false));
    return node;
}

/** An inline SVG icon from a path (24×24 viewBox). */
export function icon(path) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    const shape = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    shape.setAttribute('d', path);
    svg.append(shape);
    return svg;
}

const styles = new Map();

/** Adds a stylesheet once; resolves when it has loaded (or failed: the page goes on). */
export function loadStyle(href) {
    if (!href) return Promise.resolve();
    if (!styles.has(href)) {
        styles.set(
            href,
            new Promise((resolve) => {
                const link = h('link', { rel: 'stylesheet', href });
                link.addEventListener('load', resolve, { once: true });
                link.addEventListener('error', resolve, { once: true });
                document.head.append(link);
            })
        );
    }
    return styles.get(href);
}
