<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Batch akses — permission baca admin yang dipisah per area sensitif.
 *
 * Migration ini aditif: menambah permission dan assignment ke role yang
 * sudah ada. Tidak mengubah permission role Sales/sales-* dan tidak
 * menghapus role/data.
 */
return new class extends Migration
{
    private array $permissions = [
        'view-admin-hub',
        'view-supplier-pos',
        'view-supplier-invoices',
        'view-payment-proofs',
        'view-goods-receipts',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Jangan membuat role resmi di database kosong. Pada instalasi baru,
        // RoleAndPermissionSeeder yang membuat seluruh role. Migration ini
        // hanya menambahkan assignment pada instalasi yang sudah punya role
        // (produksi/existing), sehingga test yang mengecek eksistensi role
        // tidak salah menganggap seeder sudah dijalankan.
        if (! Role::where('guard_name', 'web')->exists()) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        $finance = Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);

        $assignments = [
            'admin' => $this->permissions,
            'purchasing' => [
                'view-admin-hub',
                'view-supplier-pos',
                'view-supplier-invoices',
                'view-goods-receipts',
            ],
            'finance' => $this->permissions,
            'management' => $this->permissions,
            'ceo' => $this->permissions,
            'super-admin' => $this->permissions,
        ];

        foreach ($assignments as $roleName => $permissionNames) {
            $role = $roleName === 'finance'
                ? $finance
                : Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                continue;
            }

            $permissions = Permission::where('guard_name', 'web')
                ->whereIn('name', $permissionNames)
                ->get();

            $role->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->get()
            ->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
