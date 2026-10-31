/**
 * Guida alle taglie — the button opens the guide (a <template> in the
 * block) in a dialog, with the same markup as the product page's size guide
 * (@hive/dialog, loaded on first use; hovering the button fetches it ahead).
 * A link to #<modalId> on the page opens it too, so the guide can sit
 * behind a link in a paragraph or a button block.
 */
import { h } from '@hive/dom';

const charts = [...document.querySelectorAll('[data-hive-size-chart]')];
const warm = () => import('@hive/dialog').then(({ preload }) => preload()).catch(() => {});

async function open(chart, trigger) {
    const template = chart.querySelector('template[data-hive-size-chart-content]');
    if (!(template instanceof HTMLTemplateElement)) return;
    const { dialog } = await import('@hive/dialog');
    const title = template.dataset.title || 'Guida alle taglie';
    const view = dialog('size-guide', { label: title });
    view.body.replaceChildren(
        h('div', { class: 'hive-guide' }, h('h2', { class: 'hive-guide__title', text: title }), h('div', { class: 'hive-guide__body' }, template.content.cloneNode(true)))
    );
    view.open(trigger);
}

for (const chart of charts) {
    const button = chart.querySelector('[data-hive-size-chart-open]');
    if (!button) continue;
    button.addEventListener('pointerover', warm, { once: true, passive: true });
    button.addEventListener('click', () => open(chart, button));
}

const page = (href) => href.split('#')[0];

document.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('a[href*="#"]') : null;
    if (!link || event.ctrlKey || event.metaKey || event.shiftKey) return;
    const url = new URL(link.href, document.baseURI);
    if (!url.hash || page(url.href) !== page(location.href)) return;
    const chart = charts.find((candidate) => `#${candidate.dataset.hiveSizeChart}` === decodeURIComponent(url.hash));
    if (!chart) return;
    event.preventDefault();
    open(chart, link);
});
