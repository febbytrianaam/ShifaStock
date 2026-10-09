<?php

namespace App\Support;

use App\Enums\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Permissions
{
    public const VIEW_DASHBOARD = 'view-dashboard';
    public const VIEW_INVENTORY = 'view-inventory';
    public const VIEW_MEDICINES = 'view-medicines';
    public const VIEW_RECEIPTS = 'view-receipts';
    public const VIEW_TRANSFERS = 'view-transfers';
    public const VIEW_NIGHTLY_CHECKS = 'view-nightly-checks';
    public const VIEW_OPNAME = 'view-opname';
    public const VIEW_NIGHTLY_HISTORY = 'view-nightly-history';
    public const VIEW_SHORTAGES = 'view-shortages';
    public const VIEW_EXPIRY = 'view-expiry';
    public const VIEW_REPORTS = 'view-reports';
    public const OPERATE_INVENTORY = 'operate-inventory';
    public const MANAGE_MEDICINES = 'manage-medicines';
    public const FINALIZE_OPNAME = 'finalize-opname';
    public const MANAGE_USERS = 'manage-users';
    public const MANAGE_PERMISSIONS = 'manage-permissions';
    public const VIEW_AUDIT_LOGS = 'view-audit-logs';

    public static function definitions(): array
    {
        return [
            'Menu' => [
                self::VIEW_DASHBOARD => 'Lihat dashboard',
                self::VIEW_MEDICINES => 'Lihat menu Data Obat',
                self::VIEW_RECEIPTS => 'Lihat menu Penerimaan Obat',
                self::VIEW_TRANSFERS => 'Lihat menu Transfer Gudang-Apotek',
                self::VIEW_NIGHTLY_CHECKS => 'Lihat menu Pemeriksaan Harian',
                self::VIEW_OPNAME => 'Lihat menu Stock Opname Periodik',
                self::VIEW_NIGHTLY_HISTORY => 'Lihat menu Riwayat Pemeriksaan',
                self::VIEW_SHORTAGES => 'Lihat menu Kekurangan & Permintaan',
                self::VIEW_EXPIRY => 'Lihat menu Monitoring Kedaluwarsa',
                self::VIEW_REPORTS => 'Lihat menu Laporan',
            ],
            'Persediaan' => [
                self::VIEW_INVENTORY => 'Lihat dashboard persediaan',
                self::OPERATE_INVENTORY => 'Input penerimaan, transfer, dan pemeriksaan harian',
                self::MANAGE_MEDICINES => 'Kelola master obat',
                self::FINALIZE_OPNAME => 'Review dan finalisasi pemeriksaan',
            ],
            'Administrasi' => [
                self::MANAGE_USERS => 'Kelola pengguna',
                self::MANAGE_PERMISSIONS => 'Kelola hak akses',
                self::VIEW_AUDIT_LOGS => 'Lihat audit log',
            ],
        ];
    }

    public static function all(): array
    {
        $permissions = [];
        foreach (self::definitions() as $group) {
            $permissions = array_merge($permissions, array_keys($group));
        }

        return $permissions;
    }

    public static function defaults(): array
    {
        return [
            Role::Administrator->value => [
                self::VIEW_DASHBOARD => true,
                self::VIEW_INVENTORY => false,
                self::VIEW_MEDICINES => false,
                self::VIEW_RECEIPTS => false,
                self::VIEW_TRANSFERS => false,
                self::VIEW_NIGHTLY_CHECKS => false,
                self::VIEW_OPNAME => false,
                self::VIEW_NIGHTLY_HISTORY => false,
                self::VIEW_SHORTAGES => false,
                self::VIEW_EXPIRY => false,
                self::VIEW_REPORTS => false,
                self::OPERATE_INVENTORY => false,
                self::MANAGE_MEDICINES => false,
                self::FINALIZE_OPNAME => false,
                self::MANAGE_USERS => true,
                self::MANAGE_PERMISSIONS => true,
                self::VIEW_AUDIT_LOGS => true,
            ],
            Role::Pharmacy->value => [
                self::VIEW_DASHBOARD => true,
                self::VIEW_INVENTORY => true,
                self::VIEW_MEDICINES => true,
                self::VIEW_RECEIPTS => true,
                self::VIEW_TRANSFERS => true,
                self::VIEW_NIGHTLY_CHECKS => true,
                self::VIEW_OPNAME => true,
                self::VIEW_NIGHTLY_HISTORY => true,
                self::VIEW_SHORTAGES => true,
                self::VIEW_EXPIRY => true,
                self::VIEW_REPORTS => true,
                self::OPERATE_INVENTORY => true,
                self::MANAGE_MEDICINES => false,
                self::FINALIZE_OPNAME => false,
                self::MANAGE_USERS => false,
                self::MANAGE_PERMISSIONS => false,
                self::VIEW_AUDIT_LOGS => false,
            ],
            Role::Pharmacist->value => [
                self::VIEW_DASHBOARD => true,
                self::VIEW_INVENTORY => true,
                self::VIEW_MEDICINES => true,
                self::VIEW_RECEIPTS => true,
                self::VIEW_TRANSFERS => true,
                self::VIEW_NIGHTLY_CHECKS => true,
                self::VIEW_OPNAME => true,
                self::VIEW_NIGHTLY_HISTORY => true,
                self::VIEW_SHORTAGES => true,
                self::VIEW_EXPIRY => true,
                self::VIEW_REPORTS => true,
                self::OPERATE_INVENTORY => true,
                self::MANAGE_MEDICINES => true,
                self::FINALIZE_OPNAME => true,
                self::MANAGE_USERS => false,
                self::MANAGE_PERMISSIONS => false,
                self::VIEW_AUDIT_LOGS => false,
            ],
            Role::Guest->value => [
                self::VIEW_DASHBOARD => true,
                self::VIEW_INVENTORY => true,
                self::VIEW_MEDICINES => true,
                self::VIEW_RECEIPTS => true,
                self::VIEW_TRANSFERS => true,
                self::VIEW_NIGHTLY_CHECKS => true,
                self::VIEW_OPNAME => true,
                self::VIEW_NIGHTLY_HISTORY => true,
                self::VIEW_SHORTAGES => true,
                self::VIEW_EXPIRY => true,
                self::VIEW_REPORTS => true,
                self::OPERATE_INVENTORY => false,
                self::MANAGE_MEDICINES => false,
                self::FINALIZE_OPNAME => false,
                self::MANAGE_USERS => false,
                self::MANAGE_PERMISSIONS => false,
                self::VIEW_AUDIT_LOGS => false,
            ],
        ];
    }

    public static function locked(): array
    {
        return [
            Role::Administrator->value => [
                self::VIEW_DASHBOARD,
                self::MANAGE_USERS,
                self::MANAGE_PERMISSIONS,
                self::VIEW_AUDIT_LOGS,
            ],
        ];
    }

    public static function roleCan(Role|string $role, string $permission): bool
    {
        $roleValue = $role instanceof Role ? $role->value : $role;

        if (in_array($permission, self::locked()[$roleValue] ?? [], true)) {
            return true;
        }

        try {
            if (! Schema::hasTable('role_permissions')) {
                return self::defaultFor($roleValue, $permission);
            }

            $allowed = DB::table('role_permissions')
                ->where('role', $roleValue)
                ->where('permission', $permission)
                ->value('allowed');

            return $allowed === null ? self::defaultFor($roleValue, $permission) : (bool) $allowed;
        } catch (Throwable) {
            return self::defaultFor($roleValue, $permission);
        }
    }

    public static function matrix(): array
    {
        $matrix = self::defaults();

        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')
                ->get(['role', 'permission', 'allowed'])
                ->each(function ($row) use (&$matrix) {
                    if (isset($matrix[$row->role]) && in_array($row->permission, self::all(), true)) {
                        $matrix[$row->role][$row->permission] = (bool) $row->allowed;
                    }
                });
        }

        foreach (self::locked() as $role => $permissions) {
            foreach ($permissions as $permission) {
                $matrix[$role][$permission] = true;
            }
        }

        return $matrix;
    }

    public static function syncMatrix(array $input): void
    {
        DB::transaction(function () use ($input) {
            foreach (Role::cases() as $role) {
                foreach (self::all() as $permission) {
                    $allowed = isset($input[$role->value][$permission]);

                    if (in_array($permission, self::locked()[$role->value] ?? [], true)) {
                        $allowed = true;
                    }

                    DB::table('role_permissions')->updateOrInsert(
                        ['role' => $role->value, 'permission' => $permission],
                        ['allowed' => $allowed, 'updated_at' => now(), 'created_at' => now()],
                    );
                }
            }
        });
    }

    private static function defaultFor(string $role, string $permission): bool
    {
        return (bool) (self::defaults()[$role][$permission] ?? false);
    }
}
