import './bootstrap';
import Chart from 'chart.js/auto';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

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
    document.getElementById('nightly-page-info-bottom').textContent = pageLabel;
    for (const id of ['nightly-prev', 'nightly-prev-bottom']) document.getElementById(id).disabled = nightlyPage === 1;
    for (const id of ['nightly-next', 'nightly-next-bottom']) document.getElementById(id).disabled = nightlyPage === totalPages;
}
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
            if (result.isConfirmed) cancelDraftForm.submit();
        });
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
    if (event.target.closest('#nightly-prev, #nightly-prev-bottom')) {
        nightlyPage = Math.max(1, nightlyPage - 1);
        filterNightlyRows();
        document.getElementById('nightly-search')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    if (event.target.closest('#nightly-next, #nightly-next-bottom')) {
        nightlyPage++;
        filterNightlyRows();
        document.getElementById('nightly-search')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
window.addEventListener('medicine-editing', () => {
    const dialog = document.getElementById('medicine-dialog');
    if (dialog && !dialog.open) dialog.showModal();
});
window.addEventListener('medicine-saved', () => document.getElementById('medicine-dialog')?.close());
document.addEventListener('input', (event) => {
    if (event.target.matches('#nightly-search')) {
        nightlyPage = 1;
        filterNightlyRows();
    }
    if (event.target.matches('[data-nightly-row] input')) filterNightlyRows();
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
    if (event.target.matches('[data-nightly-row] input')) filterNightlyRows();
});
initialize();
document.addEventListener('livewire:navigated', initialize);
