import './bootstrap';
import Chart from 'chart.js/auto';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { initFormSafety, resetFormBaseline, allowFormNavigation, cancelFormNavigation } from './form-safety';
import { initReceiptValidation, validateReceiptRows, checkReceiptInvoice } from './receipt-validation';
import { initDailyCounts, prepareDailyCounts } from './daily-counts';
import { initOnboarding } from './onboarding';
import { initPasswordVisibility } from './password-visibility';
import { initTransferRows } from './transfer-rows';
import { initIssueImports } from './issue-imports';

let nightlyPage = 1;

function initialize() {
    const canvas = document.getElementById('stockChart');
    if (canvas && !Chart.getChart(canvas)) {
        const chart = JSON.parse(canvas.dataset.chart);

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: chart.labels,
                datasets: [
                    {
                        label: 'Penerimaan',
                        data: chart.receipts,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.10)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3.5,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#10b981',
                        pointBorderWidth: 2,
                    },
                    {
                        label: 'Transfer',
                        data: chart.transfers,
                        borderColor: '#3277B2',
                        backgroundColor: 'rgba(50, 119, 178, 0.10)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3.5,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#3277B2',
                        pointBorderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 16,
                            font: { family: "'Plus Jakarta Sans', Inter, sans-serif", size: 12, weight: '600' },
                            color: '#475569',
                        },
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#f8fafc',
                        bodyColor: '#cbd5e1',
                        borderColor: '#334155',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 10,
                        titleFont: { family: "'Plus Jakarta Sans', Inter, sans-serif", size: 13, weight: '700' },
                        bodyFont: { family: "'Plus Jakarta Sans', Inter, sans-serif", size: 12 },
                        boxPadding: 6,
                        usePointStyle: true,
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { family: "'Plus Jakarta Sans', Inter, sans-serif", size: 11, weight: '500' },
                            color: '#94a3b8',
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9',
                        },
                        ticks: {
                            precision: 0,
                            font: { family: "'Plus Jakarta Sans', Inter, sans-serif", size: 11 },
                            color: '#94a3b8',
                            padding: 8,
                        },
                    },
                },
            },
        });
    }
    if (new URLSearchParams(location.search).has('tambah')) {
        document.getElementById('medicine-dialog')?.showModal();
    }
    filterNightlyRows();
    initBalanceSearch();
    initSearchableSelects();
    initReceiptRows();
    initReceiptValidation();
    initStockEntryContexts();
    initTransferRows({ initSearchableSelects, initStockEntryContexts });
    initRolePreview();
    initDailyCounts();
    initIssueImports();
    initFormSafety();
    initOnboarding();
    initPasswordVisibility();
}

function initBalanceSearch() {
    const input = document.getElementById('balance-search');
    if (!input || input.dataset.initialized) return;
    input.dataset.initialized = '1';
    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        document.querySelectorAll('[data-balance-row]').forEach((row) => {
            const text = row.textContent.toLowerCase();
            row.hidden = query.length > 0 && !text.includes(query);
        });
    });
}

