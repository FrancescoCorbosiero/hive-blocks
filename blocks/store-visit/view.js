/**
 * Vieni in negozio — the live part (script module, loaded only with the
 * block). Writes the status, "Aperto ora · chiude alle 19:30", and marks
 * today's row, in the store's time zone, holidays and closed dates
 * included, and keeps both right minute by minute: a cached page is never
 * stuck on the day it was made. The opening hours logic is the Orari
 * block's (blocks/store-hours/hours.js, bundled here); @hive/map makes the
 * map load on its button.
 */
import '@hive/map';
import { nowIn, weekday } from '@hive/calendar';
import { storeStatus, weekFrom } from '../store-hours/hours.js';

function setup(root) {
    let config;
    try {
        config = JSON.parse(root.dataset.hiveVisit);
    } catch {
        return;
    }
    const week = weekFrom(config.rows);
    const options = { holidays: Boolean(config.holidays), closed: config.closed || [] };
    const status = root.querySelector('[data-hive-visit-status]');
    const text = root.querySelector('[data-hive-visit-text]');

    const paint = () => {
        const now = nowIn(config.tz);
        const today = weekday(now);
        for (const row of root.querySelectorAll('[data-hive-day]')) {
            const isToday = Number(row.dataset.hiveDay) === today;
            row.classList.toggle('is-today', isToday);
            const badge = row.querySelector('[data-hive-today-badge]');
            if (badge) badge.hidden = !isToday;
        }

        if (!status || !text) return;
        const state = storeStatus(now, week, options);
        status.hidden = !state;
        if (!state) return;
        status.classList.toggle('is-open', state.open);
        status.classList.toggle('is-soon', state.soon);
        text.textContent = state.text;
    };

    // Repaint just after each minute turns.
    const tick = () => {
        paint();
        setTimeout(tick, 60_000 - (Date.now() % 60_000) + 50);
    };
    tick();
}

document.querySelectorAll('[data-hive-visit]').forEach(setup);
