<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\IssueImport;
use App\Models\Location;
use App\Models\Medicine;
use App\Services\IssueImportService;
use App\Services\NeoklinikIssueReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IssueImportController extends Controller
{
    public function index()
    {
        return view('inventory.issue-imports.index', [
            'documents' => IssueImport::with(['location', 'creator', 'poster'])->latest('id')->paginate(15),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'policies' => DB::table('issue_import_policies')->get()->keyBy('location_id'),
        ]);
    }

    public function store(Request $request, NeoklinikIssueReader $reader, IssueImportService $service)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx', 'max:4096'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'],
            'cutoff_confirmed' => ['accepted'],
        ], ['cutoff_confirmed.accepted' => 'Pastikan pengeluaran belum dicatat manual atau termasuk hasil hitung stok.'],
            ['file' => 'file Excel', 'location_id' => 'lokasi asal stok', 'starts_at' => 'tanggal mulai pencatatan', 'cutoff_confirmed' => 'konfirmasi pengeluaran']);
        $rows = $reader->read($request->file('file')->getRealPath());
        $document = $service->create($rows, hash_file('sha256', $request->file('file')->getRealPath()),
            (int) $data['location_id'], $data['starts_at'], $request->user());

        return redirect()->route('issue-imports.show', $document)->with('status', 'File dibaca. Belum ada stok yang dikurangi; periksa pencocokan dan pratinjau.');
    }

    public function show(Request $request, IssueImport $issueImport, IssueImportService $service)
    {
        $expired = $issueImport->status !== 'posted' && (! $issueImport->payload || $issueImport->created_at->lt(now()->subDays(7)));
        $preview = $issueImport->status === 'draft' && ! $expired ? $service->preview($issueImport) : null;
        $needsMapping = $preview ? count(array_filter($preview['groups'], fn ($group) => $group['needs_mapping'])) : 0;
        $step = $request->query('step');
        if (! in_array($step, ['match', 'review'], true) || ! $request->user()->can('operate-inventory')) {
            $step = $needsMapping && $request->user()->can('operate-inventory') ? 'match' : 'review';
        }
        if ($preview && $request->user()->can('operate-inventory')) {
            $request->session()->put('issue_import_navigation', [
                'id' => $issueImport->id, 'user_id' => $request->user()->id, 'step' => $step,
            ]);
        } elseif ((int) $request->session()->get('issue_import_navigation.id') === $issueImport->id) {
            $request->session()->forget('issue_import_navigation');
        }
        $movements = InventoryMovement::with(['medicine.unit', 'batch', 'sourceLocation', 'creator'])
            ->whereIn('id', DB::table('issue_import_line_movements as link')->join('issue_import_lines as line', 'line.id', '=', 'link.issue_import_line_id')
                ->where('line.issue_import_id', $issueImport->id)->select('link.inventory_movement_id'))->orderBy('occurred_at')->paginate(25);

        return view('inventory.issue-imports.show', ['document' => $issueImport->load(['location', 'creator', 'poster']),
            'preview' => $preview, 'expired' => $expired, 'movements' => $movements, 'step' => $step, 'needsMapping' => $needsMapping,
            'medicines' => Medicine::with('unit')->where('is_active', true)->whereNotNull('unit_id')
                ->whereNotNull('dosage_form')->where('dosage_form', '!=', '')->orderBy('name')->get()]);
    }

    public function mappings(Request $request, IssueImport $issueImport, IssueImportService $service)
    {
        $attributes = [];
        foreach ($service->groups($issueImport) as $key => $group) {
            $attributes['mappings.'.$key.'.medicine_id'] = 'obat ShifaStock untuk '.$group['code'];
            $attributes['mappings.'.$key.'.factor'] = 'isi per satuan untuk '.$group['code'];
            $attributes['mappings.'.$key.'.confirmed'] = 'persetujuan pilihan untuk '.$group['code'];
        }
        $data = $request->validate([
            'mappings' => ['required', 'array', 'max:2000'],
            'mappings.*.medicine_id' => ['nullable', 'integer', 'exists:medicines,id'],
            'mappings.*.factor' => ['required', 'numeric', 'min:0.0001', 'max:1000000', 'decimal:0,4'],
            'mappings.*.confirmed' => ['nullable', 'boolean'],
        ], ['mappings.*.medicine_id.exists' => 'Obat yang dipilih tidak tersedia. Pilih kembali dari daftar.'], $attributes);
        if (! collect($data['mappings'])->contains(fn ($mapping) => ! empty($mapping['confirmed']))) {
            return back()->withErrors(['import' => 'Pilih obat yang sesuai, lalu tandai Benar sebelum melanjutkan.']);
        }
        $service->saveMappings($issueImport, $data['mappings'], $request->user());

        return redirect()->route('issue-imports.show', $issueImport)->with('status', 'Pilihan obat disimpan. Lanjutkan pemeriksaan; stok belum berkurang.');
    }

    public function destroy(Request $request, IssueImport $issueImport, IssueImportService $service)
    {
        $service->delete($issueImport, $request->user());
        if ((int) $request->session()->get('issue_import_navigation.id') === $issueImport->id) {
            $request->session()->forget('issue_import_navigation');
        }

        return redirect()->route('issue-imports.index')->with('status', 'Pratinjau impor dihapus. Stok dan pemetaan obat tersimpan tidak berubah.');
    }

    public function post(Request $request, IssueImport $issueImport, IssueImportService $service)
    {
        $data = $request->validate([
            'preview_token' => ['required', 'string', 'size:64'],
            'delivered_confirmed' => ['accepted'], 'units_confirmed' => ['accepted'],
            'fefo_confirmed' => ['accepted'], 'period_confirmed' => ['accepted'], 'identity_confirmed' => ['accepted'],
        ], ['*.accepted' => 'Lengkapi seluruh konfirmasi sebelum posting; belum ada stok yang dikurangi.']);
        $document = $service->post($issueImport, $data['preview_token'], $request->user());

        return redirect()->route('issue-imports.show', $document)->with('status',
            $document->posted_count.' transaksi sumber diposting; '.$document->duplicate_count.' transaksi sudah diimpor dan tidak dikurangi lagi.');
    }
}
