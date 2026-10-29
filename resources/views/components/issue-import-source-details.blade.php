@props(['rows', 'document'])
<details class="text-xs" data-import-source-details>
    <summary class="cursor-pointer px-3 py-2 font-medium text-blue-700">Rincian {{ count($rows) }} transaksi dari Excel</summary>
    <div class="issue-import-source-scroll overflow-auto border-t border-slate-200" tabindex="0" role="region" aria-label="Rincian transaksi Excel">
        <table class="issue-import-source-table w-full table-fixed text-left text-xs" data-import-source-table>
            <caption class="sr-only">Data sumber pengeluaran, diurutkan sesuai baris Excel. Tanggal dan waktu menggunakan WIB.</caption>
            <thead class="sticky top-0 bg-slate-100 text-slate-600"><tr><th class="w-[8%] p-2">Baris Excel</th><th class="w-[18%] p-2">Tgl. Jual (WIB)</th><th class="w-[8%] p-2">Kode Obat</th><th class="w-[20%] p-2">Nama Obat</th><th class="w-[10%] p-2">Jumlah</th><th class="w-[8%] p-2">Satuan</th><th class="w-[10%] p-2">Racikan</th><th class="w-[18%] p-2">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach(collect($rows)->sortBy('source_row') as $row)
                <tr data-import-source-row>
                    <td class="p-2 align-top" data-label="Baris Excel">{{ $row['source_row'] }}</td>
                    <td class="p-2 align-top" data-label="Tgl. Jual (WIB)"><x-issue-import-source-period :rows="[$row]" /></td>
                    <td class="p-2 align-top" data-label="Kode Obat">{{ $row['source_code'] }}</td>
                    <td class="p-2 align-top" data-label="Nama Obat">{{ $row['source_name'] }}</td>
                    <td class="p-2 align-top" data-label="Jumlah">{{ \App\Support\Quantity::format((float)$row['source_quantity']) }}</td>
                    <td class="p-2 align-top" data-label="Satuan">{{ $row['source_unit'] }}</td>
                    <td class="p-2 align-top" data-label="Racikan">{{ $row['compound'] ? 'Ya' : 'Tidak' }}</td>
                    <td class="p-2 align-top {{ $row['status'] === 'blocked' ? 'text-rose-700' : 'text-slate-600' }}" data-label="Status">
                        @if($row['status'] === 'blocked')
                            @php($help = \App\Support\IssueImportGuidance::forMessage($row['message'], $document))
                            <span>{{ $help['title'] }}</span>
                            <details class="mt-2 text-slate-600" data-import-status-help><summary class="cursor-pointer font-medium text-blue-700">Cara memperbaiki</summary><p class="mt-2 leading-relaxed">{{ $help['short_action'] ?? $help['action'] }}</p></details>
                        @else
                            {{ $row['status'] === 'duplicate' ? 'Sudah dicatat; tidak dikurangi lagi' : 'Siap dicatat' }}
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</details>
