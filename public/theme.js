'use strict';
(() => {
    const form = document.getElementById('theme-form');
    if (!form) return;
    const mode = document.getElementById('theme-mode');
    const root = document.documentElement;
    const stylesheet = document.getElementById('custom-theme');
    const originalMode = root.dataset.theme;
    const originalCSS = stylesheet.textContent;
    const fields = document.getElementById('custom-colors');
    const inputs = [...fields.querySelectorAll('[data-color]')];
    const originalColors = inputs.map(input => input.value);
    const properties = {background: 'paper', surface: 'card', text: 'ink', muted: 'muted', accent: 'green', border: 'line'};
    const foreground = hex => {
        const c = hex.slice(1).match(/../g).map(value => {
            const n = parseInt(value, 16) / 255;
            return n <= 0.04045 ? n / 12.92 : ((n + 0.055) / 1.055) ** 2.4;
        });
        return 0.2126*c[0] + 0.7152*c[1] + 0.0722*c[2] > 0.179 ? '#000000' : '#ffffff';
    };
    function preview() {
        root.dataset.theme = mode.value;
        fields.hidden = mode.value !== 'custom';
        const colors = Object.fromEntries(inputs.map(input => [input.dataset.color, input.value]));
        if (Object.values(colors).every(color => /^#[0-9a-f]{6}$/i.test(color))) {
            stylesheet.textContent = '[data-theme="custom"]{color-scheme:' + (foreground(colors.background) === '#ffffff' ? 'dark' : 'light') + ';' +
                Object.entries(properties).map(([key, value]) => `--${value}:${colors[key]};`).join('') +
                `--accent-ink:${foreground(colors.accent)};}`;
        }
    }
    form.addEventListener('input', preview);
    document.getElementById('theme-cancel').addEventListener('click', () => {
        mode.value = originalMode;
        inputs.forEach((input, index) => { input.value = originalColors[index]; });
        root.dataset.theme = originalMode;
        stylesheet.textContent = originalCSS;
        fields.hidden = originalMode !== 'custom';
    });
    fields.hidden = mode.value !== 'custom';
})();
