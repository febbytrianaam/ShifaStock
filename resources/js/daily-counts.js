import Swal from 'sweetalert2';
import { createElement, Plus, X } from 'lucide';
import { hasFormChanges } from './form-safety';

export function initDailyCounts() {
    initNightlyStart();
    initNightlyAdd();
    initNightlyPrint();
    document.querySelectorAll('[data-nightly-row]').forEach((row) => {
        const inputs = [...row.querySelectorAll('[data-nightly-batch-count]')];
        const total = row.querySelector('input[name$="[quantity]"]');
        if (!total || !inputs.length || row.dataset.batchReady) return;
        row.dataset.batchReady = '1';
        const sync = () => {
            const filled = inputs.filter((input) => input.value !== '');
            total.readOnly = filled.length > 0;
            if (filled.length) {
                total.value = filled.every((input) => input.validity.valid) ? String(filled.reduce((sum, input) => sum + Number(input.value), 0)) : '';
                total.dataset.batchDerived = '1';
            } else if (total.dataset.batchDerived) total.value = '';
        };
        inputs.forEach((input) => input.addEventListener('input', sync));
        sync();
    });
}

function initNightlyPrint() {
    document.querySelectorAll('[data-nightly-print]').forEach((link) => {
        if (link.dataset.ready) return;
        link.dataset.ready = '1';
        link.addEventListener('click', (event) => {
            const form = document.querySelector('[data-nightly-count-form]');
            if (!hasFormChanges(form)) return;
            event.preventDefault();
            void Swal.fire({ title: 'Simpan isian sebelum mencetak', text: 'Masih ada perubahan yang belum disimpan. Tekan Simpan Draf agar hasil hitung terbaru masuk ke laporan cetak.', icon: 'warning', confirmButtonText: 'Kembali ke isian' }).then(() => {
                const save = form.querySelector('button[value="save"]');
                save?.focus();
                save?.scrollIntoView({ block: 'center' });
            });
        });
    });
}

function initNightlyAdd() {
    const dialog = document.querySelector('[data-nightly-add-dialog]');
    if (!dialog || dialog.dataset.ready) return;
    dialog.dataset.ready = '1';
    document.querySelector('[data-nightly-add-icon]')?.replaceChildren(createElement(Plus, { width: 14, height: 14 }));
    dialog.querySelector('[data-nightly-close-icon]')?.replaceChildren(createElement(X, { width: 16, height: 16 }));
    const search = dialog.querySelector('#nightly-add-search');
    const options = [...dialog.querySelectorAll('[data-nightly-add-option]')];
    const sync = () => {
        const term = search.value.trim().toLocaleLowerCase('id');
        for (const option of options) option.hidden = !option.dataset.search.includes(term);
        dialog.querySelector('[data-nightly-add-empty]').hidden = options.some((option) => !option.hidden);
        dialog.querySelector('[data-nightly-add-count]').textContent = `${dialog.querySelectorAll('input[type="checkbox"]:checked').length} obat dipilih`;
    };
    const reset = () => {
        search.value = '';
        for (const option of options) option.querySelector('input').checked = false;
        sync();
    };
    document.querySelector('[data-nightly-add-open]')?.addEventListener('click', () => { sync(); dialog.showModal(); search.focus(); });
    dialog.querySelectorAll('[data-nightly-add-close]').forEach((button) => button.addEventListener('click', () => { reset(); dialog.close(); }));
    dialog.addEventListener('cancel', reset);
    dialog.addEventListener('keydown', (event) => { if (event.key === 'Enter' && event.target instanceof HTMLInputElement) event.preventDefault(); });
    search.addEventListener('input', sync);
    dialog.addEventListener('change', sync);
    sync();
    if (dialog.hasAttribute('data-reopen')) { dialog.showModal(); search.focus(); }
}

function initNightlyStart() {
    const form = document.querySelector('[data-nightly-start-form]');
    if (!form || form.dataset.startReady) return;
    form.dataset.startReady = '1';
    const picker = form.querySelector('[data-nightly-medicine-picker]');
    const options = [...form.querySelectorAll('[data-nightly-medicine-option]')];
    const search = form.querySelector('#nightly-medicine-search');
    const sync = () => {
        const selected = form.querySelector('[name="scope"]:checked')?.value === 'selected';
        picker.hidden = !selected;
        const term = search.value.trim().toLocaleLowerCase('id');
        let visible = 0;
        for (const option of options) {
            option.hidden = !option.dataset.search.includes(term);
            if (!option.hidden) visible += 1;
            option.querySelector('input').disabled = !selected;
        }
        form.querySelector('[data-nightly-no-matches]').hidden = visible > 0;
        form.querySelector('[data-nightly-selected-count]').textContent = `${form.querySelectorAll('[name="medicine_ids[]"]:checked').length} obat dipilih`;
    };
    form.addEventListener('change', sync);
    search.addEventListener('input', sync);
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        if (form.querySelector('[name="scope"]:checked')?.value !== 'selected' || form.querySelector('[name="medicine_ids[]"]:checked')) return;
        event.preventDefault();
        void Swal.fire({ title: 'Pilih obat terlebih dahulu', text: 'Centang sedikitnya satu obat yang akan diperiksa.', icon: 'warning', confirmButtonText: 'Pilih obat' }).then(() => search.focus());
    });
    sync();
}

