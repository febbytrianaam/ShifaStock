import Swal from 'sweetalert2';
import { createElement, Trash2 } from 'lucide';
import { allowFormNavigation } from './form-safety';

export function initIssueImports() {
    document.querySelectorAll('.issue-import-workspace').forEach((workspace) => {
        if (workspace.dataset.layoutReady) return;
        workspace.dataset.layoutReady = '1';
        const fitWorkspace = () => {
            const desktop = window.matchMedia('(min-width: 1280px)').matches;
            for (const [selector, variable] of [['.issue-import-list-scroll', '--issue-import-list-height'], ['[data-import-info]', '--issue-import-info-height']]) {
                const element = workspace.querySelector(selector);
                if (!element) continue;
                if (desktop) {
                    const top = element.getBoundingClientRect().top + window.scrollY;
                    workspace.style.setProperty(variable, `${Math.max(192, window.innerHeight - top - 24)}px`);
                } else workspace.style.removeProperty(variable);
            }
        };
        fitWorkspace();
        window.addEventListener('resize', fitWorkspace);
        document.fonts.ready.then(fitWorkspace);
    });
    document.querySelectorAll('[data-issue-import-delete]').forEach((deleteForm) => {
        if (deleteForm.dataset.importReady) return;
        deleteForm.dataset.importReady = '1';
        deleteForm.querySelector('[data-issue-import-delete-icon]')?.replaceChildren(createElement(Trash2, { width: 16, height: 16 }));
        deleteForm.addEventListener('submit', async (event) => {
            if (event.defaultPrevented) return;
            event.preventDefault();
            if (deleteForm.dataset.deleting) return;
            const result = await Swal.fire({
                title: `Hapus pratinjau ${deleteForm.dataset.importReference}?`,
                text: 'Pratinjau dihapus dari riwayat. Stok tidak berubah; pemetaan obat yang sudah disimpan dan batas mulai pencatatan tetap tersedia.',
                icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, hapus pratinjau',
                cancelButtonText: 'Batal', confirmButtonColor: '#e11d48', reverseButtons: true,
            });
            if (!result.isConfirmed) return;
            deleteForm.dataset.deleting = '1';
            deleteForm.querySelector('button').disabled = true;
            allowFormNavigation();
            deleteForm.submit();
        });
    });
    const location = document.getElementById('import-location');
    const start = document.getElementById('import-start');
    if (location && start && !location.dataset.importReady) {
        location.dataset.importReady = '1';
        const sync = (changed = false) => {
            const cutoff = location.selectedOptions[0]?.dataset.start || '';
            start.readOnly = Boolean(cutoff);
            if (cutoff || changed) start.value = cutoff;
            const settings = document.querySelector('[data-import-period-settings]');
            if (settings) settings.open = !cutoff;
            const summary = document.getElementById('import-period-summary');
            if (summary) summary.textContent = cutoff
                ? `Pengeluaran dihitung sejak ${new Intl.DateTimeFormat('id-ID', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(cutoff))} WIB`
                : 'Atur tanggal mulai untuk lokasi ini';
            document.getElementById('import-policy-help').textContent = cutoff
                ? 'Lokasi ini sudah mulai mencatat pengeluaran pada waktu tersebut. Gunakan batas yang sama untuk unggahan berikutnya; file periode berikutnya tetap bisa diimpor.'
                : 'Bukan tanggal unggah. Pilih awal periode pengeluaran yang belum masuk stok ShifaStock. Setelah posting pertama, waktu ini tetap untuk lokasi tersebut.';
        };
        sync();
        location.addEventListener('change', () => sync(true));
    }
    const matchRows = [...document.querySelectorAll('[data-import-match-row]')];
    const showSaved = document.querySelector('[data-import-show-saved]');
    if (showSaved && !showSaved.dataset.importReady) {
        showSaved.dataset.importReady = '1';
        const search = document.querySelector('[data-import-match-search]');
        const selectVisible = document.querySelector('[data-import-select-visible]');
        const syncVisibleSelection = () => {
            const boxes = matchRows.filter((row) => !row.hidden).map((row) => row.querySelector('[data-import-map-confirm]'));
            const checked = boxes.filter((box) => box.checked).length;
            selectVisible.checked = boxes.length > 0 && checked === boxes.length;
            selectVisible.indeterminate = checked > 0 && checked < boxes.length;
        };
        const applyMatchFilter = () => {
            const term = search.value.trim().toLocaleLowerCase('id-ID');
            matchRows.forEach((row) => {
                row.hidden = (!showSaved.checked && row.dataset.needsMapping !== '1') || !row.dataset.importSearchText.toLocaleLowerCase('id-ID').includes(term);
                row.nextElementSibling.hidden = row.hidden;
            });
            const visible = matchRows.filter((row) => !row.hidden).length;
            document.querySelector('[data-import-match-result]').textContent = visible
                ? `${visible} dari ${matchRows.length} obat ditampilkan. Pencarian tidak menghapus pilihan Anda.`
                : 'Obat tidak ditemukan. Ubah pencarian atau tampilkan pilihan tersimpan.';
            syncVisibleSelection();
        };
        showSaved.addEventListener('change', applyMatchFilter);
        search.addEventListener('input', applyMatchFilter);
        search.addEventListener('keydown', (event) => { if (event.key === 'Enter') event.preventDefault(); });
        selectVisible.addEventListener('change', () => {
            const checked = selectVisible.checked;
            matchRows.filter((row) => !row.hidden).forEach((row) => {
                const selected = row.querySelector('[data-import-medicine]');
                const factor = row.querySelector('input[type="number"]');
                const box = row.querySelector('[data-import-map-confirm]');
                box.checked = checked && Boolean(selected.value) && factor.checkValidity();
                box.dispatchEvent(new Event('change', { bubbles: true }));
            });
            syncVisibleSelection();
        });
        matchRows.forEach((row) => {
            row.querySelector('[data-import-map-confirm]').addEventListener('change', syncVisibleSelection);
            const medicine = row.querySelector('[data-import-medicine]');
            medicine.addEventListener('change', () => {
                row.querySelector('[data-import-target-unit]').textContent = medicine.selectedOptions[0]?.dataset.unit || 'pilih obat dahulu';
                row.querySelector('[data-import-map-confirm]').checked = false;
                syncVisibleSelection();
            });
            row.querySelector('input[type="number"]').addEventListener('input', () => {
                row.querySelector('[data-import-map-confirm]').checked = false;
                syncVisibleSelection();
            });
        });
        applyMatchFilter();
    }
    const filter = document.querySelector('[data-import-review-filter]');
    if (filter && !filter.dataset.importReady) {
        filter.dataset.importReady = '1';
        const rows = [...document.querySelectorAll('[data-import-review-row]')];
        const apply = () => {
            rows.forEach((row) => {
                row.hidden = filter.value !== 'all' && Number(row.dataset[filter.value]) === 0;
                row.nextElementSibling.hidden = row.hidden;
            });
            document.querySelector('[data-import-review-count]').textContent = `${rows.filter((row) => !row.hidden).length} dari ${rows.length} obat ditampilkan`;
        };
        filter.addEventListener('change', apply);
        apply();
    }
    const form = document.querySelector('[data-import-post]');
    if (!form || form.dataset.importReady) return;
    form.dataset.importReady = '1';
    form.addEventListener('submit', async (event) => {
        if (event.defaultPrevented) return;
        event.preventDefault();
        const result = await Swal.fire({
            title: 'Simpan pengeluaran dan kurangi stok?',
            text: 'Stok berkurang sesuai daftar yang diperiksa. Batch dipilih dari kedaluwarsa terdekat. Transaksi yang sudah dicatat tidak dipotong lagi.',
            icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, simpan pengeluaran',
            cancelButtonText: 'Periksa kembali', confirmButtonColor: '#3277B2', reverseButtons: true,
        });
        if (!result.isConfirmed) return;
        allowFormNavigation();
        form.querySelector('button[type="submit"]').disabled = true;
        form.submit();
    });
}
