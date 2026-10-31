/**
 * @hive/gallery — the product page's photos (Hive\Shop\Gallery). Swiping is
 * CSS scroll snapping; this adds the arrows, the thumbnails and the "2 / 5"
 * count, which follow the photo in view; opens a photo full screen
 * (@hive/lightbox, loaded on the first click); and shows a size's own photo
 * when one is picked.
 */

/**
 * @param {HTMLElement} root [data-hive-gallery]
 * @return {{go: Function, showImage: Function} | null}
 */
export function gallery(root) {
    const track = root.querySelector('[data-hive-gallery-track]');
    if (!track) return null;
    const slides = [...track.children];
    const thumbs = [...root.querySelectorAll('[data-hive-gallery-go]')];
    const arrows = [...root.querySelectorAll('[data-hive-gallery-step]')];
    const counter = root.querySelector('[data-hive-gallery-index]');
    let index = 0;

    arrows.forEach((arrow) => (arrow.hidden = false));

    /** Scrolls to a photo. */
    function go(target, smooth = true) {
        const next = Math.max(0, Math.min(slides.length - 1, target));
        const slide = slides[next];
        if (!slide) return;
        track.scrollTo({ left: slide.offsetLeft - track.offsetLeft, behavior: smooth ? 'smooth' : 'instant' });
        mark(next);
    }

    function mark(next) {
        index = next;
        if (counter) counter.textContent = String(next + 1);
        thumbs.forEach((thumb, i) => (i === next ? thumb.setAttribute('aria-current', 'true') : thumb.removeAttribute('aria-current')));
        const [prev, after] = arrows;
        if (prev) prev.disabled = next === 0;
        if (after) after.disabled = next === slides.length - 1;
    }

    // The photo in view decides the count and the thumbnail, however it got there (swipe, keys, arrows).
    if ('IntersectionObserver' in window && slides.length > 1) {
        const seen = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) mark(slides.indexOf(entry.target));
                }
            },
            { root: track, threshold: 0.6 }
        );
        slides.forEach((slide) => seen.observe(slide));
    }
    mark(0);

    root.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const step = target?.closest('[data-hive-gallery-step]');
        if (step) {
            go(index + Number(step.dataset.hiveGalleryStep));
            return;
        }
        const thumb = target?.closest('[data-hive-gallery-go]');
        if (thumb) {
            go(Number(thumb.dataset.hiveGalleryGo));
            return;
        }
        const zoom = target?.closest('.hive-gallery__zoom');
        if (zoom && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
            event.preventDefault();
            openLightbox(slides.indexOf(zoom.closest('.hive-gallery__slide')), zoom);
        }
    });

    // Warm the full-screen view up before the click.
    root.addEventListener('pointerover', warm, { once: true, passive: true });
    root.addEventListener('focusin', warm, { once: true });

    track.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
            event.preventDefault();
            go(index + (event.key === 'ArrowRight' ? 1 : -1));
        }
    });

    function items() {
        return slides
            .map((slide) => {
                const link = slide.querySelector('.hive-gallery__zoom');
                const img = slide.querySelector('img');
                return link && img
                    ? { src: link.href, width: Number(link.dataset.width) || 0, height: Number(link.dataset.height) || 0, alt: img.alt, preview: img.currentSrc || img.src }
                    : null;
            })
            .filter(Boolean);
    }

    async function openLightbox(at, trigger) {
        const { open } = await import('@hive/lightbox');
        open(items(), Math.max(0, at), trigger, (last) => go(last, false));
    }

    // A size's own photo: in the gallery already, scroll to it; otherwise it takes the first photo's place until the size changes.
    const first = slides[0];
    const firstImg = first?.querySelector('img');
    const firstLink = first?.querySelector('.hive-gallery__zoom');
    const original = firstImg
        ? { src: firstImg.getAttribute('src'), srcset: firstImg.getAttribute('srcset'), sizes: firstImg.getAttribute('sizes'), alt: firstImg.alt, href: firstLink?.getAttribute('href'), w: firstLink?.dataset.width, h: firstLink?.dataset.height }
        : null;
    let swapped = false;

    /**
     * @param {{image_id?: number, image?: Object}|null} variation WooCommerce's variation data, or null to go back.
     */
    function showImage(variation) {
        const id = Number(variation?.image_id || 0);
        const ownId = Number(first?.dataset.imageId || 0);
        if (!id || id === ownId) {
            restore();
            return;
        }
        const at = slides.findIndex((slide) => Number(slide.dataset.imageId) === id);
        if (at > 0) {
            restore();
            go(at);
            return;
        }
        const image = variation.image || {};
        if (!firstImg || !image.src) return;
        firstImg.srcset = image.srcset || '';
        firstImg.src = image.src;
        if (image.sizes) firstImg.sizes = image.sizes;
        firstImg.alt = image.alt || firstImg.alt;
        if (firstLink && image.full_src) {
            firstLink.href = image.full_src;
            firstLink.dataset.width = image.full_src_w || '';
            firstLink.dataset.height = image.full_src_h || '';
        }
        swapped = true;
        go(0);
    }

    function restore() {
        if (!swapped || !original) return;
        swapped = false;
        if (original.srcset) firstImg.setAttribute('srcset', original.srcset);
        else firstImg.removeAttribute('srcset');
        firstImg.setAttribute('src', original.src);
        if (original.sizes) firstImg.setAttribute('sizes', original.sizes);
        firstImg.alt = original.alt;
        if (firstLink) {
            firstLink.setAttribute('href', original.href);
            firstLink.dataset.width = original.w || '';
            firstLink.dataset.height = original.h || '';
        }
    }

    return { go, showImage };
}

function warm() {
    import('@hive/lightbox').then(({ prefetch }) => prefetch()).catch(() => {});
}
