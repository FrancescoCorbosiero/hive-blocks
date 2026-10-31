// @hive/drop (assets/modules/drop.js): a drop's countdown.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { parts, status } from '@hive/drop';

const H = 3600e3;

test('milliseconds split into days, hours, minutes, seconds', () => {
    assert.deepEqual(parts(((2 * 24 + 3) * 60 + 4) * 60e3 + 5e3), { d: 2, h: 3, m: 4, s: 5 });
    assert.deepEqual(parts(-5), { d: 0, h: 0, m: 0, s: 0 }, 'the past is zero');
});

test('the status line', () => {
    assert.equal(status(3 * 24 * H + 1000), 'Esce tra 3 giorni');
    assert.equal(status(24 * H + 1000), 'Esce tra 1 giorno');
    assert.equal(status(5 * H + 20 * 60e3), 'Esce tra 5 h 20 min');
    assert.equal(status(12 * 60e3 + 5e3), 'Esce tra 12 min 05 s');
    assert.equal(status(45e3), 'Esce tra 45 s');
    assert.equal(status(0), 'Disponibile ora');
});
