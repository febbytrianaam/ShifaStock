<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\InventoryMovement;
use App\Models\Medicine;
use App\Models\StockBalance;
use Illuminate\Support\Facades\DB;

class PrototypeController extends Controller
{
    public function dashboard()
    {
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

        return view('dashboard', [
            'stats' => [
                'verified' => Medicine::whereNotNull('unit_id')->whereNotNull('dosage_form')->count(),
                'stocked' => StockBalance::where('quantity', '>', 0)->distinct()->count('medicine_id'),
                'shortages' => $shortages->count(),
                'expiring' => Batch::whereBetween('expiry_date', [now('Asia/Jakarta')->startOfDay(), now('Asia/Jakarta')->addMonths(3)])->count(),
            ],
            'shortages' => $shortages,
            'balances' => StockBalance::with(['medicine.unit', 'location'])->where('quantity', '>', 0)->orderByDesc('updated_at')->limit(12)->get(),
            'chart' => $chart,
        ]);
    }
}
