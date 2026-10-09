<?php

namespace App\Livewire;

use App\Models\Medicine;
use App\Models\Unit;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class MedicineIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $code = '';

    public string $name = '';

    public string $dosage_form = '';

    public string $unit_id = '';

    public function mount(): void
    {
        Gate::authorize('view-inventory');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function save(): void
    {
        Gate::authorize('manage-medicines');
        $this->code = trim($this->code);
        $this->name = trim($this->name);
        $this->dosage_form = trim($this->dosage_form);
        $data = $this->validate([
            'code' => ['required', 'string', 'max:50', 'unique:medicines,code'],
            'name' => ['required', 'string', 'max:255'],
            'dosage_form' => ['required', 'string', 'max:100'],
            'unit_id' => ['required', 'exists:units,id'],
        ]);
        Medicine::create($data);
        $this->reset('code', 'name', 'dosage_form', 'unit_id');
        $this->dispatch('medicine-saved');
        session()->flash('status', 'Master obat berhasil ditambahkan. Stok dicatat melalui transaksi persediaan.');
    }

    public function toggleActive(int $id): void
    {
        Gate::authorize('manage-medicines');
        $medicine = Medicine::findOrFail($id);
        if (! $medicine->unit_id || ! $medicine->dosage_form) {
            session()->flash('status', 'Bentuk sediaan dan satuan dasar hasil impor perlu diverifikasi sebelum obat diaktifkan.');
            return;
        }
        $medicine->update(['is_active' => ! $medicine->is_active]);
    }

    public function render()
    {
        Gate::authorize('view-inventory');

        $counts = Medicine::selectRaw('COUNT(*) as total')
            ->selectRaw("COALESCE(SUM(CASE WHEN unit_id IS NULL OR dosage_form IS NULL OR dosage_form = '' THEN 1 ELSE 0 END), 0) as pending")
            ->selectRaw("COALESCE(SUM(CASE WHEN unit_id IS NOT NULL AND dosage_form IS NOT NULL AND dosage_form <> '' AND is_active = 1 THEN 1 ELSE 0 END), 0) as active")
            ->selectRaw("COALESCE(SUM(CASE WHEN unit_id IS NOT NULL AND dosage_form IS NOT NULL AND dosage_form <> '' AND is_active = 0 THEN 1 ELSE 0 END), 0) as inactive")
            ->first();

        return view('livewire.medicine-index', [
            'summary' => [
                'total' => (int) $counts->total,
                'active' => (int) $counts->active,
                'inactive' => (int) $counts->inactive,
                'pending' => (int) $counts->pending,
            ],
            'medicines' => Medicine::with(['unit', 'balances', 'stockParameters', 'batches'])->where(fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('code', 'like', '%'.$this->search.'%'))
                ->orderBy('code')->paginate(15),
            'units' => Unit::orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Data Obat']);
    }
}
