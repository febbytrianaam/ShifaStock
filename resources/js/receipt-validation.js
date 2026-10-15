import Swal from 'sweetalert2';

const fields = {
    medicine_id: 'Pilih obat dari daftar.',
    batch_number: 'Isi nomor batch.',
    expiry_date: 'Isi tanggal kedaluwarsa yang valid.',
    input_quantity: 'Isi jumlah faktur lebih dari 0.',
    input_unit_name: 'Pilih satuan faktur.',
    conversion_factor: 'Isi jumlah satuan stok dalam 1 kemasan, lebih dari 0.',
    quantity: 'Jumlah stok belum dapat dihitung. Periksa jumlah faktur dan isi kemasan.',
};

function fieldError(field, message) {
    field.setAttribute('aria-invalid', message ? 'true' : 'false');
    const cell = field.closest('td');
    cell.querySelectorAll('[data-receipt-field-error]').forEach((error) => {
        if (error.dataset.fieldName === field.name || error.dataset.errorName === field.name.replace(/\[(\w+)\]/g, '.$1')) error.remove();
    });
    if (!message) return;
    const error = document.createElement('p');
    error.className = 'px-2 pb-2 text-[11px] text-rose-700';
    error.dataset.receiptFieldError = '';
    error.dataset.fieldName = field.name;
    error.setAttribute('role', 'alert');
    error.textContent = message;
    cell.append(error);
}

export function validateReceiptRows(form) {
    const rows = [...form.querySelectorAll('[data-receipt-row]')];
    const active = rows.filter((row) => [...row.querySelectorAll('input, select')].some((field) => field.value.trim() !== ''));
    const errors = [];
    rows.forEach((row, position) => {
        const required = active.includes(row) || (!active.length && position === 0);
        Object.entries(fields).forEach(([name, message]) => {
            const field = row.querySelector(`[name$="[${name}]"]`);
            if (!field) return;
            field.required = required;
            const invalid = required && (!field.value.trim() || !field.checkValidity() || field.type === 'number' && !(Number(field.value) > 0));
            fieldError(field, invalid ? message : '');
            const rowNumber = Number(field.name.match(/items\[(\d+)\]/)?.[1] ?? position) + 1;
            if (invalid) errors.push({ field, message: `Baris ${rowNumber}: ${message}` });
        });
    });
    form.dataset.receiptValidated = '1';
    return errors;
}

export function initReceiptValidation() {
    document.querySelectorAll('form.receipt-entry:not([data-row-validation])').forEach((form) => {
        form.dataset.rowValidation = '1';
        const check = (event) => {
            if (event.target.closest('[data-receipt-row]') && form.dataset.receiptValidated) validateReceiptRows(form);
            if (['counterparty', 'supplier_invoice_number'].includes(event.target.name)) {
                form.querySelector('[data-duplicate-invoice-confirmed]').checked = false;
                form.querySelector('[data-duplicate-invoice-warning]').hidden = true;
                delete form.dataset.invoiceChecked;
            }
        };
        form.addEventListener('input', check);
        form.addEventListener('change', check);
    });
}

function invoiceKey(form) {
    return JSON.stringify([form.elements.counterparty.value.trim(), form.elements.supplier_invoice_number.value.trim()]);
}

export function checkReceiptInvoice(form, submitter) {
    const supplier = form.elements.counterparty.value.trim();
    const invoice = form.elements.supplier_invoice_number.value.trim();
    const confirmed = form.querySelector('[data-duplicate-invoice-confirmed]');
    const key = invoiceKey(form);
    if (!supplier || !invoice || confirmed.checked || form.dataset.invoiceChecked === key) return false;
    if (form.dataset.invoiceChecking) return true;
    form.dataset.invoiceChecking = '1';
    const controls = [...form.querySelectorAll('button[type="submit"], button:not([type])')];
    const disabled = controls.map((button) => button.disabled);
    controls.forEach((button) => { button.disabled = true; });
    const restore = () => controls.forEach((button, index) => { button.disabled = disabled[index]; });
    void (async () => {
        try {
            const url = new URL(form.dataset.checkInvoice, location.href);
            url.search = new URLSearchParams({ counterparty: supplier, supplier_invoice_number: invoice, receipt_id: form.dataset.receiptId || '' });
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('invoice-check-failed');
            const data = await response.json();
            if (invoiceKey(form) !== key) return;
            if (data.matches.length) {
                form.querySelector('[data-duplicate-invoice-warning]').hidden = false;
                const result = await Swal.fire({
                    title: 'Faktur ini sudah tercatat',
                    text: `Ditemukan ${data.matches.map((match) => `${match.reference} (${match.status})`).join(', ')}. Lanjutkan hanya jika penerimaan ini memang berbeda.`,
                    icon: 'warning', showCancelButton: true,
                    confirmButtonText: 'Sudah diperiksa, lanjutkan', cancelButtonText: 'Periksa kembali', reverseButtons: true,
                });
                if (!result.isConfirmed || invoiceKey(form) !== key) return;
                confirmed.checked = true;
            }
            form.dataset.invoiceChecked = key;
            restore();
            form.requestSubmit(submitter || undefined);
        } catch {
            await Swal.fire({ title: 'Faktur belum dapat diperiksa', text: 'Isian tetap tersimpan di halaman ini. Coba simpan kembali setelah koneksi tersedia.', icon: 'error' });
        } finally {
            delete form.dataset.invoiceChecking;
            restore();
        }
    })();
    return true;
}
