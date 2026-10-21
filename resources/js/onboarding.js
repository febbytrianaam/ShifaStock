import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { createElement, CircleHelp } from 'lucide';

let activeTour;

export function initOnboarding() {
    const configElement = document.querySelector('[data-onboarding-config]');
    if (!configElement || configElement.dataset.ready) return;
    configElement.dataset.ready = '1';
    const config = JSON.parse(configElement.textContent);
    const restart = document.querySelector('[data-tour-restart]');
    restart?.querySelector('[data-tour-help-icon]')?.replaceWith(createElement(CircleHelp, { width: 18, height: 18, 'aria-hidden': 'true' }));

    const start = () => {
        if (activeTour?.isActive()) return;
        const sidebar = document.getElementById('sidebar');
        const wasOpen = sidebar && !sidebar.classList.contains('-translate-x-full');
        let saving = false;
        const setSidebar = (open) => {
            if (!sidebar || !matchMedia('(max-width: 767px)').matches) return;
            sidebar.classList.toggle('-translate-x-full', !open);
            document.getElementById('sidebar-backdrop').hidden = !open;
            document.getElementById('sidebar-toggle').setAttribute('aria-expanded', String(open));
            // The tour measures the target immediately after it becomes visible.
            sidebar.style.transition = 'none';
        };
        const steps = config.steps.filter((step) => !step.element || document.querySelector(step.element)).map((step) => {
            const popover = { title: step.title, description: step.description, side: 'right', align: 'start' };
            // A targetless welcome step uses Driver's centered dialog, not its edge fallback.
            if (!step.element) return { popover, onHighlightStarted: () => setSidebar(false) };
            return {
                element: () => {
                    setSidebar(step.element.startsWith('[data-tour-menu='));
                    const element = document.querySelector(step.element);
                    if (element.closest('#sidebar')) element.scrollIntoView({ block: 'nearest' });
                    return element;
                },
                popover,
            };
        });
        const finish = async (outcome) => {
            if (saving) return;
            saving = true;
            const popover = activeTour.getState().popover;
            const buttons = [...popover.wrapper.querySelectorAll('button')];
            buttons.forEach((button) => { button.disabled = true; });
            popover.wrapper.querySelector('[data-tour-error]')?.remove();
            try {
                const response = await fetch(config.endpoint, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.token },
                    body: JSON.stringify({ outcome }),
                });
                if (!response.ok || !(await response.json()).saved) throw new Error('save');
                activeTour.destroy();
            } catch {
                const error = document.createElement('p');
                error.dataset.tourError = '';
                error.setAttribute('role', 'alert');
                error.textContent = 'Panduan belum tersimpan. Periksa koneksi, lalu coba lagi.';
                popover.description.after(error);
            } finally {
                saving = false;
                buttons.forEach((button) => { button.disabled = false; });
            }
        };
        activeTour = driver({
            steps, animate: false, overlayOpacity: 0.68, overlayColor: '#111827',
            stagePadding: 5, stageRadius: 6, popoverClass: 'shifa-tour',
            disableActiveInteraction: true, allowClose: false, overlayClickBehavior: 'none',
            showButtons: ['previous', 'next'], showProgress: true, progressText: '{{current}} / {{total}}',
            nextBtnText: 'Selanjutnya', prevBtnText: 'Kembali', doneBtnText: 'Selesai',
            onDoneClick: () => { void finish('completed'); },
            onPopoverRender: (popover) => {
                const skip = document.createElement('button');
                skip.type = 'button';
                skip.className = 'shifa-tour-skip';
                skip.textContent = 'Lewati';
                skip.addEventListener('click', () => { void finish('skipped'); });
                popover.footer.prepend(skip);
            },
            onDestroyed: () => {
                setSidebar(wasOpen);
                if (sidebar) sidebar.style.transition = '';
                restart?.focus({ preventScroll: true });
            },
        });
        activeTour.drive();
        document.fonts?.ready.then(() => {
            if (activeTour?.isActive()) activeTour.refresh();
        });
    };
    restart?.addEventListener('click', start);
    if (config.autoStart) start();
}

document.addEventListener('livewire:navigating', () => activeTour?.destroy());
