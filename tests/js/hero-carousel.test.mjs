// The carousel's pure helpers (blocks/hero-carousel/carousel.js).
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { rotates, wrap } from '../../blocks/hero-carousel/carousel.js';

test('a step wraps around at either end', () => {
    assert.equal(wrap(1, 3), 1, 'the next slide');
    assert.equal(wrap(3, 3), 0, 'past the last: the first');
    assert.equal(wrap(-1, 3), 2, 'before the first: the last');
    assert.equal(wrap(7, 3), 1, 'any distance');
    assert.equal(wrap(2, 0), 0, 'no slides');
});

test('autoplay moves on only when nothing holds it', () => {
    const free = { delay: 6000, reduced: false, stopped: false, hovered: false, focused: false, visible: true, hidden: false };
    assert.equal(rotates(free), true, 'on, in view, nobody on it');
    assert.equal(rotates({ ...free, delay: 0 }), false, 'autoplay off');
    assert.equal(rotates({ ...free, reduced: true }), false, 'the visitor prefers reduced motion');
    assert.equal(rotates({ ...free, stopped: true }), false, 'stopped with its button');
    assert.equal(rotates({ ...free, hovered: true }), false, 'the pointer is over it');
    assert.equal(rotates({ ...free, focused: true }), false, 'the keyboard is in it');
    assert.equal(rotates({ ...free, visible: false }), false, 'out of view');
    assert.equal(rotates({ ...free, hidden: true }), false, 'a background tab');
});
