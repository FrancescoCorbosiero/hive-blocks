/**
 * Slider categorie — the arrows (script module, loaded only with the
 * block). The row scrolls by itself with CSS scroll-snap (swipe, trackpad,
 * keyboard); the arrows appear on desktop when there's more to see, move a
 * page of categories at a time and switch off at either end.
 */

function setup(root) {
    const track = root.querySelector('.hive-category-slider__track');
    const arrows = [...root.querySelectorAll('.hive-category-slider__nav')];
    if (!track || !arrows.length) return;

    // A page: as many whole items as are in view.
    const page = () => {
        const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
        const item = track.firstElementChild;
        const step = item ? item.getBoundingClientRect().width + gap : track.clientWidth;
        return Math.max(1, Math.floor((track.clientWidth + gap) / step)) * step;
    };

    let frame = 0;
    const update = () => {
        frame = 0;
        const max = track.scrollWidth - track.clientWidth - 1;
        for (const arrow of arrows) {
            arrow.hidden = max <= 1;
            arrow.disabled = Number(arrow.dataset.dir) < 0 ? track.scrollLeft <= 1 : track.scrollLeft >= max;
        }
    };

    const smooth = () => (window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth');
    for (const arrow of arrows) {
        arrow.addEventListener('click', () => track.scrollBy({ left: Number(arrow.dataset.dir) * page(), behavior: smooth() }));
    }
    track.addEventListener('scroll', () => (frame ||= requestAnimationFrame(update)), { passive: true });
    new ResizeObserver(update).observe(track);
    update();
}

document.querySelectorAll('.hive-category-slider').forEach(setup);
