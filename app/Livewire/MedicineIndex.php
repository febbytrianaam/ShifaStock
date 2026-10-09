<?php

namespace App\Livewire;

use App\Models\Medicine;
use App\Models\MedicineAudit;
use App\Models\MedicinePackage;
use App\Models\Unit;
use App\Services\MedicineResearchImportService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class MedicineIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $verificationFilter = '';

    public string $operationalFilter = '';

    public string $sort = 'code';

    public ?int $editingId = null;

    public string $code = '';

    public ?string $original_name = '';

    public string $name = '';

    public ?string $generic_name = '';

    public ?string $strength = '';

    public ?string $dosage_form = '';

    public ?string $unit_id = '';

    public ?string $stock_unit_suggestion = '';

    public ?string $purchase_unit_name = '';

    public ?string $package_reference = '';

    public ?string $conversion_candidate = '';

    public string $verification_status = 'belum_diverifikasi';

    public ?string $verification_note = '';

    public ?string $package_name = '';

    public ?string $package_conversion = '';

    public bool $showImportPreview = false;

    public array $importPreview = [];

    public function mount(): void
    {
        Gate::authorize('view-inventory');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedVerificationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedOperationalFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize('manage-medicines');
        $this->resetForm();
        $this->dispatch('medicine-editing');
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-medicines');
        $medicine = Medicine::with('packages')->findOrFail($id);
        $package = $medicine->packages->first();

        $this->editingId = $medicine->id;
        $this->code = $medicine->code;
        $this->original_name = (string) $medicine->original_name;
        $this->name = $medicine->name;
        $this->generic_name = (string) $medicine->generic_name;
        $this->strength = (string) $medicine->strength;
        $this->dosage_form = (string) $medicine->dosage_form;
        $this->unit_id = (string) $medicine->unit_id;
        $this->stock_unit_suggestion = (string) $medicine->stock_unit_suggestion;
        $this->purchase_unit_name = (string) $medicine->purchase_unit_name;
        $this->package_reference = (string) $medicine->package_reference;
        $this->conversion_candidate = $medicine->conversion_candidate !== null ? (string) $medicine->conversion_candidate : '';
        $this->verification_status = $medicine->verification_status ?: 'belum_diverifikasi';
        $this->verification_note = (string) $medicine->verification_note;
        $this->package_name = (string) $package?->name;
        $this->package_conversion = $package?->conversion_factor !== null ? (string) $package->conversion_factor : '';

        $this->dispatch('medicine-editing');
    }

    public function save(): void
    {
        Gate::authorize('manage-medicines');
        $this->code = trim($this->code);
        $this->name = trim($this->name);
        $this->original_name = $this->nullableTrim($this->original_name);
        $this->generic_name = $this->nullableTrim($this->generic_name);
        $this->strength = $this->nullableTrim($this->strength);
        $this->dosage_form = $this->nullableTrim($this->dosage_form);
        $this->unit_id = blank($this->unit_id) ? null : $this->unit_id;
        $this->stock_unit_suggestion = $this->nullableTrim($this->stock_unit_suggestion);
        $this->purchase_unit_name = $this->nullableTrim($this->purchase_unit_name);
        $this->package_reference = $this->nullableTrim($this->package_reference);
        $this->conversion_candidate = blank($this->conversion_candidate) ? null : $this->conversion_candidate;
        $this->verification_note = $this->nullableTrim($this->verification_note);
        $this->package_name = $this->nullableTrim($this->package_name);
        $this->package_conversion = blank($this->package_conversion) ? null : $this->package_conversion;

        $data = $this->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('medicines', 'code')->ignore($this->editingId)],
            'original_name' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:100'],
            'dosage_form' => ['nullable', 'string', 'max:100'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'stock_unit_suggestion' => ['nullable', 'string', 'max:100'],
            'purchase_unit_name' => ['nullable', 'string', 'max:100'],
            'package_reference' => ['nullable', 'string', 'max:2000'],
            'conversion_candidate' => ['nullable', 'numeric', 'gt:0'],
            'verification_status' => ['required', 'in:belum_diverifikasi,perlu_revisi,terverifikasi'],
            'verification_note' => ['nullable', 'string', 'max:4000'],
            'package_name' => ['nullable', 'string', 'max:100'],
            'package_conversion' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $payload = collect($data)->except(['package_name', 'package_conversion'])->map(fn ($value) => blank($value) ? null : $value)->all();
        if ($payload['verification_status'] === 'terverifikasi') {
            $payload['verified_by'] = auth()->id();
            $payload['verified_at'] = now();
        }

        $medicine = $this->editingId ? Medicine::findOrFail($this->editingId) : new Medicine(['is_active' => false]);
        $before = $medicine->exists ? $medicine->replicate()->toArray() : null;
        $medicine->fill($payload);
        $medicine->save();

        if (filled($data['package_name']) && filled($data['package_conversion'])) {
            MedicinePackage::updateOrCreate(
                ['medicine_id' => $medicine->id, 'name' => trim($data['package_name'])],
                ['conversion_factor' => (float) $data['package_conversion']],
            );
        }

        MedicineAudit::create([
            'medicine_id' => $medicine->id,
            'user_id' => auth()->id(),
            'action' => $this->editingId ? 'updated' : 'created',
            'before' => $before,
            'after' => $medicine->fresh()->toArray(),
        ]);

        $this->resetForm();
        $this->dispatch('medicine-saved');
        session()->flash('status', 'Master obat berhasil disimpan.');
    }

    public function verify(int $id): void
    {
        Gate::authorize('manage-medicines');
        $medicine = Medicine::findOrFail($id);
        $before = $medicine->replicate()->toArray();
        $medicine->update([
            'verification_status' => 'terverifikasi',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        MedicineAudit::create([
            'medicine_id' => $medicine->id,
            'user_id' => auth()->id(),
            'action' => 'verified',
            'before' => $before,
            'after' => $medicine->fresh()->toArray(),
        ]);

        session()->flash('status', 'Obat ditandai terverifikasi.');
    }

    public function toggleActive(int $id): void
    {
        Gate::authorize('manage-medicines');
        $medicine = Medicine::findOrFail($id);
        if (! $medicine->is_active && ($medicine->verification_status !== 'terverifikasi' || ! $medicine->unit_id || ! $medicine->dosage_form)) {
            session()->flash('status', 'Obat hanya bisa diaktifkan setelah terverifikasi, bentuk sediaan terisi, dan satuan stok dipilih.');
            return;
        }

        $before = $medicine->replicate()->toArray();
        $medicine->update(['is_active' => ! $medicine->is_active]);

        MedicineAudit::create([
            'medicine_id' => $medicine->id,
            'user_id' => auth()->id(),
            'action' => $medicine->is_active ? 'activated' : 'deactivated',
            'before' => $before,
            'after' => $medicine->fresh()->toArray(),
        ]);
    }

    public function previewImport(MedicineResearchImportService $importer): void
    {
        Gate::authorize('manage-medicines');
        $this->importPreview = $importer->preview()->all();
        $this->showImportPreview = true;
    }

    public function runImport(MedicineResearchImportService $importer): void
    {
        Gate::authorize('manage-medicines');
        $result = $importer->import(auth()->user());
        $this->showImportPreview = false;
        $this->importPreview = [];
        session()->flash('status', "Import selesai. {$result['created']} obat dibuat, {$result['skipped']} dilewati karena sudah ada.");
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId',
            'code',
            'original_name',
            'name',
            'generic_name',
            'strength',
            'dosage_form',
            'unit_id',
            'stock_unit_suggestion',
            'purchase_unit_name',
            'package_reference',
            'conversion_candidate',
            'verification_note',
            'package_name',
            'package_conversion',
        ]);
        $this->verification_status = 'belum_diverifikasi';
        $this->resetValidation();
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function render()
    {
        Gate::authorize('view-inventory');

        $counts = Medicine::selectRaw('COUNT(*) as total')
            ->selectRaw("COALESCE(SUM(CASE WHEN verification_status = 'terverifikasi' THEN 1 ELSE 0 END), 0) as verified")
            ->selectRaw("COALESCE(SUM(CASE WHEN verification_status = 'perlu_revisi' THEN 1 ELSE 0 END), 0) as revision")
            ->selectRaw("COALESCE(SUM(CASE WHEN verification_status = 'belum_diverifikasi' THEN 1 ELSE 0 END), 0) as pending")
            ->selectRaw('COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active')
            ->first();

        $sort = in_array($this->sort, ['code', 'name', 'verification_status', 'is_active'], true) ? $this->sort : 'code';

        return view('livewire.medicine-index', [
            'summary' => [
                'total' => (int) $counts->total,
                'verified' => (int) $counts->verified,
                'revision' => (int) $counts->revision,
                'pending' => (int) $counts->pending,
                'active' => (int) $counts->active,
            ],
            'medicines' => Medicine::with(['unit', 'balances.location', 'stockParameters', 'batches', 'packages', 'audits.user'])
                ->where(fn ($query) => $query
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('original_name', 'like', '%'.$this->search.'%')
                    ->orWhere('generic_name', 'like', '%'.$this->search.'%')
                    ->orWhere('code', 'like', '%'.$this->search.'%'))
                ->when($this->verificationFilter !== '', fn ($query) => $query->where('verification_status', $this->verificationFilter))
                ->when($this->operationalFilter === 'active', fn ($query) => $query->where('is_active', true))
                ->when($this->operationalFilter === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderBy($sort)
                ->paginate(15),
            'units' => Unit::orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Data Obat']);
    }
}
