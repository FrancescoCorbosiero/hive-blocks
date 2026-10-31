// The promo popup's rules (blocks/promo-modal/promo.js): the delay, exit intent, links that open it.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { delayMs, isExitIntent, opensPromo, seenKey } from '../../blocks/promo-modal/promo.js';

test('the delay is in milliseconds; old blocks that saved seconds still work', () => {
    assert.equal(delayMs(5000), 5000);
    assert.equal(delayMs(0), 0, 'at once');
    assert.equal(delayMs(8), 8000, 'golden-hive-blocks once saved seconds: 8 means 8 s');
    assert.equal(delayMs(-3), 0);
    assert.equal(delayMs('x'), 0);
    assert.equal(delayMs(10 * 60_000), 120_000, 'two minutes at most');
});

test('exit intent: the mouse leaves through the top of the page', () => {
    assert.equal(isExitIntent({ relatedTarget: null, clientY: -2 }), true);
    assert.equal(isExitIntent({ relatedTarget: null, clientY: 0 }), true);
    assert.equal(isExitIntent({ relatedTarget: null, clientY: 300 }), false, 'out through a side or the bottom');
    assert.equal(isExitIntent({ relatedTarget: {}, clientY: -2 }), false, 'onto another element');
});

test('a link to #id opens it, on this page only', () => {
    const page = 'https://shop.test/negozio/?orderby=price#top';
    assert.equal(opensPromo('#promo-popup', 'promo-popup', page), true);
    assert.equal(opensPromo('https://shop.test/negozio/?orderby=price#promo-popup', 'promo-popup', page), true);
    assert.equal(opensPromo('/negozio/#promo-popup', 'promo-popup', page), false, 'another address (no query): another page');
    assert.equal(opensPromo('https://shop.test/chi-siamo/#promo-popup', 'promo-popup', page), false);
    assert.equal(opensPromo('#altro', 'promo-popup', page), false);
    assert.equal(opensPromo('', 'promo-popup', page), false);
});

test('one key per popup, so a new popup (a new id) shows again', () => {
    assert.equal(seenKey('promo-popup'), 'hive_promo_promo-popup');
    assert.notEqual(seenKey('saldi-estivi'), seenKey('promo-popup'));
});
