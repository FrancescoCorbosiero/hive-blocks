// @hive/calendar (assets/modules/calendar.js).
import assert from 'node:assert/strict';
import { test } from 'node:test';
import * as cal from '@hive/calendar';

const day = (y, m, d) => ({ y, m, d });

test('Easter and Easter Monday', () => {
    assert.deepEqual(cal.easter(2025), day(2025, 4, 20));
    assert.deepEqual(cal.easter(2026), day(2026, 4, 5));
    assert.deepEqual(cal.easter(2027), day(2027, 3, 28));
    assert.equal(cal.isHoliday(day(2026, 4, 6)), true, 'Pasquetta 2026');
    assert.equal(cal.isHoliday(day(2026, 4, 7)), false, 'the day after Pasquetta');
});

test('fixed national holidays', () => {
    assert.equal(cal.isHoliday(day(2026, 12, 8)), true, 'Immacolata');
    assert.equal(cal.isHoliday(day(2026, 6, 2)), true, 'Festa della Repubblica');
    assert.equal(cal.isHoliday(day(2026, 10, 19)), false, 'an ordinary Monday');
});

test('days across months, years and leap days', () => {
    assert.deepEqual(cal.addDays(day(2026, 12, 31), 1), day(2027, 1, 1));
    assert.deepEqual(cal.addDays(day(2028, 2, 28), 1), day(2028, 2, 29));
    assert.deepEqual(cal.addDays(day(2026, 3, 1), -1), day(2026, 2, 28));
    assert.equal(cal.iso(day(2026, 4, 6)), '2026-04-06');
    assert.equal(cal.weekday(day(2026, 10, 19)), 1, 'Monday');
    assert.equal(cal.weekday(day(2026, 10, 25)), 0, 'Sunday');
});

test('workdays', () => {
    assert.equal(cal.isWorkday(day(2026, 10, 19)), true, 'Monday');
    assert.equal(cal.isWorkday(day(2026, 10, 24)), false, 'Saturday');
    assert.equal(cal.isWorkday(day(2026, 12, 8)), false, 'a holiday on a Tuesday');
});

test("wall-clock time in the store's time zone, whatever the visitor's", () => {
    assert.deepEqual(cal.nowIn('Europe/Rome', new Date('2026-10-19T09:00:00Z')), { y: 2026, m: 10, d: 19, h: 11, mi: 0 }, 'summer time');
    assert.deepEqual(cal.nowIn('Europe/Rome', new Date('2026-12-31T23:30:00Z')), { y: 2027, m: 1, d: 1, h: 0, mi: 30 }, 'winter time, past midnight');
    assert.deepEqual(cal.nowIn('Europe/Rome', new Date('2026-10-25T01:30:00Z')), { y: 2026, m: 10, d: 25, h: 2, mi: 30 }, 'right after the clocks go back');
});

test('day names in Italian', () => {
    assert.equal(cal.format(day(2026, 10, 19), { weekday: 'long' }), 'lunedì');
});
