<?php

namespace App\Support;

use App\Models\User;

class Onboarding
{
    public static function steps(User $user): array
    {
        $steps = [[
            'title' => 'Selamat datang di ShifaStock',
            'description' => 'Mari kenali alur kerja persediaan Klinik Shifa Medika. Panduan ini tidak menyimpan transaksi atau mengubah stok.',
        ]];
        $menus = [
            ['view-dashboard', 'dashboard', 'Dashboard', 'Lihat ringkasan stok, penerimaan, transfer, dan obat yang perlu ditindaklanjuti. Semua angka berasal dari data yang tersimpan.'],
            ['manage-users', 'admin.users.index', 'Pengguna dan hak akses', 'Buka Pengguna untuk membuat akun, menonaktifkan akun, atau mengganti password.'
                .($user->can('manage-permissions') ? ' Tab Hak Akses mengatur menu dan tindakan untuk setiap role.' : '')],
            ['manage-system-references', 'admin.references.index', 'Referensi sistem', 'Atur satuan dan lokasi yang digunakan bersama oleh petugas dan apoteker. Nonaktifkan referensi yang tidak dipakai; jangan mengganti arti satuan yang sudah memiliki riwayat.'],
            ['manage-system-settings', 'admin.settings.index', 'Pengaturan sistem', 'Isi identitas klinik dan lokasi penerimaan bawaan sebelum petugas mulai mencatat transaksi.'],
            ['view-audit-logs', 'admin.audit-logs.index', 'Audit aktivitas', 'Telusuri akun, waktu, dan aktivitas yang tercatat ketika perlu memeriksa perubahan data.'],
            ['view-medicines', 'medicines.index', 'Data obat', $user->can('manage-medicines')
                ? 'Periksa nama, satuan stok, dan isi kemasan setiap obat. Lengkapi verifikasi lalu aktifkan obat agar bisa dipilih pada transaksi.'
                : 'Cari obat dan periksa satuan yang digunakan. Jika obat belum tersedia atau satuannya belum tepat, minta kepala apoteker memperbarui master obat.'],
            ['view-opening-balances', 'opening-balances.index', 'Stok awal', 'Saat mulai memakai sistem, masukkan stok fisik per obat, batch, kedaluwarsa, dan lokasi. Jangan mencatat stok yang sama lagi sebagai penerimaan.'],
            ['view-receipts', 'preview.receipts', 'Penerimaan obat', 'Cocokkan faktur PBF dengan obat, batch, kedaluwarsa, jumlah, dan satuan. Simpan draf jika belum selesai. Posting hanya saat benar karena stok langsung bertambah.'],
            ['view-transfers', 'preview.transfers', 'Transfer Gudang ke Apotek', 'Pilih obat, lokasi sumber, tujuan, dan jumlah. Batch kosong memakai FEFO otomatis. Pastikan obat benar-benar sudah dipindahkan sebelum menyimpan.'],
            ['view-issues', 'preview.issues', 'Pengeluaran obat', 'Catat obat yang keluar dari lokasi persediaan. Isi referensi dan jumlah sesuai pemakaian; penyimpanan transaksi langsung mengurangi stok.'],
            ['view-returns', 'preview.returns', 'Retur ke PBF', 'Pilih obat, batch, lokasi sumber, dan alasan pengembalian. Posting retur mengurangi stok pada lokasi tersebut.'],
            ['view-nightly-checks', 'preview.nightly', 'Pemeriksaan stok', $user->can('finalize-opname')
                ? 'Tab Harian dipakai untuk meninjau hasil hitung petugas. Tab Bulanan dipakai untuk stock opname. Cocokkan selisih dan rincian batch sebelum menyetujui atau finalisasi karena tindakan ini dapat menyesuaikan stok.'
                : 'Mulai pemeriksaan per lokasi, isi hasil hitung fisik tiap batch, lalu simpan draf atau kirim ke kepala apoteker. Tab bulanan dipakai untuk stock opname.'],
            ['view-shortages', 'shortages.index', 'Monitoring stok dan kedaluwarsa', 'Periksa kekurangan stok dan tab Kedaluwarsa. Gunakan filter lokasi untuk menemukan obat yang perlu diisi ulang atau ditindaklanjuti.'],
            ['view-monitoring-parameters', 'monitoring-parameters.index', 'Parameter monitoring', 'Tentukan stok minimum per obat dan lokasi serta batas peringatan kedaluwarsa sesuai keputusan apoteker.'],
            ['view-special-adjustments', 'special-adjustments.index', 'Kondisi khusus', 'Catat obat rusak, hilang, atau kondisi lain. Cocokkan batch dengan lokasi dan jelaskan kejadian; pastikan arah perubahan stok sudah benar.'],
            ['view-reports', 'preview.reports', 'Laporan dan kartu stok', 'Buka Laporan untuk melihat persediaan. Pada tab Kartu Stok, pilih obat, lokasi, dan periode lalu terapkan filter untuk menelusuri mutasi.'],
        ];
        $readOnly = [
            'medicines.index' => 'Cari obat dan periksa satuan stok. Minta kepala apoteker memperbaiki master jika data belum tepat.',
            'opening-balances.index' => 'Telusuri stok awal per obat, batch, dan lokasi sebagai dasar persediaan.',
            'preview.receipts' => 'Lihat penerimaan dan riwayat faktur PBF beserta batch, jumlah, dan lokasi tujuan.',
            'preview.transfers' => 'Periksa riwayat perpindahan obat dari Gudang ke Apotek, termasuk jumlah dan batch yang dialokasikan.',
            'preview.issues' => 'Periksa obat yang sudah keluar dari lokasi persediaan beserta jumlah dan referensinya.',
            'preview.returns' => 'Lihat riwayat obat yang dikembalikan ke PBF beserta batch dan alasannya.',
            'preview.nightly' => 'Lihat pemeriksaan pada tab Harian dan stock opname pada tab Bulanan. Akun ini tidak dapat mengisi atau menyetujui penghitungan.',
            'special-adjustments.index' => 'Periksa riwayat penyesuaian stok, lokasi, batch, dan alasan kejadian khusus.',
            'monitoring-parameters.index' => 'Lihat batas minimum stok per obat dan lokasi serta periode peringatan kedaluwarsa yang telah ditetapkan apoteker.',
        ];
        foreach ($menus as [$permission, $route, $title, $description]) {
            if (! $user->can($permission)) {
                continue;
            }
            $canWrite = match ($route) {
                'medicines.index' => $user->can('manage-medicines'),
                'monitoring-parameters.index' => $user->can('manage-monitoring-parameters'),
                'preview.nightly' => $user->can('operate-inventory') || $user->can('finalize-opname'),
                default => $user->can('operate-inventory'),
            };
            $steps[] = ['element' => '[data-tour-menu="'.$route.'"]', 'title' => $title,
                'description' => ! $canWrite && isset($readOnly[$route])
                    ? 'Pada menu ini, akun Anda hanya dapat melihat data. '.$readOnly[$route] : $description];
        }
        $steps[] = ['element' => '[data-tour-profile]', 'title' => 'Selesai bekerja',
            'description' => 'Klik nama akun di sini lalu pilih Keluar, terutama jika komputer dipakai bergantian. Panduan bisa dibuka ulang lewat tombol tanda tanya di topbar.'];

        return $steps;
    }
}
