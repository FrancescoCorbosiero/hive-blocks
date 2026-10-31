/**
 * Orari del negozio — the live part (script module, loaded only with the
 * block). Marks today's row and writes the status, "Aperto ora · chiude
 * alle 19:30", in the store's time zone, and keeps both right minute by
 * minute: a cached page is never stuck on the day it was generated.
 */
import { nowIn, weekday } from '@hive/calendar';
import { storeStatus, weekFrom } from './hours.js';

function setup(root) {
    let config;
    try {
        config = JSON.parse(root.dataset.hiveHours);
    } catch {
        return;
    }
    const week = weekFrom(config.rows);
    const status = root.querySelector('[data-hive-hours-status]');
    const text = root.querySelector('[data-hive-hours-text]');

    const paint = () => {
        const now = nowIn(config.tz);
        const today = weekday(now);
        for (const row of root.querySelectorAll('[data-hive-day]')) {
            const isToday = Number(row.dataset.hiveDay) === today;
            row.classList.toggle('is-today', isToday);
            const badge = row.querySelector('[data-hive-today-badge]');
            if (badge) badge.hidden = !isToday;
        }

        const state = status && text ? storeStatus(now, week, { holidays: Boolean(config.holidays), closed: config.closed || [] }) : null;
        if (!status) return;
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

document.querySelectorAll('[data-hive-hours]').forEach(setup);
