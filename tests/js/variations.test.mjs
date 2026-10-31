// @hive/variations (assets/modules/variations.js).
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { available, find, matches, without } from '@hive/variations';

const size = (id, value, inStock = true) => ({ id, attributes: { pa_taglia: value }, in_stock: inStock });
const sizes = [size(1, '40'), size(2, '41', false), size(3, '42')];
const choices = [{ name: 'pa_taglia', options: [{ value: '40' }, { value: '41' }, { value: '42' }] }];

test('a variation agrees with the picks', () => {
    assert.equal(matches(sizes[0], { pa_taglia: '40' }), true);
    assert.equal(matches(sizes[0], { pa_taglia: '42' }), false);
    assert.equal(matches({ attributes: { pa_taglia: '' } }, { pa_taglia: '42' }), true, '"Any" accepts every value');
});

test('only sizes in stock can be picked', () => {
    assert.equal(available(sizes, {}, 'pa_taglia', '40'), true);
    assert.equal(available(sizes, {}, 'pa_taglia', '41'), false, 'sold out');
    assert.equal(available(sizes, {}, 'pa_taglia', '45'), false, 'not offered');
});

test('the picks select a variation once every choice is made', () => {
    assert.equal(find(sizes, choices, {}), null);
    assert.equal(find(sizes, choices, { pa_taglia: '42' }).id, 3);
});

test('two attributes: each option depends on the other pick', () => {
    const variations = [
        { id: 1, attributes: { pa_taglia: '40', pa_colore: 'nero' }, in_stock: true },
        { id: 2, attributes: { pa_taglia: '42', pa_colore: 'bianco' }, in_stock: true },
        { id: 3, attributes: { pa_taglia: '42', pa_colore: 'nero' }, in_stock: false },
    ];
    const two = [{ name: 'pa_taglia' }, { name: 'pa_colore' }];
    assert.equal(available(variations, { pa_colore: 'nero' }, 'pa_taglia', '42'), false, '42 in black is sold out');
    assert.equal(available(variations, { pa_colore: 'bianco' }, 'pa_taglia', '42'), true);
    assert.equal(find(variations, two, { pa_taglia: '42' }), null, 'one choice still to make');
    assert.equal(find(variations, two, { pa_taglia: '42', pa_colore: 'bianco' }).id, 2);
});

test('without() drops one pick and keeps the others', () => {
    const picks = { pa_taglia: '42', pa_colore: 'nero' };
    assert.deepEqual(without(picks, 'pa_taglia'), { pa_colore: 'nero' });
    assert.deepEqual(picks, { pa_taglia: '42', pa_colore: 'nero' }, 'the picks are left alone');
});
