<?php

namespace App\Support;

use App\Models\IssueImport;

class IssueImportGuidance
{
    public static function forMessage(string $message, IssueImport $document): array
    {
        return match ($message) {
            'Sebelum batas mulai impor; periksa periode ekspor' => [
                'title' => 'Pengeluaran lebih awal dari '.$document->starts_at->format('d/m/Y H:i').' WIB',
                'action' => 'Lihat jam transaksi pada Rincian, lalu gunakan ekspor periode yang sesuai. Jangan memundurkan tanggal jika pengeluaran sudah termasuk stok awal.',
            ],
            'Pemetaan obat/satuan belum disetujui atau master berubah' => [
                'title' => 'Pilihan obat dan satuan perlu dikonfirmasi',
                'short_action' => 'Pilih obat di langkah Cocokkan obat, periksa isi per satuan, centang Benar, lalu Simpan Pilihan & Lanjutkan.',
                'action' => 'Buka langkah Cocokkan obat. Pilih obat sesuai nama, bentuk, dan dosisnya; periksa isi per satuan (contoh: 1 BUTIR = 1 Tablet). Centang Benar, lalu tekan Simpan Pilihan & Lanjutkan. Isian otomatis baru saran, belum persetujuan. Jika pilihan pernah disimpan, data obat/satuannya mungkin berubah dan perlu diperiksa ulang. Stok belum berkurang.',
            ],
            'Data sumber berubah; tinjau koreksi transaksi lama' => [
                'title' => 'Transaksi pernah dicatat, tetapi isinya berubah',
                'action' => 'Minta apoteker memeriksa transaksi lama dan koreksinya sebelum melanjutkan.',
            ],
            'Hasil konversi jumlah di luar batas' => [
                'title' => 'Jumlah setelah penyesuaian satuan tidak valid',
                'action' => 'Periksa jumlah pada Excel dan isi per satuan di langkah Cocokkan obat.',
            ],
            'Sudah melewati stok awal/hitung fisik; pastikan tidak mengurangi dua kali' => [
                'title' => 'Pengeluaran lebih lama dari stok awal atau hitung fisik terakhir',
                'action' => 'Pengeluaran ini mungkin sudah termasuk hasil hitung stok. Minta apoteker memeriksa periodenya agar stok tidak dipotong dua kali.',
            ],
            'Stok total atau batch layak pakai tidak cukup untuk seluruh pengeluaran obat ini' => [
                'title' => 'Stok di '.$document->location->name.' belum cukup',
                'action' => 'Cek stok lokasi dan batch. Lengkapi penerimaan atau transfer yang memang terjadi, lalu muat ulang halaman.',
            ],
            'Stok total dan rincian batch berbeda; rekonsiliasi dahulu' => [
                'title' => 'Jumlah stok dan rincian batch perlu dicocokkan',
                'action' => 'Minta apoteker memeriksa stok total dan jumlah masing-masing batch terlebih dahulu.',
            ],
            default => ['title' => 'Data perlu diperiksa', 'action' => 'Periksa rincian transaksi bersama apoteker sebelum melanjutkan.'],
        };
    }
}
