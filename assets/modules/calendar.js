/**
 * @hive/calendar — days, Italian holidays and the store's wall clock.
 *
 * A day is a plain {y, m, d} (m 1–12) worked on as a UTC date, so
 * daylight-saving changes never shift it. "Now" is read as wall-clock time
 * in the store's time zone, whatever the visitor's. Used by the opening
 * hours (blocks/store-hours) and, later, the drops and the delivery
 * estimate. Pure: tested by tests/js/calendar.test.mjs.
 */

export function utc(day) {
    return new Date(Date.UTC(day.y, day.m - 1, day.d));
}

export function fromUtc(date) {
    return { y: date.getUTCFullYear(), m: date.getUTCMonth() + 1, d: date.getUTCDate() };
}

export function addDays(day, n) {
    const date = utc(day);
    date.setUTCDate(date.getUTCDate() + n);
    return fromUtc(date);
}

/** {y: 2026, m: 4, d: 6} → '2026-04-06' */
export function iso(day) {
    return `${day.y}-${String(day.m).padStart(2, '0')}-${String(day.d).padStart(2, '0')}`;
}

/** 0 Sunday … 6 Saturday. */
export function weekday(day) {
    return utc(day).getUTCDay();
}

/** Easter Sunday (anonymous Gregorian algorithm). */
export function easter(y) {
    const a = y % 19;
    const b = Math.floor(y / 100);
    const c = y % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7;
    const m = Math.floor((a + 11 * h + 22 * l) / 451);
    return { y, m: Math.floor((h + l - 7 * m + 114) / 31), d: ((h + l - 7 * m + 114) % 31) + 1 };
}

/** Italian national holidays (month-day), Easter Monday aside. */
const FIXED_HOLIDAYS = ['01-01', '01-06', '04-25', '05-01', '06-02', '08-15', '11-01', '12-08', '12-25', '12-26'];

export function isHoliday(day) {
    return FIXED_HOLIDAYS.includes(iso(day).slice(5)) || iso(day) === iso(addDays(easter(day.y), 1));
}

/** Monday to Friday, not a holiday. */
export function isWorkday(day) {
    const dow = weekday(day);
    return dow !== 0 && dow !== 6 && !isHoliday(day);
}

/**
 * Wall-clock time in a time zone: {y, m, d, h, mi}. Falls back to the
 * device's clock where the zone isn't known.
 */
export function nowIn(tz, date = new Date()) {
    const parts = {};
    try {
        new Intl.DateTimeFormat('en-GB', {
            timeZone: tz,
            hourCycle: 'h23',
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
        })
            .formatToParts(date)
            .forEach((p) => {
                parts[p.type] = parseInt(p.value, 10);
            });
    } catch {
        return { y: date.getFullYear(), m: date.getMonth() + 1, d: date.getDate(), h: date.getHours(), mi: date.getMinutes() };
    }
    return { y: parts.year, m: parts.month, d: parts.day, h: parts.hour % 24, mi: parts.minute };
}

/** 'lunedì 20 ottobre', 'mar 21 ott': Intl in Italian, the day read as UTC. */
export function format(day, options) {
    return new Intl.DateTimeFormat('it-IT', { ...options, timeZone: 'UTC' }).format(utc(day));
}
