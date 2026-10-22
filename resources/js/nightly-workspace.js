import { createElement, ArrowLeft, ArrowRight, ChevronDown, ChevronLeft, ChevronRight, Printer, Save, Send, ClipboardCopy, Trash2 } from 'lucide';
import { hasFormChanges } from './form-safety';

function fitWorkspace() {
    const workspace = document.querySelector('[data-nightly-workspace]');
    if (!workspace) return;
    const top = workspace.getBoundingClientRect().top + window.scrollY;
    const padding = parseFloat(getComputedStyle(workspace.parentElement).paddingBottom) || 16;
    workspace.style.setProperty('--nightly-height', `${Math.max(0, window.innerHeight - top - padding)}px`);
}

window.addEventListener('resize', fitWorkspace);

export function openNightlyDetails(row) {
    const details = row?.querySelector('[data-nightly-row-details]');
    if (!details) return;
    details.hidden = false;
    row.querySelector('[data-nightly-row-toggle]')?.setAttribute('aria-expanded', 'true');
}

export function initNightlyWorkspace() {
    const workspace = document.querySelector('[data-nightly-workspace]');
    if (!workspace || workspace.dataset.ready) return;
    workspace.dataset.ready = '1';
    fitWorkspace();
    const icons = { back: ArrowLeft, next: ArrowRight, previous: ChevronLeft, down: ChevronDown, print: Printer, save: Save, send: Send, copy: ClipboardCopy, cancel: Trash2 };
    workspace.querySelectorAll('[data-nightly-icon]').forEach((node) => node.replaceChildren(createElement(node.dataset.nightlyIcon === 'next' && node.closest('#nightly-next') ? ChevronRight : icons[node.dataset.nightlyIcon], { width: 14, height: 14 })));
    const form = workspace.querySelector('[data-nightly-count-form]');
    const listContainer = form || workspace.querySelector('[data-nightly-list-container]');
    const tabs = [...workspace.querySelectorAll('[data-nightly-view]')];
    const rows = [...workspace.querySelectorAll('[data-nightly-row]')];
    const selectView = (name) => {
        listContainer.hidden = name !== 'list';
        workspace.querySelectorAll('[data-nightly-panel]').forEach((panel) => { panel.hidden = panel.dataset.nightlyPanel !== name; });
        tabs.forEach((tab) => {
            const active = tab.dataset.nightlyView === name;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
        });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selectView(tab.dataset.nightlyView));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            selectView(tabs[next].dataset.nightlyView);
            tabs[next].focus();
        });
    });
    rows.forEach((row) => {
        row.querySelector('[data-nightly-row-toggle]').addEventListener('click', () => {
            const details = row.querySelector('[data-nightly-row-details]');
            const opening = details.hidden;
            if (opening) openNightlyDetails(row);
            else { details.hidden = true; row.querySelector('[data-nightly-row-toggle]').setAttribute('aria-expanded', 'false'); }
        });
        if (row.querySelector('[role="alert"]')) openNightlyDetails(row);
    });
    const refresh = () => {
        let counted = 0;
        rows.forEach((row) => {
            const input = row.querySelector('input[name$="[quantity]"]');
            const quantity = input ? input.value : row.dataset.quantity;
            const low = row.querySelector('[name$="[is_low]"]')?.checked ?? row.dataset.low === '1';
            const state = quantity === '' ? 'unchecked' : Number(quantity) === 0 ? 'empty' : low ? 'low' : 'counted';
            const badge = row.querySelector('[data-nightly-state]');
            badge.dataset.state = state;
            badge.textContent = { unchecked: 'Belum dicek', empty: 'Kosong', low: 'Menipis', counted: 'Diperiksa' }[state];
            if (quantity !== '') counted += 1;
            if (input) input.title = input.readOnly ? 'Jumlah dari hitungan batch. Buka Rincian untuk mengubahnya.' : 'Isi jumlah fisik; kosongkan jika belum diperiksa.';
        });
        const label = workspace.querySelector('[data-nightly-progress-label]');
        if (label) label.textContent = `${counted} / ${rows.length} obat diperiksa`;
        const progress = workspace.querySelector('[data-nightly-progress]');
        if (progress) progress.value = counted;
        const state = workspace.querySelector('[data-nightly-save-state]');
        if (state) state.textContent = hasFormChanges(form) ? 'Belum disimpan' : 'Isian tersimpan';
        const next = workspace.querySelector('[data-next-unchecked]');
        if (next) next.disabled = counted === rows.length;
    };
    workspace.addEventListener('input', refresh);
    workspace.addEventListener('change', refresh);
    // Form safety records its baseline later in the same initialization cycle.
    queueMicrotask(refresh);
    workspace.querySelector('[data-next-unchecked]')?.addEventListener('click', () => {
        const row = rows.find((item) => item.querySelector('input[name$="[quantity]"]')?.value === '');
        if (!row) return;
        selectView('list');
        document.dispatchEvent(new CustomEvent('nightly:reveal-row', { detail: row }));
        const input = row.querySelector('input[name$="[quantity]"]');
        input.focus();
        input.scrollIntoView({ block: 'nearest' });
    });
    form?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || !(event.target instanceof HTMLInputElement) || event.target.closest('dialog')) return;
        event.preventDefault();
        const inputs = [...form.querySelectorAll('[data-nightly-row] input[name$="[quantity]"]')].filter((input) => !input.closest('[data-nightly-row]').hidden && !input.readOnly);
        const index = inputs.indexOf(event.target);
        if (index >= 0) inputs[index + 1]?.focus();
    });
    workspace.querySelector('[data-nightly-copy]')?.addEventListener('click', async () => {
        const summary = workspace.querySelector('#nightly-summary');
        try {
            await navigator.clipboard.writeText(summary.value);
            workspace.querySelector('[data-nightly-copy-label]').textContent = 'Tersalin';
        } catch { summary.focus(); summary.select(); }
    });
}
