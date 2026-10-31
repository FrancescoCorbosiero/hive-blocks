// @hive/delivery (assets/modules/delivery.js): when an order ships and arrives.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { duration, estimate } from '@hive/delivery';

const day = (y, m, d) => ({ y, m, d });
const at = (y, m, d, h, mi) => ({ y, m, d, h, mi });
const config = { cutoff: '13:00', min: 1, max: 3, closed: [] };

test('before the cutoff on a workday it ships today', () => {
    const e = estimate(at(2026, 10, 19, 10, 30), config);
    assert.equal(e.today, true);
    assert.equal(e.minutesLeft, 150);
    assert.deepEqual([e.from, e.to], [day(2026, 10, 20), day(2026, 10, 22)], 'delivery Tue 20 – Thu 22');
});

test('at the cutoff it ships the next workday', () => {
    const e = estimate(at(2026, 10, 19, 13, 0), config);
    assert.equal(e.today, false);
    assert.deepEqual(e.ship, day(2026, 10, 20));
});

test('weekends are skipped for shipping and delivery', () => {
    let e = estimate(at(2026, 10, 23, 18, 0), config);
    assert.deepEqual(e.ship, day(2026, 10, 26), 'Friday evening ships on Monday');
    assert.deepEqual([e.from, e.to], [day(2026, 10, 27), day(2026, 10, 29)]);
    e = estimate(at(2026, 10, 24, 9, 0), config);
    assert.deepEqual([e.today, e.ship], [false, day(2026, 10, 26)], 'Saturday ships on Monday');
});

test('holidays: New Year and Easter Monday', () => {
    let e = estimate(at(2026, 12, 31, 15, 0), config);
    assert.deepEqual(e.ship, day(2027, 1, 4), 'Fri 1 is a holiday, then the weekend');
    assert.deepEqual(e.from, day(2027, 1, 5));
    e = estimate(at(2026, 4, 3, 9, 0), config);
    assert.deepEqual([e.ship, e.from, e.to], [day(2026, 4, 3), day(2026, 4, 7), day(2026, 4, 9)], 'Pasquetta is skipped');
});

test('the store’s closed days move the shipping day only', () => {
    const e = estimate(at(2026, 10, 19, 10, 0), { ...config, closed: ['2026-10-19', '2026-10-20'] });
    assert.deepEqual([e.today, e.ship], [false, day(2026, 10, 21)]);
});

test('a one-day window', () => {
    const e = estimate(at(2026, 10, 19, 10, 0), { ...config, min: 2, max: 2 });
    assert.deepEqual([e.from, e.to], [day(2026, 10, 21), day(2026, 10, 21)]);
});

test('durations', () => {
    assert.equal(duration(135), '2 h 15 min');
    assert.equal(duration(40), '40 min');
});
