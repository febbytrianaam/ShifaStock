<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NightlyCheckController;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockParameter;
use App\Models\User;
use App\Services\InventoryReconciliationService;
use App\Support\UatEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UatSeeder extends Seeder
{
    public function run(): void
    {
        UatEnvironment::assertSafe();
        if (User::exists() || DB::table('inventory_movements')->exists()) {
            throw new RuntimeException('UAT sudah berisi akun/transaksi; seeder tidak menghapus atau menimpa hasil UAT.');
        }
        $accounts = json_decode(file_get_contents(storage_path('app/private/uat/accounts.json')), true, flags: JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($accounts) {
            foreach ($accounts['accounts'] as $account) {
                User::create(['username' => $account['username'], 'name' => $account['name'],
                    'email' => $account['username'].'@uat.example.test', 'role' => Role::from($account['role']),
                    'password' => $account['password'], 'is_active' => true]);
            }
            $actor = User::where('username', 'apoteker_uat')->sole();
            Auth::setUser($actor);
            $medicines = Medicine::where('is_active', true)->whereNotNull('unit_id')
                ->whereNotNull('dosage_form')->where('dosage_form', '<>', '')->orderBy('id')->limit(3)->get();
            if ($medicines->count() < 3) {
                throw new RuntimeException('Perlu setidaknya tiga obat bersatuan dan aktif untuk fixture UAT.');
            }
            $warehouse = Location::where('code', 'GDG')->where('is_active', true)->sole();
            $pharmacy = Location::where('code', 'APT')->where('is_active', true)->sole();
            $time = now('Asia/Jakarta')->format('Y-m-d\TH:i');
            $invoke = function (string $method, array $payload, string $controller = InventoryController::class) use ($actor) {
                $request = Request::create('/uat-fixture', 'POST', $payload);
                $request->setUserResolver(fn () => $actor);
                $request->setLaravelSession(app('session')->driver());
                app()->instance('request', $request);

                return app()->call([app($controller), $method], ['request' => $request, 'r' => $request]);
            };
            $openings = [
                [0, $warehouse->id, 'UAT-DEKAT', 80, 30], [0, $warehouse->id, 'UAT-LAMA', 120, 240],
                [0, $pharmacy->id, 'UAT-DEKAT', 25, 30], [1, $warehouse->id, 'UAT-B-01', 10, 180],
                [1, $pharmacy->id, 'UAT-B-01', 3, 180], [2, $warehouse->id, 'UAT-EXPIRED', 4, -10],
            ];
            foreach ($openings as $index => [$medicine, $location, $batch, $quantity, $days]) {
                $invoke('storeOpeningBalance', ['reference' => 'UAT-STOK-'.($index + 1), 'medicine_id' => $medicines[$medicine]->id,
                    'location_id' => $location, 'batch_number' => $batch, 'quantity' => $quantity,
                    'expiry_date' => now('Asia/Jakarta')->addDays($days)->toDateString(), 'occurred_at' => $time,
                    'notes' => 'SIMULASI UAT, bukan stok fisik klinik.']);
            }
            foreach (['post', 'draft'] as $index => $action) {
                $invoke('storeReceipt', ['reference' => 'UAT-FKT-'.($index + 1), 'action' => $action, 'occurred_at' => $time,
                    'destination_location_id' => $warehouse->id, 'counterparty' => 'PBF Simulasi UAT',
                    'supplier_invoice_number' => 'UAT-INV-'.($index + 1), 'notes' => 'SIMULASI UAT, bukan faktur klinik.',
                    'items' => [['medicine_id' => $medicines[0]->id, 'batch_number' => 'UAT-TERIMA-'.($index + 1),
                        'expiry_date' => now('Asia/Jakarta')->addMonths(10)->toDateString(), 'quantity' => 15]]]);
            }
            $invoke('storeTransfer', ['reference' => 'UAT-TRF-1', 'medicine_id' => $medicines[0]->id,
                'source_location_id' => $warehouse->id, 'destination_location_id' => $pharmacy->id,
                'quantity' => 5, 'occurred_at' => $time, 'notes' => 'SIMULASI transfer FEFO UAT.']);
            foreach ([$warehouse, $pharmacy] as $location) {
                StockParameter::create(['medicine_id' => $medicines[1]->id, 'location_id' => $location->id, 'minimum_quantity' => 20]);
            }
            $invoke('store', ['date' => now('Asia/Jakarta')->toDateString(), 'location_id' => $pharmacy->id], NightlyCheckController::class);
            $invoke('storeOpnameSession', ['period' => now('Asia/Jakarta')->format('Y-m'), 'location_id' => $warehouse->id, 'cutoff_at' => $time]);
            $report = app(InventoryReconciliationService::class)->report();
            if ($report['mismatched_pairs'] || $report['batch_identity_issues']) {
                throw new RuntimeException('Rekonsiliasi fixture UAT tidak sesuai.');
            }
        });
    }
}
