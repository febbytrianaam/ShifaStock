import { createElement, Eye, EyeOff } from 'lucide';

export function initPasswordVisibility() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        if (button.dataset.ready) return;
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (!(input instanceof HTMLInputElement)) return;
        button.dataset.ready = '1';

        const setVisible = (visible) => {
            input.type = visible ? 'text' : 'password';
            const label = visible ? 'Sembunyikan password' : 'Tampilkan password';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', label);
            button.title = label;
            button.replaceChildren(createElement(visible ? EyeOff : Eye, { width: 18, height: 18, 'aria-hidden': 'true' }));
        };

        setVisible(false);
        button.hidden = false;
        button.addEventListener('click', () => setVisible(input.type === 'password'));
        input.form?.addEventListener('submit', () => setVisible(false));
    });
}
