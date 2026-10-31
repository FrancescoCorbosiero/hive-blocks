/**
 * Opening hours, the browser side (the server side is src/Core/OpeningHours.php):
 * reading the rows the store writes and saying whether it's open now. Pure:
 * tested by tests/js/store-hours.test.mjs.
 */
import { addDays, format, isHoliday, iso, weekday } from '@hive/calendar';

const pad = (n) => String(n).padStart(2, '0');

/** Minutes after midnight → '19:30'. */
export const hhmm = (min) => `${pad(Math.floor(min / 60) % 24)}:${pad(min % 60)}`;

/** '10.30 - 13.00 | 15.30 - 19.30' → [[630, 780], [930, 1170]]; 'Chiuso' → []; unreadable → null. */
export function parseHours(text) {
    const t = String(text || '').trim().toLowerCase();
    if (!t) return null;
    if (/^chius/.test(t)) return [];
    const ranges = [];
    const re = /(\d{1,2})(?:[.:](\d{2}))?\s*[-–—]\s*(\d{1,2})(?:[.:](\d{2}))?/g;
    let m;
    while ((m = re.exec(t))) {
        const from = parseInt(m[1], 10) * 60 + parseInt(m[2] || '0', 10);
        const to = parseInt(m[3], 10) * 60 + parseInt(m[4] || '0', 10);
        if (to > from) ranges.push([from, to]);
    }
    return ranges.length ? ranges : null;
}

const DAYS = { dom: 0, lun: 1, mar: 2, mer: 3, gio: 4, ven: 5, sab: 6 };

/** 'Lunedì' → 1 … 'Domenica' → 0 (Date.getDay numbering); null when it isn't a day. */
export function dayIndex(name) {
    const key = String(name || '').trim().toLowerCase().slice(0, 3);
    return Object.hasOwn(DAYS, key) ? DAYS[key] : null;
}

/** Rows [{day, time}] → {0…6: ranges | null}. */
export function weekFrom(rows) {
    const week = {};
    for (const row of rows || []) {
        const i = dayIndex(row.day);
        if (i !== null) week[i] = parseHours(row.time);
    }
    return week;
}

/**
 * Open or closed now, and until when.
 *
 * @param {{y, m, d, h, mi}} now Wall-clock time in the store's time zone.
 * @param {Object} week weekFrom(rows).
 * @param {{holidays: boolean, closed: string[]}} options Closed on national holidays; other closed days ('2026-12-24').
 * @return {{open: boolean, soon: boolean, text: string}|null} null when no hours can be read.
 */
export function storeStatus(now, week, { holidays = false, closed = [] } = {}) {
    if (!Object.values(week).some((ranges) => ranges !== null && ranges !== undefined)) return null;

    const today = { y: now.y, m: now.m, d: now.d };
    const holiday = (day) => holidays && isHoliday(day);
    const shut = (day) => holiday(day) || closed.includes(iso(day));
    const rangesOn = (day) => (shut(day) ? [] : week[weekday(day)] || []);
    const mins = now.h * 60 + now.mi;
    const todays = rangesOn(today);

    for (const [from, to] of todays) {
        if (mins >= from && mins < to) {
            const left = to - mins;
            return left <= 30
                ? { open: true, soon: true, text: `Aperto · chiude tra ${left} min` }
                : { open: true, soon: false, text: `Aperto ora · chiude alle ${hhmm(to)}` };
        }
    }
    for (let i = 0; i < todays.length; i++) {
        if (mins < todays[i][0]) {
            return { open: false, soon: false, text: `Chiuso ora · ${i > 0 ? 'riapre' : 'apre'} alle ${hhmm(todays[i][0])}` };
        }
    }

    const lead = holiday(today) ? 'Chiuso per festività' : 'Chiuso';
    for (let i = 1; i <= 7; i++) {
        const day = addDays(today, i);
        const ranges = rangesOn(day);
        if (ranges.length) {
            const when = i === 1 ? 'domani' : format(day, { weekday: 'long' });
            return { open: false, soon: false, text: `${lead} · apre ${when} alle ${hhmm(ranges[0][0])}` };
        }
    }
    return { open: false, soon: false, text: lead };
}
