/**
 * Hero — the video (script module, enqueued by render.php only for a hero
 * with one). The video never loads with the page: the poster image is what
 * paints first, and the video starts once the page has loaded and the hero
 * is in view. Visitors who prefer reduced motion or save data keep the
 * poster, and the button plays the video on demand; for everyone else the
 * same button pauses it. It rests while out of view or in a background tab.
 */

const PLAY = 'Riproduci il video';
const PAUSE = 'Metti in pausa il video';
const reduced = matchMedia('(prefers-reduced-motion: reduce)');

let loaded = document.readyState === 'complete';
const heroes = [];

function setup(video) {
    const root = video.closest('.hive-hero');
    const toggle = root?.querySelector('[data-hive-hero-toggle]');
    if (!root || !toggle || root.hiveHero) return;

    // Whether the video should play: the visitor's choice once they've pressed the button.
    let wanted = !reduced.matches && !navigator.connection?.saveData;
    let inView = false;
    let asked = false;

    const paint = () => {
        toggle.classList.toggle('is-on', wanted);
        toggle.setAttribute('aria-label', wanted ? PAUSE : PLAY);
    };

    const sync = () => {
        paint();
        if (wanted && inView && !document.hidden && (loaded || asked)) {
            if (!video.getAttribute('src')) video.src = video.dataset.src;
            video.play()?.catch((error) => {
                // Blocked (the browser's autoplay rules, Low Power Mode) or unplayable: the button offers it.
                if (error?.name !== 'AbortError') {
                    wanted = false;
                    paint();
                }
            });
        } else if (!video.paused) {
            video.pause();
        }
    };

    toggle.addEventListener('click', () => {
        wanted = !wanted;
        asked = true;
        sync();
    });
    video.addEventListener('playing', () => root.classList.add('is-playing'));
    new IntersectionObserver((entries) => {
        inView = entries[entries.length - 1].isIntersecting;
        sync();
    }).observe(root);
    document.addEventListener('visibilitychange', sync);
    reduced.addEventListener('change', () => {
        if (reduced.matches) {
            wanted = false;
            sync();
        }
    });

    root.hiveHero = sync;
    heroes.push(sync);
    toggle.hidden = false;
    paint();
}

document.querySelectorAll('[data-hive-hero-video]').forEach(setup);

if (!loaded) {
    window.addEventListener(
        'load',
        () => {
            loaded = true;
            heroes.forEach((sync) => sync());
        },
        { once: true }
    );
}
