import Swal from 'sweetalert2';

export function initDailyCounts() {
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

export function prepareDailyCounts(form, submitter) {
    const payload = {};
    const errors = [];
    form.querySelectorAll('[data-nightly-row]').forEach((row) => {
        const inputs = [...row.querySelectorAll('[data-nightly-batch-count]')];
        const total = row.querySelector('input[name$="[quantity]"]');
        if (!total) return;
        const label = row.querySelector('b')?.textContent || 'Obat';
        const filled = inputs.filter((input) => input.value !== '');
        for (const input of filled) {
            if (!input.validity.valid) errors.push({ input, message: `${label}: jumlah batch harus bilangan bulat 0 atau lebih.` });
            (payload[input.dataset.itemId] ||= {})[input.dataset.batchId] = input.value;
        }
        if (submitter?.value !== 'submit') return;
        if (!total.value || !total.validity.valid) errors.push({ input: total, message: `${label}: isi hasil hitung; gunakan 0 jika kosong.` });
        if (filled.length || total.value !== '' && Number(total.value) !== Number(row.dataset.systemQuantity)) {
            const missing = inputs.find((input) => Number(input.dataset.systemQuantity) > 0 && input.value === '');
            if (missing) errors.push({ input: missing, message: `${label}: lengkapi hasil hitung semua batch yang memiliki stok.` });
            else if (!filled.length) errors.push({ input: total, message: `${label}: isi hasil hitung per batch untuk mencatat perubahan stok.` });
        }
    });
    form.querySelector('[data-nightly-batch-payload]').value = JSON.stringify(payload);
    if (!errors.length) return true;
    void Swal.fire({ title: 'Periksa hitungan stok', text: errors.map((error) => error.message).join('\n'), icon: 'warning', confirmButtonText: 'Perbaiki isian' }).then(() => {
        const input = errors[0].input;
        const row = input.closest('[data-nightly-row]');
        row.hidden = false;
        const details = row.querySelector('[data-nightly-batch-details]');
        if (details) details.open = true;
        input.focus();
        input.scrollIntoView({ block: 'center', inline: 'center' });
    });
    return false;
}
