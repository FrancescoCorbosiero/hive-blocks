/**
 * @hive/variations — which variation a shopper's picks point to, and which
 * options can still be picked. Pure: tested by tests/js/variations.test.mjs.
 *
 * choices     [{name: 'pa_taglia', label, options: [{value, label}]}]
 * variations  [{id, attributes: {pa_taglia: '42'}, in_stock, …}]
 * picks       {pa_taglia: '42'}
 *
 * A variation whose attribute is '' accepts any value for it (WooCommerce's "Any").
 */

/** Whether a variation agrees with the picks. */
export function matches(variation, picks) {
    return Object.entries(picks).every(([name, value]) => !variation.attributes?.[name] || variation.attributes[name] === value);
}

/** Whether `value` can be picked for `name`, given the other picks: some variation in stock agrees. */
export function available(variations, picks, name, value) {
    const test = { ...picks, [name]: value };
    return variations.some((variation) => variation.in_stock && matches(variation, test));
}

/** The variation the picks select once every choice is made; null before, or when none agrees. */
export function find(variations, choices, picks) {
    if (choices.some((choice) => !picks[choice.name])) return null;
    return variations.find((variation) => matches(variation, picks)) || null;
}

/** The picks without one choice. */
export function without(picks, name) {
    const rest = { ...picks };
    delete rest[name];
    return rest;
}
