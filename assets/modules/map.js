/**
 * @hive/map — the map that loads when asked (Hive\Blocks\Map): "Mostra la
 * mappa" puts the iframe waiting in its <template> in place of the cover,
 * and the focus goes to the map. Until that click nothing reaches Google.
 * Loaded by the Contatti and Mappa blocks, imported by Vieni in negozio's.
 */

function load(button) {
    const box = button.closest('[data-hive-map]');
    const template = box?.querySelector('template[data-hive-map-frame]');
    if (!box || !template) return;
    box.append(template.content.cloneNode(true));
    template.remove();
    for (const cover of box.querySelectorAll('[data-hive-map-cover]')) cover.remove();
    box.classList.add('is-loaded');
    // The button is gone: keyboard and screen reader users go on from the map.
    box.querySelector('iframe')?.focus();
}

document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-hive-map-load]') : null;
    if (button) load(button);
});
