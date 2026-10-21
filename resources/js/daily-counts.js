import Swal from 'sweetalert2';

export function initDailyCounts() {
    initNightlyStart();
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
        const unit = row.querySelector('[data-nightly-unit]');
        if (unit && !unit.value) errors.push({ input: unit, message: `${label}: pilih satuan hitung yang sesuai Data Obat.` });
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
