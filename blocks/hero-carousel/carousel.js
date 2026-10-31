/**
 * Carosello — the pure part of view.js (tested by tests/js/hero-carousel.test.mjs):
 * which slide a step lands on, and when autoplay may move the slides.
 */

/** The slide a step lands on: past the last comes the first, before the first the last. */
export function wrap(index, count) {
    return count > 0 ? ((index % count) + count) % count : 0;
}

/**
 * Whether autoplay moves on by itself right now: it's on (a pause between
 * slides), the visitor doesn't prefer reduced motion and hasn't stopped it,
 * the pointer isn't over the carousel, the keyboard isn't in it, and it's
 * in view in a visible tab.
 *
 * @param {{delay: number, reduced: boolean, stopped: boolean, hovered: boolean, focused: boolean, visible: boolean, hidden: boolean}} state
 */
export function rotates({ delay, reduced, stopped, hovered, focused, visible, hidden }) {
    return delay > 0 && !reduced && !stopped && !hovered && !focused && visible && !hidden;
}
