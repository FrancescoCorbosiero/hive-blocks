/**
 * @hive/rail — the arrows of the product rails (Hive\Shop\Rail). The rail
 * scrolls by itself with CSS scroll-snap (swipe, trackpad, keyboard); the
 * arrows appear on desktop when there's more to see, move a page of cards at
 * a time and switch off at either end.
 */

export function setup(rail) {
    const track = rail.querySelector('.hive-rail__track');
    const arrows = [...rail.querySelectorAll('.hive-rail__nav')];
    if (!track || !arrows.length || rail.hiveRail) return;
    rail.hiveRail = true;

    // A page: as many whole cards as are in view.
    const page = () => {
        const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
        const card = track.firstElementChild;
        const step = card ? card.getBoundingClientRect().width + gap : track.clientWidth;
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

    for (const arrow of arrows) {
        arrow.addEventListener('click', () => track.scrollBy({ left: Number(arrow.dataset.dir) * page(), behavior: 'smooth' }));
    }
    track.addEventListener('scroll', () => (frame ||= requestAnimationFrame(update)), { passive: true });
    new ResizeObserver(update).observe(track);
    update();
}

document.querySelectorAll('.hive-rail').forEach(setup);
