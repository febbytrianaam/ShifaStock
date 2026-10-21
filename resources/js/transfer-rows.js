import Swal from 'sweetalert2';
import { createElement, Plus, X } from 'lucide';

export function initTransferRows({ initSearchableSelects, initStockEntryContexts }) {
    document.querySelectorAll('[data-transfer-form]').forEach((form) => {
        if (form.dataset.transferReady) return;
        form.dataset.transferReady = '1';
        const table = form.querySelector('[data-transfer-items]');
        const add = form.querySelector('[data-transfer-add-row]');
        const rows = () => [...table.querySelectorAll('[data-transfer-row]')];
        let confirming = false;
        const initializeRows = () => {
            initSearchableSelects(form);
            initStockEntryContexts(form);
            rows().forEach((row, index) => {
                row.querySelector('[data-transfer-row-label]').textContent = `Baris ${index + 1}`;
                row.querySelector('[data-transfer-remove-row]').disabled = rows().length === 1;
                row.querySelector('[data-transfer-remove-icon]')?.replaceWith(createElement(X, { width: 16, height: 16, 'aria-hidden': 'true' }));
                const medicine = row.querySelector('[data-transfer-medicine]');
                const sync = () => { row.querySelector('[data-transfer-unit]').textContent = medicine.selectedOptions[0]?.dataset.stockUnit || ''; };
                if (!row.dataset.unitReady) {
                    row.dataset.unitReady = '1';
                    medicine.addEventListener('change', sync);
                }
                sync();
            });
            add.disabled = rows().length >= 50;
        };
        add.querySelector('[data-transfer-add-icon]')?.replaceWith(createElement(Plus, { width: 14, height: 14, 'aria-hidden': 'true' }));
        initializeRows();
        add.addEventListener('click', () => {
            if (rows().length >= 50) return;
            const index = Math.max(-1, ...rows().map((row) => Number(row.dataset.transferIndex))) + 1;
            const fragment = form.querySelector('[data-transfer-template]').content.cloneNode(true);
            fragment.querySelectorAll('*').forEach((element) => {
                for (const attribute of ['name', 'id', 'for', 'data-depends-on', 'data-transfer-index', 'data-medicine', 'data-quantity', 'data-batch']) {
                    if (element.hasAttribute(attribute)) element.setAttribute(attribute, element.getAttribute(attribute).replaceAll('__INDEX__', String(index)));
                }
            });
            table.append(fragment);
            initializeRows();
            rows().at(-1).querySelector('.searchable-select-input')?.focus();
        });
        table.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-transfer-remove-row]');
            if (!remove || rows().length === 1) return;
            remove.closest('[data-transfer-row]').remove();
            initializeRows();
        });
        form.addEventListener('submit', async (event) => {
            if (event.defaultPrevented) return;
            const confirmed = form.dataset.transferConfirmed === '1';
            delete form.dataset.transferConfirmed;
            const errors = [];
            let count = 0;
            rows().forEach((row, index) => {
                const medicine = row.querySelector('[data-transfer-medicine]');
                const quantity = row.querySelector('[data-transfer-quantity]');
                const batch = row.querySelector('[data-transfer-batch]');
                const query = medicine.closest('.searchable-select')?.querySelector('input')?.value.trim();
                if (!medicine.value && !quantity.value && !batch.value && !query) return;
                count++;
                if (!medicine.value) errors.push({ field: medicine, message: `Baris ${index + 1}: pilih obat dari daftar.` });
                if (!quantity.value || !quantity.checkValidity()) errors.push({ field: quantity, message: `Baris ${index + 1}: isi jumlah lebih dari 0, maksimal empat angka di belakang koma.` });
                const batchQuery = batch.closest('.searchable-select')?.querySelector('input')?.value.trim();
                if (!batch.value && batchQuery && batchQuery !== batch.dataset.emptyLabel) errors.push({ field: batch, message: `Baris ${index + 1}: pilih batch dari daftar, atau kosongkan untuk otomatis.` });
            });
            if (!count) errors.push({ field: rows()[0].querySelector('[data-transfer-medicine]'), message: 'Isi minimal satu baris obat untuk ditransfer.' });
            if (form.querySelector('#transfer-source').value === form.querySelector('#transfer-target').value) errors.unshift({ field: form.querySelector('#transfer-target'), message: 'Lokasi tujuan harus berbeda dari lokasi sumber.' });
            if (errors.length) {
                event.preventDefault();
                await Swal.fire({ title: 'Periksa item transfer', text: errors.map((error) => error.message).join('\n'), icon: 'warning', confirmButtonText: 'Perbaiki isian' });
                const field = errors[0].field;
                field.closest('td')?.scrollIntoView({ block: 'center', inline: 'center' });
                (field.closest('.searchable-select')?.querySelector('input') || field).focus();
                return;
            }
            if (confirmed) {
                form.querySelector('button[type="submit"]').disabled = true;
                return;
            }
            event.preventDefault();
            if (confirming) return;
            confirming = true;
            const result = await Swal.fire({ title: `Simpan transfer ${count} baris obat?`, text: 'Stok lokasi sumber akan berkurang dan stok tujuan bertambah. Semua item disimpan bersama; jika ada yang gagal, stok tidak dipindahkan.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, simpan transfer', cancelButtonText: 'Periksa kembali' });
            confirming = false;
            if (result.isConfirmed) {
                form.dataset.transferConfirmed = '1';
                form.requestSubmit(event.submitter);
            }
        });
    });
}
