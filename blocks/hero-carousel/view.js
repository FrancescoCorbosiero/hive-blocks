/**
 * Carosello — the arrows, the dots and autoplay (script module, loaded with
 * the block). Sliding itself is CSS scroll snapping: swipe, trackpad, and a
 * link reached with the keyboard scrolling into view all work without this,
 * and the dots follow whatever brought a slide into view.
 *
 * Autoplay never runs for visitors who prefer reduced motion. It pauses
 * while the pointer is over the carousel or the keyboard is in it, and
 * while it's out of view or the tab is hidden; its button stops it until
 * pressed again. Every move, by hand or by itself, restarts the wait.
 */
import { rotates, wrap } from './carousel.js';

const STOP = 'Ferma lo scorrimento automatico';
const START = 'Avvia lo scorrimento automatico';
const reduced = matchMedia('(prefers-reduced-motion: reduce)');

let loaded = document.readyState === 'complete';
const warmers = [];

function setup(root) {
    const track = root.querySelector('[data-hive-carousel-track]');
    const slides = track ? [...track.querySelectorAll(':scope > .hive-hero-carousel__slide')] : [];
    if (slides.length < 2 || root.hiveCarousel) return;
    root.hiveCarousel = true;

    const dots = [...root.querySelectorAll('[data-hive-carousel-go]')];
    const toggle = root.querySelector('[data-hive-carousel-toggle]');
    const controls = root.querySelector('[data-hive-carousel-controls]');
    const state = {
        delay: Number(root.dataset.hiveCarousel) || 0,
        reduced: reduced.matches,
        stopped: false,
        hovered: false,
        focused: false,
        visible: true,
        hidden: document.hidden,
    };
    let index = 0;
    let timer = 0;

    // Once the page has loaded, the next slide's image loads ahead of it (it's lazy until then).
    const warm = () => {
        if (!loaded) return;
        const image = slides[wrap(index + 1, slides.length)].querySelector('img[loading="lazy"]');
        if (image) image.loading = 'eager';
    };

    const mark = (next) => {
        index = next;
        dots.forEach((dot, i) => (i === next ? dot.setAttribute('aria-current', 'true') : dot.removeAttribute('aria-current')));
        warm();
    };

    const schedule = () => {
        clearTimeout(timer);
        if (rotates(state)) timer = setTimeout(() => go(index + 1), state.delay);
    };

    const paintToggle = () => {
        if (!toggle) return;
        toggle.hidden = !state.delay || state.reduced;
        toggle.classList.toggle('is-on', !state.stopped);
        toggle.setAttribute('aria-label', state.stopped ? START : STOP);
    };

    function go(target) {
        const next = wrap(target, slides.length);
        track.scrollTo({ left: slides[next].offsetLeft - slides[0].offsetLeft, behavior: reduced.matches ? 'instant' : 'smooth' });
        mark(next);
        schedule();
    }

    // The slide in view, however it got there.
    const seen = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                const at = slides.indexOf(entry.target);
                if (entry.intersectionRatio >= 0.6 && at !== index) {
                    mark(at);
                    schedule();
                }
            }
        },
        { root: track, threshold: 0.6 }
    );
    slides.forEach((slide) => seen.observe(slide));

    root.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const step = target?.closest('[data-hive-carousel-step]');
        const dot = target?.closest('[data-hive-carousel-go]');
        if (step) {
            go(index + Number(step.dataset.hiveCarouselStep));
        } else if (dot) {
            go(Number(dot.dataset.hiveCarouselGo));
        } else if (toggle && target?.closest('[data-hive-carousel-toggle]')) {
            state.stopped = !state.stopped;
            paintToggle();
            schedule();
        }
    });

    // The pointer over the carousel, the keyboard in it (a click's focus doesn't count).
    const hover = (on) => (event) => {
        if (event.pointerType !== 'mouse') return;
        state.hovered = on;
        schedule();
    };
    root.addEventListener('pointerenter', hover(true));
    root.addEventListener('pointerleave', hover(false));
    root.addEventListener('focusin', (event) => {
        state.focused = event.target instanceof Element && event.target.matches(':focus-visible');
        schedule();
    });
    root.addEventListener('focusout', (event) => {
        if (root.contains(event.relatedTarget)) return;
        state.focused = false;
        schedule();
    });

    new IntersectionObserver((entries) => {
        state.visible = entries[entries.length - 1].isIntersecting;
        schedule();
    }).observe(root);
    document.addEventListener('visibilitychange', () => {
        state.hidden = document.hidden;
        schedule();
    });
    reduced.addEventListener('change', () => {
        state.reduced = reduced.matches;
        paintToggle();
        schedule();
    });

    warmers.push(warm);
    if (controls) controls.hidden = false;
    paintToggle();
    mark(0);
    schedule();
}

document.querySelectorAll('[data-hive-carousel]').forEach(setup);

if (!loaded) {
    window.addEventListener(
        'load',
        () => {
            loaded = true;
            warmers.forEach((warm) => warm());
        },
        { once: true }
    );
}
