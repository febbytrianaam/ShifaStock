<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\InventoryMovement;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\SystemSetting;
use App\Queries\MinimumStockAlerts;
use Illuminate\Support\Facades\DB;

class PrototypeController extends Controller
{
    public function dashboard()
    {
        $expiryWarningMonths = (int) SystemSetting::value('expiry_warning_months', 3);
        $shortageQuery = MinimumStockAlerts::query();
        $shortageCount = (clone $shortageQuery)->count();
        $shortages = $shortageQuery->orderByRaw('COALESCE(b.quantity, 0)')
            ->orderBy('m.name')->orderBy('l.name')->limit(8)->get();

        $start = now('Asia/Jakarta')->startOfDay()->subDays(6);
        $movements = InventoryMovement::whereBetween('occurred_at', [$start, now('Asia/Jakarta')])->get();
        $dates = collect(range(0, 6))->map(fn ($offset) => $start->copy()->addDays($offset));
        $chart = [
            'labels' => $dates->map(fn ($date) => $date->locale('id')->translatedFormat('d M'))->all(),
            'receipts' => $dates->map(fn ($date) => $movements->filter(fn ($m) => $m->type === 'receipt' && $m->occurred_at->isSameDay($date))->unique('reference')->count())->all(),
            'transfers' => $dates->map(fn ($date) => $movements->filter(fn ($m) => $m->type === 'transfer' && $m->occurred_at->isSameDay($date))->unique('reference')->count())->all(),
        ];

        return view('dashboard', [
            'expiryWarningMonths' => $expiryWarningMonths,
            'hasMonitoringParameters' => DB::table('stock_parameters')->whereNotNull('minimum_quantity')->exists(),
            'stats' => [
                'verified' => Medicine::where('verification_status', 'terverifikasi')->where('is_active', true)
                    ->whereNotNull('unit_id')->whereNotNull('dosage_form')->where('dosage_form', '<>', '')->count(),
                'stocked' => StockBalance::where('quantity', '>', 0)->distinct()->count('medicine_id'),
                'shortages' => $shortageCount,
                'expiring' => Batch::whereBetween('expiry_date', [now('Asia/Jakarta')->startOfDay(), now('Asia/Jakarta')->addMonths($expiryWarningMonths)])
                    ->whereHas('balances', fn ($q) => $q->where('quantity', '>', 0))->count(),
            ],
            'shortages' => $shortages,
            'balances' => StockBalance::with(['medicine.unit', 'location'])->where('quantity', '>', 0)->orderByDesc('updated_at')->limit(12)->get(),
            'chart' => $chart,
            'adminStats' => [
                'users' => DB::table('users')->count(),
                'active_users' => DB::table('users')->where('is_active', true)->count(),
                'roles' => count(Role::cases()),
            ],
        ]);
    }
}
