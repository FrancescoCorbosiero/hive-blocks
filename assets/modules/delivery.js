/**
 * @hive/delivery — the product page's delivery estimate: "Ordina entro
 * 2 h 15 min e spediamo oggi · Consegna stimata mar 21 – gio 23 ott", from
 * the store's cutoff time and transit days (Hive → Pagina prodotto). Worked
 * out in the browser, because the page is cached and "now" is the
 * visitor's. Shipping days skip weekends, Italian national holidays and the
 * store's closed dates; delivery days skip weekends and holidays.
 *
 * estimate() is pure (tests/js/delivery.test.mjs).
 */
import { addDays, format, iso, isWorkday, nowIn } from '@hive/calendar';

/**
 * When an order placed now ships and arrives.
 *
 * @param {{y: number, m: number, d: number, h: number, mi: number}} now Wall-clock time in the store's time zone.
 * @param {{cutoff?: string, min?: number, max?: number, closed?: string[]}} config
 * @return {{today: boolean, minutesLeft: number, ship: Object, from: Object, to: Object}}
 */
export function estimate(now, config) {
    const closed = config.closed || [];
    const [hours, minutes] = String(config.cutoff || '13:00').split(':').map((part) => parseInt(part, 10) || 0);
    const today = { y: now.y, m: now.m, d: now.d };
    const minutesLeft = hours * 60 + minutes - (now.h * 60 + now.mi);
    const shipsToday = isWorkday(today) && !closed.includes(iso(today)) && minutesLeft > 0;
    const ship = shipsToday ? today : nextShippingDay(today, closed);
    const min = config.min || 0;
    return {
        today: shipsToday,
        minutesLeft: shipsToday ? minutesLeft : 0,
        ship,
        from: addWorkdays(ship, min),
        to: addWorkdays(ship, Math.max(min, config.max || 0)),
    };
}

function nextShippingDay(day, closed) {
    let next = addDays(day, 1);
    while (!isWorkday(next) || closed.includes(iso(next))) next = addDays(next, 1);
    return next;
}

function addWorkdays(day, n) {
    let out = day;
    for (let i = 0; i < n; i++) {
        out = addDays(out, 1);
        while (!isWorkday(out)) out = addDays(out, 1);
    }
    return out;
}

/** "2 h 15 min", "40 min". */
export function duration(minutes) {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return h ? `${h} h ${m} min` : `${m} min`;
}

/**
 * Fills a [data-hive-delivery] box (its JSON: cutoff, min, max, closed, tz)
 * and keeps it current every minute.
 */
export function deliveryBox(box) {
    let config;
    try {
        config = JSON.parse(box.dataset.hiveDelivery || '{}');
    } catch {
        return null;
    }
    const render = () => {
        const now = nowIn(config.tz || 'Europe/Rome');
        const est = estimate(now, config);
        const short = { weekday: 'short', day: 'numeric', month: 'short' };
        const tomorrow = iso(addDays({ y: now.y, m: now.m, d: now.d }, 1));
        const first = est.today
            ? line('Ordina entro ', duration(est.minutesLeft), ' e spediamo oggi')
            : line('Spediamo ', iso(est.ship) === tomorrow ? 'domani' : format(est.ship, { weekday: 'long', day: 'numeric', month: 'long' }));
        const window = iso(est.from) === iso(est.to) ? format(est.from, short) : `${format(est.from, short)} – ${format(est.to, short)}`;
        box.replaceChildren(first, line('Consegna stimata ', window));
        box.hidden = false;
    };
    render();
    const timer = setInterval(render, 60_000);
    return {
        /** A sold-out size ships nothing: the estimate steps aside. */
        set available(on) {
            box.hidden = !on;
        },
        stop: () => clearInterval(timer),
    };
}

function line(text, strong, after = '') {
    const p = document.createElement('p');
    p.className = 'hive-delivery__line';
    const b = document.createElement('strong');
    b.textContent = strong;
    p.append(text, b, after);
    return p;
}
