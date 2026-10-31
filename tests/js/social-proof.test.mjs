// The "Qualcuno ha appena acquistato" toast's pure helpers (blocks/social-proof/feed.js).
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { ago, fresh, keyOf, meta, parseState } from '../../blocks/social-proof/feed.js';

const now = Date.UTC(2026, 9, 31, 12, 0, 0);
const secondsAgo = (s) => now / 1000 - s;

test('"2 ore fa", worked out in the browser from the payment time', () => {
    assert.equal(ago(secondsAgo(20), now), 'adesso');
    assert.equal(ago(secondsAgo(60), now), '1 minuto fa');
    assert.equal(ago(secondsAgo(12 * 60 + 30), now), '12 minuti fa');
    assert.equal(ago(secondsAgo(3600), now), '1 ora fa');
    assert.equal(ago(secondsAgo(2 * 3600 + 59 * 60), now), '2 ore fa');
    assert.equal(ago(secondsAgo(23 * 3600), now), '23 ore fa');
    assert.equal(ago(secondsAgo(26 * 3600), now), 'ieri');
    assert.equal(ago(secondsAgo(5 * 86400), now), '5 giorni fa');
    assert.equal(ago(secondsAgo(-90), now), 'adesso', 'a clock a little ahead is "adesso", not the future');
});

test('the second line: the city, then how long ago', () => {
    assert.equal(meta({ city: 'Piacenza', ts: secondsAgo(7200) }, now), 'Piacenza · 2 ore fa');
    assert.equal(meta({ city: '', ts: secondsAgo(7200) }, now), '2 ore fa');
});

test('which purchases: inside the window, with a product, not yet seen this session', () => {
    const items = [
        { product: 'Nike Dunk Low Panda', city: 'Piacenza', ts: secondsAgo(600) },
        { product: 'Calzini Hive', city: '', ts: secondsAgo(3 * 86400) },
        { product: '', city: 'Lodi', ts: secondsAgo(60) },
        { product: 'Felpa', city: 'Milano', ts: secondsAgo(20 * 86400) },
        null,
    ];
    assert.deepEqual(
        fresh(items, { days: 14, now }).map((item) => item.product),
        ['Nike Dunk Low Panda', 'Calzini Hive'],
        'the newest first, as the feed sends them; nothing without a product or older than 14 days'
    );
    assert.deepEqual(
        fresh(items, { days: 2, now }).map((item) => item.product),
        ['Nike Dunk Low Panda'],
        'a shorter window'
    );
    assert.deepEqual(
        fresh(items, { days: 14, seen: [keyOf(items[0])], now }).map((item) => item.product),
        ['Calzini Hive'],
        'one seen already'
    );
    assert.deepEqual(fresh(undefined, { now }), [], 'no feed, nothing');
});

test("the session's record survives anything sessionStorage may hold", () => {
    assert.deepEqual(parseState(null), { shown: 0, seen: [], off: false });
    assert.deepEqual(parseState('not json'), { shown: 0, seen: [], off: false });
    assert.deepEqual(parseState('null'), { shown: 0, seen: [], off: false });
    assert.deepEqual(parseState('{"shown":2,"seen":["1|A"],"off":true}'), { shown: 2, seen: ['1|A'], off: true });
    assert.deepEqual(parseState('{"shown":"x","seen":"1|A","off":"yes"}'), { shown: 0, seen: [], off: false });
});
