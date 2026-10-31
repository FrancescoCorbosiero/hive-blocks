/**
 * @hive/lightbox — the product's photos full screen, at their full size:
 * swipe or arrows (and the keyboard's) to move, a click (or a tap) to zoom
 * in and out, then scroll or drag the zoomed photo; pinching works as the
 * browser's own zoom. Loaded by @hive/gallery on the first click on a photo.
 */
import { dialog, preload } from '@hive/dialog';
import { h } from '@hive/dom';

const ARROW = { prev: 'm14.5 6-6 6 6 6', next: 'm9.5 6 6 6-6 6' };

let view = null;

/** The dialog's CSS ahead of the click. */
export function prefetch() {
    return preload();
}

/**
 * @param {Array<{src: string, width: number, height: number, alt: string, preview?: string}>} items
 * @param {number} start The photo to open at.
 * @param {Element|null} trigger Gets the focus back on close.
 * @param {(index: number) => void} onClose The photo last seen, so the page's gallery can show it.
 */
export async function open(items, start, trigger = null, onClose = () => {}) {
    if (!items.length) return;
    view ??= dialog('lightbox', { label: 'Foto del prodotto', full: true });

    const slides = items.map((item, i) =>
        h(
            'li',
            { class: 'hive-lightbox__slide' },
            h('img', {
                class: 'hive-lightbox__image',
                // The page's copy shows at once; the full size replaces it when it arrives.
                src: Math.abs(i - start) <= 1 ? item.src : item.preview || item.src,
                'data-full': item.src,
                alt: item.alt || '',
                width: item.width || false,
                height: item.height || false,
                decoding: 'async',
                draggable: 'false',
                style: item.width ? `--hive-natural-width:${item.width}px` : false,
            })
        )
    );
    const track = h('ul', { class: 'hive-lightbox__track', role: 'list' }, slides);
    const count = h('p', { class: 'hive-lightbox__count', 'aria-live': 'polite' });
    const prev = arrow('prev', 'Foto precedente');
    const next = arrow('next', 'Foto successiva');
    let index = start;

    const show = (i, smooth = true) => {
        index = Math.max(0, Math.min(slides.length - 1, i));
        track.scrollTo({ left: slides[index].offsetLeft, behavior: smooth ? 'smooth' : 'instant' });
        update();
    };
    const update = () => {
        count.textContent = `${index + 1} / ${slides.length}`;
        prev.disabled = index === 0;
        next.disabled = index === slides.length - 1;
        // The neighbours at full size too, so a swipe lands on a sharp photo.
        for (const at of [index - 1, index, index + 1]) {
            const img = slides[at]?.querySelector('img');
            if (img && img.src !== img.dataset.full) img.src = img.dataset.full;
        }
    };

    prev.addEventListener('click', () => show(index - 1));
    next.addEventListener('click', () => show(index + 1));
    // Where a swipe settled (scrollend isn't everywhere yet: a pause in scrolling will do).
    let settle = 0;
    track.addEventListener(
        'scroll',
        () => {
            clearTimeout(settle);
            settle = setTimeout(() => {
                const at = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
                if (at !== index) {
                    unzoom();
                    index = at;
                    update();
                }
            }, 120);
        },
        { passive: true }
    );
    track.addEventListener('click', (event) => {
        const slide = event.target instanceof Element ? event.target.closest('.hive-lightbox__slide') : null;
        if (!slide || dragged) return;
        const zoomed = slide.classList.toggle('is-zoomed');
        if (zoomed) {
            // Zoom in on the point that was clicked.
            const rect = slide.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width;
            const y = (event.clientY - rect.top) / rect.height;
            requestAnimationFrame(() => {
                slide.scrollLeft = x * slide.scrollWidth - rect.width / 2;
                slide.scrollTop = y * slide.scrollHeight - rect.height / 2;
            });
        }
    });

    // A zoomed photo moves with the mouse held down.
    let drag = null;
    let dragged = false;
    track.addEventListener('pointerdown', (event) => {
        const slide = event.target instanceof Element ? event.target.closest('.hive-lightbox__slide.is-zoomed') : null;
        dragged = false;
        if (!slide || event.pointerType !== 'mouse') return;
        drag = { slide, x: event.clientX, y: event.clientY, left: slide.scrollLeft, top: slide.scrollTop };
    });
    track.addEventListener('pointermove', (event) => {
        if (!drag) return;
        const dx = event.clientX - drag.x;
        const dy = event.clientY - drag.y;
        if (Math.abs(dx) + Math.abs(dy) > 4) dragged = true;
        drag.slide.scrollLeft = drag.left - dx;
        drag.slide.scrollTop = drag.top - dy;
    });
    const stop = () => (drag = null);
    track.addEventListener('pointerup', stop);
    track.addEventListener('pointerleave', stop);

    const unzoom = () => slides.forEach((slide) => slide.classList.remove('is-zoomed'));

    const keys = (event) => {
        if (event.key === 'ArrowRight') show(index + 1);
        if (event.key === 'ArrowLeft') show(index - 1);
    };

    view.body.replaceChildren(h('div', { class: 'hive-lightbox' }, track, slides.length > 1 ? [prev, next, count] : null));
    view.node.addEventListener('keydown', keys);
    view.node.addEventListener(
        'close',
        () => {
            view.node.removeEventListener('keydown', keys);
            onClose(index);
        },
        { once: true }
    );
    await view.open(trigger);
    show(start, false);
}

function arrow(direction, label) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', ARROW[direction]);
    svg.append(path);
    return h('button', { type: 'button', class: `hive-lightbox__arrow is-${direction}`, 'aria-label': label }, svg);
}