export function prepareDailyCounts(form, submitter) {
    const dialog = form.querySelector('[data-nightly-add-dialog]');
    if (submitter?.value === 'add' && !dialog?.querySelector('input[type="checkbox"]:checked')) {
        dialog?.close();
        void Swal.fire({ title: 'Pilih obat tambahan', text: 'Centang sedikitnya satu obat yang akan ditambahkan ke draf.', icon: 'warning', confirmButtonText: 'Pilih obat' }).then(() => {
            dialog?.showModal();
            dialog?.querySelector('input[type="search"]')?.focus();
        });
        return false;
    }
    const payload = {};
    const errors = [];
    const rows = [...form.querySelectorAll('[data-nightly-row]')];
    let checked = 0;
    rows.forEach((row) => {
        const inputs = [...row.querySelectorAll('[data-nightly-batch-count]')];
        const total = row.querySelector('input[name$="[quantity]"]');
        if (!total) return;
        const label = row.querySelector('b')?.textContent || 'Obat';
        const filled = inputs.filter((input) => input.value !== '');
        const problem = (input, reason) => errors.push({ input, label, reason });
        for (const input of filled) {
            if (!input.validity.valid) problem(input, 'Jumlah batch harus bilangan bulat 0 atau lebih.');
            (payload[input.dataset.itemId] ||= {})[input.dataset.batchId] = input.value;
        }
        if (submitter?.value !== 'submit') return;
        if (total.value === '') return;
        checked += 1;
        if (!total.validity.valid) problem(total, 'Jumlah aktual harus bilangan bulat 0 atau lebih.');
        const unit = row.querySelector('[data-nightly-unit]');
        if (unit && !unit.value) problem(unit, 'Pilih satuan hitung yang sesuai Data Obat.');
        if (filled.length || total.value !== '' && Number(total.value) !== Number(row.dataset.systemQuantity)) {
            const missing = inputs.find((input) => Number(input.dataset.systemQuantity) > 0 && input.value === '');
            if (missing) problem(missing, 'Buka Hasil hitung per batch dan isi semua batch yang memiliki stok. Isi 0 hanya untuk batch yang sudah diperiksa dan kosong.');
            else if (!filled.length) problem(total, 'Jumlah aktual berbeda dari stok sistem. Isi Hasil hitung per batch agar selisih masuk ke batch yang benar. Jika batch belum tersedia, catat melalui Stok Awal atau Penerimaan.');
        }
    });
    form.querySelector('[data-nightly-batch-payload]').value = JSON.stringify(payload);
    if (!errors.length && submitter?.value === 'submit' && checked === 0) {
        void Swal.fire({ title: 'Belum ada obat yang diperiksa', text: 'Isi hasil hitung sedikitnya satu obat sebelum mengirim. Angka 0 berarti sudah diperiksa dan benar-benar kosong. Jika belum selesai, pilih Simpan Draf.', icon: 'warning', confirmButtonText: 'Perbaiki isian' });
        return false;
    }
    if (!errors.length && submitter?.value === 'submit' && checked < rows.length && submitter.dataset.partialConfirmed !== '1') {
        const unchecked = rows.length - checked;
        void Swal.fire({
            title: `${unchecked} obat belum diperiksa`,
            text: `${checked} dari ${rows.length} obat sudah diperiksa. Kirim hanya ${checked} hasil tersebut ke kepala apoteker? ${unchecked} obat lainnya tetap belum diperiksa, tidak dianggap kosong, dan stoknya tidak disesuaikan.`,
            icon: 'warning', showCancelButton: true, showDenyButton: true, focusCancel: true,
            confirmButtonText: `Kirim ${checked} obat`, denyButtonText: 'Simpan Draf', cancelButtonText: 'Kembali',
        }).then((result) => {
            if (result.isDenied) {
                const save = form.querySelector('button[value="save"]');
                if (save) form.requestSubmit(save);
            } else if (result.isConfirmed) {
                const confirmation = form.querySelector('[data-nightly-confirm-partial]');
                confirmation.value = '1';
                submitter.dataset.partialConfirmed = '1';
                try { form.requestSubmit(submitter); }
                finally { confirmation.value = '0'; delete submitter.dataset.partialConfirmed; }
            }
        });
        return false;
    }
    if (!errors.length) return true;
    if (dialog?.open) dialog.close();
    const content = document.createElement('div');
    content.className = 'text-left text-sm';
    const intro = document.createElement('p');
    intro.textContent = `${new Set(errors.map((error) => error.input.closest('[data-nightly-row]'))).size} obat perlu diperbaiki. Isian Anda belum dihapus.`;
    content.append(intro);
    const list = document.createElement('ul');
    list.style.cssText = 'max-height:32vh;overflow:auto;padding-left:1.25rem;list-style:disc;margin-top:12px';
    const groups = new Map();
    for (const error of errors) {
        if (!groups.has(error.reason)) groups.set(error.reason, new Set());
        groups.get(error.reason).add(error.label);
    }
    for (const [reason, names] of groups) {
        const item = document.createElement('li');
        const heading = document.createElement('strong');
        heading.textContent = `${names.size} obat: ${reason}`;
        const examples = document.createElement('p');
        examples.textContent = [...names].slice(0, 3).join(', ') + (names.size > 3 ? `, dan ${names.size - 3} obat lainnya.` : '.');
        item.append(heading, examples);
        list.append(item);
    }
    content.append(list);
    void Swal.fire({ title: 'Periksa hitungan stok', html: content, icon: 'warning', confirmButtonText: 'Perbaiki isian' }).then(() => {
        const input = errors[0].input;
        const row = input.closest('[data-nightly-row]');
        document.dispatchEvent(new CustomEvent('nightly:reveal-row', { detail: row }));
        row.hidden = false;
        const details = row.querySelector('[data-nightly-batch-details]');
        if (details) details.open = true;
        input.focus();
        input.scrollIntoView({ block: 'center', inline: 'center' });
    });
    return false;
}
