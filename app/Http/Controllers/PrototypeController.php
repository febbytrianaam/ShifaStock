<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\InventoryMovement;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrototypeController extends Controller
{
    public function dashboard(Request $request)
    {
        $dashboardMode = $request->query('data') === 'real' ? 'real' : 'demo';
        $payload = $dashboardMode === 'real'
            ? $this->realDashboardData()
            : $this->demoDashboardData();

        return view('dashboard', $payload + [
            'dashboardMode' => $dashboardMode,
            'adminStats' => $dashboardMode === 'real'
                ? [
                    'users' => DB::table('users')->count(),
                    'active_users' => DB::table('users')->where('is_active', true)->count(),
                    'roles' => 4,
                ]
                : [
                    'users' => 18,
                    'active_users' => 16,
                    'roles' => 4,
                ],
        ]);
    }

    private function realDashboardData(): array
    {
        $expiryWarningMonths = (int) SystemSetting::value('expiry_warning_months', 3);
        $latestChecks = DB::table('nightly_checks')->where('status', 'approved')
            ->orderByDesc('date')->orderByDesc('id')->get()->unique('location_id');
        $shortages = DB::table('nightly_check_items as i')
            ->join('nightly_checks as c', 'c.id', '=', 'i.nightly_check_id')
            ->join('locations as l', 'l.id', '=', 'c.location_id')
            ->whereIn('c.id', $latestChecks->pluck('id'))
            ->where(fn ($q) => $q->where('i.quantity', 0)->orWhere('i.is_low', true))
            ->select('i.*', 'c.date', 'l.name as location')->orderBy('i.quantity')->limit(8)->get();

        $start = now('Asia/Jakarta')->startOfDay()->subDays(6);
        $movements = InventoryMovement::where('occurred_at', '>=', $start)->get();
        $dates = collect(range(0, 6))->map(fn ($offset) => $start->copy()->addDays($offset));
        $chart = [
            'labels' => $dates->map(fn ($date) => $date->locale('id')->translatedFormat('d M'))->all(),
            'receipts' => $dates->map(fn ($date) => $movements->filter(fn ($movement) => $movement->type === 'receipt' && $movement->occurred_at->isSameDay($date))->count())->all(),
            'transfers' => $dates->map(fn ($date) => $movements->filter(fn ($movement) => $movement->type === 'transfer' && $movement->occurred_at->isSameDay($date))->count())->all(),
        ];

        return [
            'expiryWarningMonths' => $expiryWarningMonths,
            'stats' => [
                'verified' => Medicine::whereNotNull('unit_id')->whereNotNull('dosage_form')->count(),
                'stocked' => StockBalance::where('quantity', '>', 0)->distinct()->count('medicine_id'),
                'shortages' => $shortages->count(),
                'expiring' => Batch::whereBetween('expiry_date', [now('Asia/Jakarta')->startOfDay(), now('Asia/Jakarta')->addMonths($expiryWarningMonths)])->count(),
            ],
            'shortages' => $shortages,
            'balances' => StockBalance::with(['medicine.unit', 'location'])->where('quantity', '>', 0)->orderByDesc('updated_at')->limit(12)->get(),
            'chart' => $chart,
        ];
    }

    private function demoDashboardData(): array
    {
        $today = now('Asia/Jakarta');

        return [
            'expiryWarningMonths' => 3,
            'stats' => [
                'verified' => 148,
                'stocked' => 126,
                'shortages' => 5,
                'expiring' => 9,
            ],
            'shortages' => collect([
                $this->demoShortage(101, 'Amoxicillin 500 mg', 'Apotek', 0, 'strip'),
                $this->demoShortage(102, 'Paracetamol 500 mg', 'Gudang Farmasi', 4, 'strip'),
                $this->demoShortage(103, 'Cetirizine 10 mg', 'Apotek', 2, 'strip'),
                $this->demoShortage(104, 'Omeprazole 20 mg', 'Gudang Farmasi', 0, 'strip'),
                $this->demoShortage(105, 'Salbutamol Tablet', 'Apotek', 3, 'strip'),
            ]),
            'balances' => collect([
                $this->demoBalance('OBT-001', 'Paracetamol 500 mg', 'tablet', 'Gudang Farmasi', 240, $today->copy()->subHours(3)),
                $this->demoBalance('OBT-018', 'Amoxicillin 500 mg', 'kapsul', 'Apotek', 58, $today->copy()->subHours(5)),
                $this->demoBalance('OBT-032', 'Cetirizine 10 mg', 'tablet', 'Apotek', 42, $today->copy()->subDay()),
                $this->demoBalance('OBT-047', 'Omeprazole 20 mg', 'kapsul', 'Gudang Farmasi', 85, $today->copy()->subDays(2)),
                $this->demoBalance('OBT-059', 'Metformin 500 mg', 'tablet', 'Gudang Farmasi', 160, $today->copy()->subDays(2)),
                $this->demoBalance('OBT-074', 'Amlodipine 5 mg', 'tablet', 'Apotek', 73, $today->copy()->subDays(3)),
                $this->demoBalance('OBT-088', 'Simvastatin 20 mg', 'tablet', 'Gudang Farmasi', 118, $today->copy()->subDays(3)),
                $this->demoBalance('OBT-103', 'Salbutamol Tablet', 'tablet', 'Apotek', 31, $today->copy()->subDays(4)),
            ]),
            'chart' => [
                'labels' => collect(range(6, 0))->map(fn ($offset) => $today->copy()->subDays($offset)->locale('id')->translatedFormat('d M'))->all(),
                'receipts' => [2, 1, 3, 2, 4, 1, 3],
                'transfers' => [1, 2, 1, 3, 2, 2, 4],
            ],
        ];
    }

    private function demoShortage(int $id, string $name, string $location, int $quantity, string $unit): object
    {
        return (object) [
            'nightly_check_id' => $id,
            'name' => $name,
            'location' => $location,
            'quantity' => $quantity,
            'unit' => $unit,
        ];
    }

    private function demoBalance(string $code, string $name, string $unit, string $location, int $quantity, $lastCountedAt): object
    {
        return (object) [
            'quantity' => $quantity,
            'last_counted_at' => $lastCountedAt,
            'medicine' => (object) [
                'code' => $code,
                'name' => $name,
                'unit' => (object) ['name' => $unit],
            ],
            'location' => (object) ['name' => $location],
        ];
    }
}
