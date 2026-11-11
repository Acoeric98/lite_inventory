'use strict';
(() => {
    const details = document.getElementById('new-inventory');
    if (!details) return;
    const form = details.querySelector('form');
    details.querySelector('summary').addEventListener('click', event => {
        event.preventDefault();
        const name = window.prompt('Új raktár neve:');
        if (name === null) return;
        if (!name.trim() || [...name.trim()].length > 120) {
            window.alert('A raktár neve 1–120 karakter legyen.');
            return;
        }
        const location = window.prompt('Helyszín (nem kötelező):', '');
        if (location === null) return;
        form.elements.namedItem('name').value = name.trim();
        form.elements.namedItem('location').value = location.trim();
        form.requestSubmit(form.querySelector('button[value="create_inventory"]'));
    });
})();
