import Swal from 'sweetalert2';

const baselines = new Map();
const restoredForms = new WeakSet();
let leaving = false;
let prompting = false;

function snapshot(form) {
    return JSON.stringify([...form.elements]
        .filter((field) => (field.name || field.hasAttribute('data-nightly-batch-count')) && !['hidden', 'submit', 'button'].includes(field.type) && !field.disabled)
        .map((field) => [field.name || `batch:${field.dataset.itemId}:${field.dataset.batchId}`, ['checkbox', 'radio'].includes(field.type) ? field.checked : field.value]));
}

export function initFormSafety() {
    const restoredFields = JSON.parse(document.querySelector('[data-restored-input-fields]')?.textContent || '[]');
    for (const form of baselines.keys()) if (!form.isConnected) baselines.delete(form);
    document.querySelectorAll('form').forEach((form) => {
        if (baselines.has(form) || new URL(form.action, location.href).pathname === '/login') return;
        if (form.method.toLowerCase() !== 'post' && !form.hasAttribute('wire:submit')) return;
        if (!form.querySelector('input:not([type="hidden"]):not([readonly]), select, textarea:not([readonly])')) return;
        baselines.set(form, snapshot(form));
        if ([...form.elements].some((field) => field.name && field.type !== 'hidden' && restoredFields.includes(field.name.split('[')[0]))) restoredForms.add(form);
    });
    leaving = false;
}

export function resetFormBaseline(form) {
    if (form && baselines.has(form)) {
        baselines.set(form, snapshot(form));
        restoredForms.delete(form);
    }
}

export function hasFormChanges(form) {
    return Boolean(form && baselines.has(form) && (restoredForms.has(form) || snapshot(form) !== baselines.get(form)));
}

export function allowFormNavigation() {
    leaving = true;
    setTimeout(() => { leaving = false; }, 5000);
}

export function cancelFormNavigation() {
    leaving = false;
}

function hasUnsavedChanges(except = null) {
    return [...baselines].some(([form, baseline]) => form !== except && form.isConnected && (restoredForms.has(form) || snapshot(form) !== baseline));
}

async function confirmLeave(proceed) {
    if (prompting) return;
    prompting = true;
    try {
        const result = await Swal.fire({
            title: 'Ada isian yang belum disimpan',
            text: 'Isian akan hilang jika meninggalkan halaman ini. Simpan terlebih dahulu agar bisa dilanjutkan nanti.',
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Keluar tanpa menyimpan', cancelButtonText: 'Tetap di halaman',
            confirmButtonColor: '#e11d48', reverseButtons: true,
        });
        if (result.isConfirmed) {
            allowFormNavigation();
            proceed();
        }
    } finally {
        prompting = false;
    }
}

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || leaving || !hasUnsavedChanges()) return;
    if (link.target && link.target !== '_self' || link.hasAttribute('download') || link.getAttribute('href').startsWith('#')) return;
    const url = new URL(link.href, location.href);
    if (!['http:', 'https:'].includes(url.protocol)) return;
    if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    void confirmLeave(() => location.assign(url.href));
}, true);

document.addEventListener('livewire:navigate', (event) => {
    if (leaving || !hasUnsavedChanges()) return;
    event.preventDefault();
    void confirmLeave(() => window.Livewire.navigate(event.detail.url.href));
});

document.addEventListener('submit', (event) => {
    if (event.defaultPrevented || leaving) return;
    if (hasUnsavedChanges(event.target)) {
        event.preventDefault();
        const submitter = event.submitter;
        void confirmLeave(() => event.target.requestSubmit(submitter || undefined));
        return;
    }
    if (!event.target.hasAttribute('wire:submit')) allowFormNavigation();
});

window.addEventListener('beforeunload', (event) => {
    if (leaving || !hasUnsavedChanges()) return;
    event.preventDefault();
    event.returnValue = '';
});