function initSearchableSelects(root = document) {
    root.querySelectorAll('select[data-searchable-select]:not([data-searchable-ready])').forEach((select) => {
        select.dataset.searchableReady = '1';
        const wrapper = document.createElement('div');
        wrapper.className = 'searchable-select';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'field searchable-select-input text-xs';
        input.autocomplete = 'off';
        input.placeholder = select.dataset.searchPlaceholder || 'Cari data...';
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-expanded', 'false');
        const clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.className = 'searchable-select-clear';
        clearButton.textContent = 'x';
        clearButton.setAttribute('aria-label', 'Bersihkan pilihan');
        clearButton.hidden = true;
        const menu = document.createElement('div');
        menu.className = 'searchable-select-menu';
        menu.hidden = true;
        menu.setAttribute('role', 'listbox');
        select.classList.add('searchable-native');
        select.after(wrapper);
        wrapper.append(select, input, clearButton, menu);

        const dependencies = () => (select.dataset.dependsOn || '')
            .split(',')
            .map((selector) => selector.trim())
            .filter(Boolean)
            .map((selector) => document.querySelector(selector))
            .filter(Boolean);
        const dependencyValues = () => dependencies().map((dependency) => dependency.value || '');
        const selectedOption = () => select.options[select.selectedIndex];
        const optionDependencyValues = (option) => {
            if (option.dataset.dependsValues) return option.dataset.dependsValues.split('|');
            return option.dataset.dependsValue ? [option.dataset.dependsValue] : [];
        };
        const optionMatchesDependency = (option) => {
            const values = dependencyValues();
            return optionDependencyValues(option).every((dependsValue, index) => !dependsValue || dependsValue === values[index]);
        };
        const syncInput = () => {
            const option = selectedOption();
            input.value = option?.value ? option.textContent.trim() : (select.dataset.emptyLabel || '');
            syncClearButton();
        };
        const syncClearButton = () => {
            clearButton.hidden = !input.value && !select.value;
        };
        const options = () => [...select.options].filter((option) => option.value !== '' && !option.disabled && optionMatchesDependency(option));
        const ensureSelectionMatchesDependency = () => {
            const option = selectedOption();
            if (option?.value && !optionMatchesDependency(option)) {
                // A batch can have the same value at several locations; keep the matching option's identity.
                const matching = options().find((candidate) => candidate.value === option.value);
                select.selectedIndex = matching ? matching.index : 0;
                syncInput();
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };
        const close = () => {
            menu.hidden = true;
            input.setAttribute('aria-expanded', 'false');
        };
        const open = () => {
            if (select.closest('.receipt-entry, .transfer-entry, .issue-import-mapping')) {
                const rect = input.getBoundingClientRect();
                const below = window.innerHeight - rect.bottom - 12;
                const above = rect.top - 12;
                const upwards = below < 160 && above > below;
                menu.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - rect.width - 8))}px`;
                menu.style.width = `${Math.min(rect.width, window.innerWidth - 16)}px`;
                menu.style.maxHeight = `${Math.min(256, Math.max(80, upwards ? above : below))}px`;
                menu.style.top = upwards ? 'auto' : `${rect.bottom + 4}px`;
                menu.style.bottom = upwards ? `${window.innerHeight - rect.top + 4}px` : 'auto';
            }
            menu.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };
        if (select.closest('.receipt-entry, .transfer-entry, .issue-import-mapping')) {
            window.addEventListener('resize', close);
            document.addEventListener('scroll', (event) => {
                if (event.target === document || event.target.contains?.(wrapper)) close();
            }, true);
        }
        const render = () => {
            ensureSelectionMatchesDependency();
            const query = input.value === select.dataset.emptyLabel ? '' : input.value.trim().toLocaleLowerCase('id-ID');
            const matches = options()
                .filter((option) => !query || option.textContent.toLocaleLowerCase('id-ID').includes(query))
                .slice(0, 80);
            menu.replaceChildren();

            if (!matches.length) {
                const empty = document.createElement('div');
                empty.className = 'searchable-select-empty';
                empty.textContent = select.dataset.dependsOn && dependencyValues().some((value) => !value)
                    ? (select.dataset.dependsEmpty || 'Lengkapi pilihan terkait dulu')
                    : 'Data tidak ditemukan';
                menu.append(empty);
                return;
            }

            matches.forEach((option) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'searchable-select-option';
                button.textContent = option.textContent.trim();
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', String(option === selectedOption()));
                button.addEventListener('mousedown', (event) => event.preventDefault());
                button.addEventListener('click', () => {
                    select.selectedIndex = option.index;
                    syncInput();
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    close();
                });
                menu.append(button);
            });
        };

        syncInput();
        ensureSelectionMatchesDependency();
        dependencies().forEach((dependency) => dependency.addEventListener('change', () => {
            ensureSelectionMatchesDependency();
            render();
        }));
        input.addEventListener('focus', () => {
            render();
            open();
        });
        input.addEventListener('input', () => {
            const selected = selectedOption();
            if (selected && input.value.trim() !== selected.textContent.trim()) {
                select.value = '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
            syncClearButton();
            render();
            open();
        });
        clearButton.addEventListener('mousedown', (event) => event.preventDefault());
        clearButton.addEventListener('click', () => {
            select.value = '';
            input.value = '';
            syncClearButton();
            select.dispatchEvent(new Event('change', { bubbles: true }));
            render();
            open();
            input.focus();
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
                return;
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (menu.hidden) {
                    render();
                    open();
                }
                menu.querySelector('button')?.focus();
            }
        });
        menu.addEventListener('keydown', (event) => {
            const buttons = [...menu.querySelectorAll('button')];
            const index = buttons.indexOf(document.activeElement);
            if (event.key === 'Escape') {
                input.focus();
                close();
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                buttons[Math.min(index + 1, buttons.length - 1)]?.focus();
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (index <= 0) input.focus();
                else buttons[index - 1]?.focus();
            }
        });
    });
}

function initStockEntryContexts(root = document) {
    const format = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 }).format(value);
    root.querySelectorAll('[data-stock-entry-context]:not([data-ready])').forEach((context) => {
        context.dataset.ready = '1';
        const source = context.querySelector('[data-stock-context-data]') || document.querySelector(context.dataset.stockContextSource);
        const data = JSON.parse(source.textContent);
        const medicine = document.querySelector(context.dataset.medicine);
        const location = document.querySelector(context.dataset.location);
        const quantity = document.querySelector(context.dataset.quantity);
        const batch = document.querySelector(context.dataset.batch);
        const label = quantity.labels?.[0];
        const summary = context.querySelector('[data-stock-context-summary]');
        const plan = context.querySelector('[data-stock-context-plan]');
        const sync = () => {
            const unit = data.units[medicine.value] || '';
            if (label) label.textContent = unit ? `Jumlah (${unit})` : 'Jumlah';
            plan.textContent = '';
            if (!medicine.value || !location.value) {
                summary.textContent = 'Pilih obat dan lokasi untuk melihat stok tersedia.';
                return;
            }
            const balances = data.balances.filter((item) => String(item.medicine) === medicine.value && String(item.location) === location.value)
                .sort((a, b) => (a.expiry || '9999').localeCompare(b.expiry || '9999') || (a.number || '').localeCompare(b.number || '') || a.id - b.id);
            const selected = batch.value ? balances.filter((item) => String(item.batch) === batch.value) : balances;
            const available = selected.reduce((sum, item) => sum + item.quantity, 0);
            summary.textContent = `Stok batch ${context.dataset.fefo === '1' ? 'layak pakai ' : ''}tersedia: ${format(available)} ${unit} di ${location.selectedOptions[0].textContent.trim()}.`;
            if (context.dataset.medicine === '#adjust-medicine' && document.getElementById('adjust-direction')?.value === 'increase') return;
            if (Number(quantity.value) > available) {
                plan.textContent = 'Jumlah melebihi stok batch tersedia. Periksa jumlah dan pilihan batch.';
                return;
            }
            if (context.dataset.fefo !== '1' || batch.value || !(Number(quantity.value) > 0)) return;
            let remaining = Number(quantity.value);
            const allocations = [];
            for (const item of balances) {
                const amount = Math.min(remaining, item.quantity);
                if (amount > 0) allocations.push(`${item.number}: ${format(amount)} ${unit}`);
                remaining = Number((remaining - amount).toFixed(4));
                if (remaining <= 0) break;
            }
            plan.textContent = `Perkiraan batch otomatis: ${allocations.join('; ')}. Stok diperiksa kembali saat disimpan.`;
        };
        [medicine, location, batch].forEach((field) => field.addEventListener('change', sync));
        quantity.addEventListener('input', sync);
        if (context.dataset.medicine === '#adjust-medicine') {
            document.getElementById('adjust-direction')?.addEventListener('change', sync);
            document.getElementById('adjust-condition')?.addEventListener('change', () => queueMicrotask(sync));
        }
        sync();
    });
    const condition = document.getElementById('adjust-condition');
    const direction = document.getElementById('adjust-direction');
    if (condition && direction && !condition.dataset.directionReady) {
        condition.dataset.directionReady = '1';
        const syncDirection = () => {
            const decreaseOnly = ['rusak', 'hilang', 'terjatuh', 'expired'].includes(condition.value);
            direction.querySelector('[value="increase"]').disabled = decreaseOnly;
            if (decreaseOnly && direction.value !== 'decrease') {
                direction.value = 'decrease';
                direction.dispatchEvent(new Event('change', { bubbles: true }));
            }
            document.querySelector('[data-adjust-save]').textContent = direction.value === 'increase' ? 'Simpan & Tambah Stok Koreksi' : 'Simpan & Kurangi Stok';
        };
        condition.addEventListener('change', syncDirection);
        direction.addEventListener('change', syncDirection);
        syncDirection();
    }
}

function initReceiptRows(root = document) {
    root.querySelectorAll('[data-receipt-row]:not([data-receipt-ready])').forEach((row) => {
        row.dataset.receiptReady = '1';
        const medicine = row.querySelector('[data-receipt-medicine]');
        row.querySelector('[data-receipt-row-label]').textContent = `Baris ${Number(medicine.name.match(/items\[(\d+)\]/)?.[1] ?? 0) + 1}`;
        const inputQuantity = row.querySelector('[data-receipt-input-quantity]');
        const inputUnit = row.querySelector('[data-receipt-input-unit]');
        const conversion = row.querySelector('[data-receipt-conversion]');
        const stockQuantity = row.querySelector('[data-receipt-stock-quantity]');
        const syncUnitDisplay = () => {
            const stockUnit = medicine?.selectedOptions[0]?.dataset.stockUnit || '';
            const sameUnit = inputUnit.value.trim().toLocaleLowerCase() === stockUnit.trim().toLocaleLowerCase();
            row.querySelector('[data-receipt-conversion-label]').hidden = !medicine.value || !inputUnit.value || sameUnit;
            row.querySelector('[data-receipt-unit-equation]').textContent = `1 ${inputUnit.value} =`;
            row.querySelector('[data-receipt-conversion-unit]').textContent = stockUnit;
            row.querySelector('[data-receipt-stock-unit]').textContent = stockUnit;
            if (sameUnit && medicine.value) conversion.value = '1';
            conversion.readOnly = sameUnit;
        };
        const applyMedicineDefaults = (event) => {
            const option = medicine?.options[medicine.selectedIndex];
            if (inputUnit?.tagName === 'SELECT') {
                const previous = event ? '' : (inputUnit.dataset.initialUnit || inputUnit.value);
                inputUnit.replaceChildren(new Option('Pilih satuan', ''));
                if (!option?.value) {
                    conversion.value = '';
                    stockQuantity.value = '';
                    syncUnitDisplay();
                    return;
                }
                const packages = JSON.parse(option.dataset.packages || '[]');
                if (option.dataset.purchaseUnit && !packages.some((item) => item.name === option.dataset.purchaseUnit)) {
                    packages.push({ name: option.dataset.purchaseUnit, factor: option.dataset.conversion || '' });
                }
                if (option.dataset.stockUnit && !packages.some((item) => item.name === option.dataset.stockUnit)) {
                    packages.push({ name: option.dataset.stockUnit, factor: 1 });
                }
                if (previous && !packages.some((item) => item.name === previous)) packages.push({ name: previous, factor: conversion.value || 1 });
                packages.forEach((item) => {
                    const unitOption = new Option(item.name, item.name);
                    unitOption.dataset.factor = item.name.trim().toLocaleLowerCase() === (option.dataset.stockUnit || '').trim().toLocaleLowerCase() ? 1 : item.factor;
                    inputUnit.add(unitOption);
                });
                inputUnit.value = previous || option.dataset.purchaseUnit || option.dataset.stockUnit || '';
            }
            if (!option?.value) return;
            if (conversion && (event || !conversion.value)) {
                const factor = inputUnit?.selectedOptions?.[0]?.dataset.factor || option.dataset.conversion;
                conversion.value = factor ? String(Number(factor)) : '';
            }
            if (event) stockQuantity.dataset.autoCalculated = '1';
            stockQuantity.placeholder = option.dataset.stockUnit || 'Satuan stok';
            syncUnitDisplay();
            updateReceiptStockQuantity(row);
        };
        inputUnit?.addEventListener('change', () => {
            const factor = inputUnit.selectedOptions[0]?.dataset.factor;
            conversion.value = factor ? String(Number(factor)) : '';
            stockQuantity.dataset.autoCalculated = '1';
            syncUnitDisplay();
            updateReceiptStockQuantity(row);
        });
        medicine?.addEventListener('change', applyMedicineDefaults);
        inputQuantity?.addEventListener('input', () => updateReceiptStockQuantity(row));
        conversion?.addEventListener('input', () => updateReceiptStockQuantity(row));
        stockQuantity?.addEventListener('input', () => {
            stockQuantity.dataset.autoCalculated = '0';
        });
        applyMedicineDefaults();
    });
}

function initRolePreview() {
    const select = document.querySelector('[data-role-preview-select]');
    if (!select || select.dataset.initialized) return;
    select.dataset.initialized = '1';
    const sync = () => {
        document.querySelectorAll('[data-role-preview-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.rolePreviewPanel !== select.value;
        });
    };
    select.addEventListener('change', sync);
    sync();
}

function updateReceiptStockQuantity(row) {
    const inputQuantity = row.querySelector('[data-receipt-input-quantity]');
    const conversion = row.querySelector('[data-receipt-conversion]');
    const stockQuantity = row.querySelector('[data-receipt-stock-quantity]');
    if (!inputQuantity || !conversion || !stockQuantity) return;
    stockQuantity.value = '';
    if (!inputQuantity.value || !conversion.value) return;

    const quantity = Number(inputQuantity.value);
    const factor = Number(conversion.value);
    if (!Number.isFinite(quantity) || !Number.isFinite(factor) || quantity <= 0 || factor <= 0) return;

    stockQuantity.value = Number((quantity * factor).toFixed(4)).toString();
    stockQuantity.dataset.autoCalculated = '1';
}

function filterNightlyRows() {
    const search = document.getElementById('nightly-search');
    const filter = document.getElementById('nightly-filter');
    const pageSizeSelect = document.getElementById('nightly-page-size');
    if (!search || !filter || !pageSizeSelect) return;

    const query = search.value.trim().toLocaleLowerCase('id-ID');
    const pageSize = Number(pageSizeSelect.value);
    const matches = [...document.querySelectorAll('[data-nightly-row]')].filter((row) => {
        const quantityInput = row.querySelector('input[name$="[quantity]"]');
        const lowInput = row.querySelector('input[name$="[is_low]"]');
        const quantity = quantityInput ? quantityInput.value : row.dataset.quantity;
        const isLow = lowInput ? lowInput.checked : row.dataset.low === '1';
        const matchesText = row.dataset.search.includes(query);
        const matchesStatus = filter.value === 'all'
            || (filter.value === 'unchecked' && quantity === '')
            || (filter.value === 'empty' && quantity === '0')
            || (filter.value === 'low' && quantity !== '' && quantity !== '0' && isLow);
        return matchesText && matchesStatus;
    });

    const totalPages = Math.max(1, Math.ceil(matches.length / pageSize));
    nightlyPage = Math.min(nightlyPage, totalPages);
    const first = (nightlyPage - 1) * pageSize;
    const visibleRows = new Set(matches.slice(first, first + pageSize));
    document.querySelectorAll('[data-nightly-row]').forEach((row) => { row.hidden = !visibleRows.has(row); });

    const last = Math.min(first + pageSize, matches.length);
    const range = matches.length ? `${first + 1}-${last} dari ${matches.length} obat` : 'Tidak ada obat yang sesuai';
    const pageLabel = `Halaman ${nightlyPage} dari ${totalPages}`;
    document.getElementById('nightly-result-count').textContent = range;
    document.getElementById('nightly-page-info').textContent = pageLabel;
    for (const id of ['nightly-page-info-bottom']) {
        const label = document.getElementById(id);
        if (label) label.textContent = pageLabel;
    }
    for (const id of ['nightly-prev', 'nightly-prev-bottom']) {
        const button = document.getElementById(id);
        if (button) button.disabled = nightlyPage === 1;
    }
    for (const id of ['nightly-next', 'nightly-next-bottom']) {
        const button = document.getElementById(id);
        if (button) button.disabled = nightlyPage === totalPages;
    }
    const empty = document.querySelector('[data-nightly-empty]');
    if (empty) empty.hidden = matches.length > 0;
}
document.addEventListener('nightly:reveal-row', (event) => {
    const row = event.detail;
    const rows = [...document.querySelectorAll('[data-nightly-row]')];
    if (!rows.includes(row)) return;
    document.getElementById('nightly-search').value = '';
    document.getElementById('nightly-filter').value = 'all';
    nightlyPage = Math.floor(rows.indexOf(row) / Number(document.getElementById('nightly-page-size').value)) + 1;
    filterNightlyRows();
});

function closeSidebar() {
    document.getElementById('sidebar')?.classList.add('-translate-x-full');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (backdrop) backdrop.hidden = true;
    document.getElementById('sidebar-toggle')?.setAttribute('aria-expanded', 'false');
}
function closeUserMenu() {
    const menu = document.getElementById('user-menu');
    const button = document.getElementById('user-menu-button');
    if (menu) menu.hidden = true;
    button?.setAttribute('aria-expanded', 'false');
}
document.addEventListener('click', (event) => {
    const approveButton = event.target.closest('[data-confirm-stock-review]');
    if (approveButton && approveButton.dataset.confirmed !== '1') {
        event.preventDefault();
        const coverage = approveButton.dataset.checkedCount === undefined ? '' : `${approveButton.dataset.checkedCount} obat sudah diperiksa; ${approveButton.dataset.uncheckedCount} obat belum diperiksa dan tidak akan diubah stoknya. `;
        Swal.fire({ title: 'Setujui dan sesuaikan stok?', text: coverage + 'Stok sistem akan disesuaikan hanya ke hasil hitung yang sudah diisi. Pastikan satuan dan transaksi setelah penghitungan sudah diperiksa.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, setujui dan sesuaikan', cancelButtonText: 'Periksa kembali' }).then((result) => {
            if (!result.isConfirmed) return;
            approveButton.dataset.confirmed = '1';
            approveButton.form.requestSubmit(approveButton);
            delete approveButton.dataset.confirmed;
        });
        return;
    }
    const confirmForm = event.target.closest('form[data-confirm-action]');
    if (confirmForm && event.target.closest('button[type="submit"], button:not([type])')) {
        event.preventDefault();
        Swal.fire({
            title: confirmForm.dataset.confirmTitle || 'Lanjutkan tindakan?',
            text: confirmForm.dataset.confirmText || 'Pastikan data yang dipilih sudah benar.',
            icon: confirmForm.dataset.confirmIcon || 'warning',
            showCancelButton: true,
            confirmButtonText: confirmForm.dataset.confirmButton || 'Ya, lanjutkan',
            cancelButtonText: confirmForm.dataset.cancelButton || 'Batal',
            reverseButtons: true,
            confirmButtonColor: confirmForm.dataset.confirmColor || '#e11d48',
            cancelButtonColor: '#64748b',
        }).then((result) => {
            if (result.isConfirmed) {
                allowFormNavigation();
                confirmForm.submit();
            }
        });
        return;
    }
    const cancelDraftForm = event.target.closest('form[data-confirm-cancel-draft]');
    if (cancelDraftForm && event.target.closest('button[type="submit"], button:not([type])')) {
        event.preventDefault();
        Swal.fire({
            title: 'Batalkan draf?',
            text: 'Draf pemeriksaan dan isian obat di dalamnya akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, cancel draf',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#64748b',
        }).then((result) => {
            if (result.isConfirmed) {
                allowFormNavigation();
                cancelDraftForm.submit();
            }
        });
        return;
    }
    const cancelReceiptDraftForm = event.target.closest('form[data-confirm-receipt-draft]');
    if (cancelReceiptDraftForm && event.target.closest('button[type="submit"], button:not([type])')) {
        event.preventDefault();
        Swal.fire({
            title: 'Batalkan draft penerimaan?',
            text: 'Dokumen draft dan item di dalamnya akan dihapus. Stok tidak berubah.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, batalkan',
            cancelButtonText: 'Kembali',
            reverseButtons: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#64748b',
        }).then((result) => {
            if (result.isConfirmed) {
                allowFormNavigation();
                cancelReceiptDraftForm.submit();
            }
        });
        return;
    }
    if (event.target.closest('[data-receipt-add-row]')) {
        const table = document.querySelector('[data-receipt-items]');
        const template = document.getElementById('receipt-row-template');
        if (!table || !template) return;
        const index = Math.max(-1, ...[...table.querySelectorAll('[data-receipt-medicine]')].map((field) => Number(field.name.match(/items\[(\d+)\]/)?.[1] ?? -1))) + 1;
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[data-name]').forEach((input) => {
            input.name = `items[${index}][${input.dataset.name}]`;
            input.removeAttribute('data-name');
        });
        table.appendChild(fragment);
        initSearchableSelects(table);
        initReceiptRows(table);
        return;
    }
    if (event.target.closest('[data-receipt-remove-row]')) {
        const row = event.target.closest('[data-receipt-row]');
        const table = row?.closest('[data-receipt-items]');
        if (!row || !table || table.querySelectorAll('[data-receipt-row]').length <= 1) return;
        row.remove();
        table.querySelectorAll('[data-receipt-row]').forEach((row, index) => {
            row.querySelector('[data-receipt-row-label]').textContent = `Baris ${index + 1}`;
            row.querySelectorAll('[data-receipt-field-error]').forEach((error) => error.remove());
            row.querySelectorAll('[name^="items["]').forEach((input) => {
                input.name = input.name.replace(/items\[\d+]/, `items[${index}]`);
            });
        });
        if (table.closest('form').dataset.receiptValidated) validateReceiptRows(table.closest('form'));
        return;
    }
    const userMenuButton = event.target.closest('[data-user-menu-toggle]');
    if (userMenuButton) {
        const menu = document.getElementById('user-menu');
        const willOpen = menu?.hidden ?? false;
        if (menu) menu.hidden = !willOpen;
        userMenuButton.setAttribute('aria-expanded', String(willOpen));
        return;
    }
    if (!event.target.closest('#user-menu')) closeUserMenu();
    if (!event.target.closest('.searchable-select')) {
        document.querySelectorAll('.searchable-select-menu').forEach((menu) => {
            menu.hidden = true;
            menu.closest('.searchable-select')?.querySelector('[role="combobox"]')?.setAttribute('aria-expanded', 'false');
        });
    }
    if (event.target.closest('#nightly-prev, #nightly-prev-bottom')) {
        nightlyPage = Math.max(1, nightlyPage - 1);
        filterNightlyRows();
        document.getElementById('nightly-table-scroll')?.scrollTo({ top: 0 });
    }
    if (event.target.closest('#nightly-next, #nightly-next-bottom')) {
        nightlyPage++;
        filterNightlyRows();
        document.getElementById('nightly-table-scroll')?.scrollTo({ top: 0 });
    }
    const documentButton = event.target.closest('[data-document]');
    if (documentButton) {
        const dialog = document.getElementById('preview-dialog');
        dialog.style.maxWidth = '1100px';
        document.getElementById('preview-dialog-title').textContent = documentButton.dataset.title;
        const frame = document.createElement('iframe');
        frame.src = documentButton.dataset.document;
        frame.title = documentButton.dataset.title;
        frame.style.cssText = 'width:100%;height:70vh;border:1px solid #e2e8f0;border-radius:12px;background:#f1f5f9';
        document.getElementById('preview-dialog-content').replaceChildren(frame);
        dialog.showModal();
    }
    const button = event.target.closest('[data-preview]');
    if (button) {
        const template = document.getElementById(button.dataset.preview);
        const dialog = document.getElementById('preview-dialog');
        if (!template || !dialog) return;
        dialog.style.maxWidth = '';
        document.getElementById('preview-dialog-title').textContent = button.dataset.title || 'Pratinjau';
        document.getElementById('preview-dialog-content').replaceChildren(template.content.cloneNode(true));
        dialog.showModal();
    }
    if (event.target.closest('[data-close-dialog]')) event.target.closest('dialog')?.close();
    if (event.target.closest('[data-open-medicine]')) document.getElementById('medicine-dialog')?.showModal();
    if (event.target.closest('#sidebar-toggle')) {
        const sidebar = document.getElementById('sidebar');
        const open = sidebar.classList.contains('-translate-x-full');
        sidebar.classList.toggle('-translate-x-full', !open);
        document.getElementById('sidebar-backdrop').hidden = !open;
        document.getElementById('sidebar-toggle').setAttribute('aria-expanded', String(open));
    }
    if (event.target.closest('#sidebar-backdrop')) closeSidebar();
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeSidebar();
        closeUserMenu();
    }
});
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.matches('[data-nightly-count-form]') && !prepareDailyCounts(form, event.submitter)) {
        event.preventDefault();
        cancelFormNavigation();
        return;
    }

    for (const select of form.querySelectorAll('select[data-searchable-select]')) {
        const wrapper = select.closest('.searchable-select');
        const input = wrapper?.querySelector('.searchable-select-input');
        if (!input) continue;

        const query = input.value.trim().toLocaleLowerCase('id-ID');
        const dependentValues = (select.dataset.dependsOn || '')
            .split(',')
            .map((selector) => selector.trim())
            .filter(Boolean)
            .map((selector) => document.querySelector(selector)?.value || '');
        const exact = [...select.options].find((option) => {
            const optionDependencyValues = option.dataset.dependsValues
                ? option.dataset.dependsValues.split('|')
                : (option.dataset.dependsValue ? [option.dataset.dependsValue] : []);
            const matchesDependency = optionDependencyValues.every((dependsValue, index) => !dependsValue || !dependentValues[index] || dependsValue === dependentValues[index]);
            return matchesDependency && option.textContent.trim().toLocaleLowerCase('id-ID') === query;
        });
        if (exact) {
            select.selectedIndex = exact.index;
        }
    }

    if (form.matches('.receipt-entry')) {
        const errors = validateReceiptRows(form);
        if (errors.length) {
            event.preventDefault();
            void Swal.fire({ title: 'Periksa baris penerimaan', text: errors.map((error) => error.message).join('\n'), icon: 'warning', confirmButtonText: 'Perbaiki isian' }).then(() => {
                const field = errors[0].field;
                (field.closest('.searchable-select')?.querySelector('.searchable-select-input') || field).focus();
                field.closest('td')?.scrollIntoView({ block: 'center', inline: 'center' });
            });
            return;
        }
    }

    if (form.dataset.requiredAlert) {
        const requiredFields = [...form.querySelectorAll('[required]')];
        const invalidFields = requiredFields.filter((field) => !field.disabled && !field.checkValidity()).map((field) => {
            return field.dataset.requiredLabel
                || form.querySelector(`label[for="${field.id}"]`)?.textContent.trim()
                || field.name;
        });

        const start = form.dataset.dateRangeStart ? document.querySelector(form.dataset.dateRangeStart) : null;
        const end = form.dataset.dateRangeEnd ? document.querySelector(form.dataset.dateRangeEnd) : null;
        const invalidRange = start?.value && end?.value && end.value < start.value;

        if (invalidFields.length || invalidRange) {
            event.preventDefault();
            const messages = [
                ...invalidFields.map((field) => `<li>${field}</li>`),
                ...(invalidRange ? ['<li>Tanggal selesai tidak boleh sebelum tanggal mulai</li>'] : []),
            ].join('');
            Swal.fire({
                title: form.dataset.requiredAlert,
                html: `<div class="text-left text-sm text-slate-600">Isi atau perbaiki bagian berikut:<ul class="mt-2 list-disc pl-5">${messages}</ul></div>`,
                icon: 'warning',
                confirmButtonText: 'Mengerti',
                confirmButtonColor: '#3277B2',
            }).then(() => {
                const firstMissing = requiredFields.find((field) => !field.disabled && !field.checkValidity()) || (invalidRange ? end : null);
                const visibleInput = firstMissing?.closest('.searchable-select')?.querySelector('.searchable-select-input');
                (visibleInput || firstMissing)?.focus();
            });
            return;
        }
    }

    for (const select of form.querySelectorAll('select[data-searchable-select]')) {
        const wrapper = select.closest('.searchable-select');
        const input = wrapper?.querySelector('.searchable-select-input');
        if (!input) continue;

        if (select.required && !select.value) {
            event.preventDefault();
            const label = select.closest('div')?.querySelector('label')?.textContent.trim().toLocaleLowerCase('id-ID') || 'data';
            input.setCustomValidity(`Pilih ${label} dari daftar.`);
            input.reportValidity();
            input.addEventListener('input', () => input.setCustomValidity(''), { once: true });
            break;
        }
    }
    if (!event.defaultPrevented && form.dataset.checkInvoice && checkReceiptInvoice(form, event.submitter)) event.preventDefault();
}, true);
window.addEventListener('medicine-editing', () => {
    const dialog = document.getElementById('medicine-dialog');
    resetFormBaseline(dialog?.querySelector('form'));
    if (dialog && !dialog.open) dialog.showModal();
});
window.addEventListener('medicine-saved', () => {
    const dialog = document.getElementById('medicine-dialog');
    resetFormBaseline(dialog?.querySelector('form'));
    dialog?.close();
});
document.addEventListener('input', (event) => {
    if (event.target.matches('#nightly-search')) {
        nightlyPage = 1;
        filterNightlyRows();
    }
    if (!event.target.matches('[data-opname-input]')) return;
    const input = event.target;
    const row = input.closest('[data-opname-row]');
    const value = input.valueAsNumber;
    const valid = input.value !== '' && Number.isInteger(value) && value >= 0;
    const difference = value - Number(input.dataset.stock);
    row.querySelector('[data-diff]').textContent = valid ? (difference > 0 ? '+' : '') + difference : '—';
    const status = row.querySelector('[data-opname-status]');
    status.textContent = valid ? (difference === 0 ? 'Sesuai' : 'Perlu Cek') : 'Belum valid';
    status.className = 'p-4 text-center ' + (valid ? (difference === 0 ? 'text-emerald-600' : 'text-amber-600') : 'text-slate-400');
    const count = [...document.querySelectorAll('[data-opname-input]')].filter(el => el.value !== '' && el.validity.valid).length;
    const progress = document.getElementById('opname-progress');
    if (progress) progress.textContent = count;
    const bar = document.getElementById('opname-progress-bar');
    if (bar) bar.style.width = count / document.querySelectorAll('[data-opname-input]').length * 100 + '%';
});
document.addEventListener('change', (event) => {
    if (event.target.matches('#nightly-filter, #nightly-page-size')) {
        nightlyPage = 1;
        filterNightlyRows();
    }
});
initialize();
document.addEventListener('livewire:navigated', initialize);
