/**
 * Newsletter — the form posts to wc-ajax=hive_newsletter (Hive\Shop\Newsletter)
 * without leaving the page, and says what happened. Up to three sizes.
 */
const MAX_SIZES = 3;

for (const form of document.querySelectorAll('[data-hive-newsletter]')) {
    const status = form.querySelector('[data-hive-newsletter-status]');
    const button = form.querySelector('button[type="submit"]');
    const say = (text, error = false) => {
        status.textContent = text;
        status.classList.toggle('is-error', error);
    };

    // A fourth size unticks the oldest.
    const picked = [];
    form.querySelector('[data-hive-newsletter-sizes]')?.addEventListener('change', (event) => {
        const box = event.target;
        if (!(box instanceof HTMLInputElement)) return;
        const at = picked.indexOf(box);
        if (at !== -1) picked.splice(at, 1);
        if (box.checked) picked.push(box);
        while (picked.length > MAX_SIZES) picked.shift().checked = false;
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const email = form.elements.email;
        if (!email.value || !email.checkValidity()) {
            say('Scrivi un indirizzo email valido.', true);
            email.focus();
            return;
        }
        button.disabled = true;
        say('Un attimo…');
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json();
            say(data.message || 'Qualcosa non ha funzionato: riprova.', !data.ok);
            if (data.ok) {
                form.classList.add('is-done');
                email.value = '';
            }
        } catch {
            say('Qualcosa non ha funzionato: riprova.', true);
        } finally {
            button.disabled = false;
        }
    });
}
