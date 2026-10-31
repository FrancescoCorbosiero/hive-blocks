/**
 * @hive/picker — the choice of a variation as rows of buttons, one row per
 * attribute (usually just the size). Options nobody can buy with the other
 * picks are struck through and disabled; picking an option again unpicks it.
 * The rules are @hive/variations.
 */
import { h } from '@hive/dom';
import { available, find, without } from '@hive/variations';

let uid = 0;

/**
 * @param {{choices: Array, variations: Array, preselect?: number|null, onChange: Function}} options
 *   preselect  a variation id to start from (the shop's size filter), if it can be bought
 *   onChange   (variation|null, picks) after every pick
 * @return {{node: HTMLElement, variation: Object|null, nudge: Function}}
 */
export function picker({ choices, variations, preselect = null, onChange = () => {} }) {
    const picks = {};
    const buttons = [];
    const node = h('div', { class: 'hive-picker' });

    for (const choice of choices) {
        const labelId = `hive-picker-${++uid}`;
        const row = h('div', { class: 'hive-picker__options', role: 'group', 'aria-labelledby': labelId });
        for (const option of choice.options) {
            const button = h('button', {
                type: 'button',
                class: 'hive-picker__option',
                'aria-pressed': 'false',
                'data-name': choice.name,
                'data-value': option.value,
                text: option.label,
            });
            buttons.push(button);
            row.append(button);
        }
        node.append(h('div', { class: 'hive-picker__choice' }, h('p', { class: 'hive-picker__label', id: labelId, text: choice.label }), row));
    }

    const refresh = () => {
        for (const button of buttons) {
            const { name, value } = button.dataset;
            const pressed = picks[name] === value;
            const can = available(variations, without(picks, name), name, value);
            button.setAttribute('aria-pressed', String(pressed));
            button.classList.toggle('is-unavailable', !can);
            button.disabled = !can && !pressed;
        }
    };

    node.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('.hive-picker__option') : null;
        if (!button || button.disabled) return;
        const { name, value } = button.dataset;
        if (picks[name] === value) delete picks[name];
        else picks[name] = value;
        node.classList.remove('is-invalid');
        refresh();
        onChange(find(variations, choices, picks), { ...picks });
    });

    const start = preselect ? variations.find((variation) => variation.id === preselect && variation.in_stock) : null;
    if (start) Object.assign(picks, start.attributes);
    refresh();

    return {
        node,
        get variation() {
            return find(variations, choices, picks);
        },
        /** Draws the eye to the choice still to make, and focuses its first option. */
        nudge() {
            node.classList.remove('is-invalid');
            void node.offsetWidth; // restart the animation
            node.classList.add('is-invalid');
            buttons.find((button) => !button.disabled)?.focus();
        },
    };
}
