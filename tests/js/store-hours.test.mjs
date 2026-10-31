// The opening hours' pure helpers (blocks/store-hours/hours.js).
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { dayIndex, hhmm, parseHours, storeStatus, weekFrom } from '../../blocks/store-hours/hours.js';

const at = (y, m, d, h, mi) => ({ y, m, d, h, mi });

// The store's week: Monday–Saturday 10.30–13.00 | 15.30–19.30, Sunday closed.
const rows = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato']
    .map((day) => ({ day, time: '10.30 - 13.00 | 15.30 - 19.30' }))
    .concat([{ day: 'Domenica', time: 'Chiuso' }]);
const week = weekFrom(rows);
const options = { holidays: true, closed: [] };
const text = (now, o = options) => storeStatus(now, week, o).text;

test('reading the hours', () => {
    assert.deepEqual(parseHours('10.30 - 13.00 | 15.30 - 19.30'), [[630, 780], [930, 1170]], 'two ranges with dots');
    assert.deepEqual(parseHours('10:00–19:30'), [[600, 1170]], 'colons and an en dash');
    assert.deepEqual(parseHours('9 - 13'), [[540, 780]], 'whole hours');
    assert.deepEqual(parseHours('Chiuso'), [], 'closed');
    assert.equal(parseHours('Su appuntamento'), null, 'unreadable');
    assert.equal(hhmm(1170), '19:30');
});

test('reading the days', () => {
    assert.equal(dayIndex('Lunedì'), 1);
    assert.equal(dayIndex('domenica'), 0);
    assert.equal(dayIndex('Holiday'), null);
});

test('open or closed now, and until when', () => {
    assert.equal(text(at(2026, 10, 19, 11, 0)), 'Aperto ora · chiude alle 13:00', 'Monday morning, open');
    assert.equal(text(at(2026, 10, 19, 12, 45)), 'Aperto · chiude tra 15 min', 'closing soon');
    assert.equal(storeStatus(at(2026, 10, 19, 12, 45), week, options).soon, true, 'flagged as closing soon');
    assert.equal(text(at(2026, 10, 19, 9, 0)), 'Chiuso ora · apre alle 10:30', 'before opening');
    assert.equal(text(at(2026, 10, 19, 14, 0)), 'Chiuso ora · riapre alle 15:30', 'lunch break');
    assert.equal(text(at(2026, 10, 19, 20, 0)), 'Chiuso · apre domani alle 10:30', 'evening');
    assert.equal(text(at(2026, 10, 24, 20, 0)), 'Chiuso · apre lunedì alle 10:30', 'Saturday evening: Sunday is closed');
});

test('holidays and closed dates', () => {
    assert.equal(text(at(2026, 12, 8, 11, 0)), 'Chiuso per festività · apre domani alle 10:30', 'Immacolata');
    assert.equal(text(at(2026, 12, 8, 11, 0), { holidays: false, closed: [] }), 'Aperto ora · chiude alle 13:00', 'unless the store opens on holidays');
    assert.equal(text(at(2026, 10, 20, 11, 0), { holidays: true, closed: ['2026-10-20'] }), 'Chiuso · apre domani alle 10:30', 'a closed date');
    assert.equal(text(at(2026, 12, 24, 20, 0)), 'Chiuso · apre lunedì alle 10:30', 'Christmas and Santo Stefano skipped, Sunday closed: Monday 28');
});

test('no readable hours, no status', () => {
    assert.equal(storeStatus(at(2026, 10, 19, 11, 0), weekFrom([{ day: 'Lunedì', time: 'Su appuntamento' }]), options), null);
});
