<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pastikan role purchasing/finance tersedia pada instalasi lama.
 *
 * Role ini tidak dibuat oleh migration permission sebelumnya jika role
 * purchasing belum ada di database. Migration ini aditif dan tidak
 * menghapus permission/role lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Instalasi baru dibuat oleh RoleAndPermissionSeeder, bukan migration.
        if (! Role::where('guard_name', 'web')->exists()) {
            return;
        }

        $purchasing = Role::firstOrCreate(['name' => 'purchasing', 'guard_name' => 'web']);
        $finance = Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);

        $purchasing->givePermissionTo([
            'manage-admin', 'view-admin', 'view-sales', 'view-customer', 'view-trash',
            'view-admin-hub', 'view-supplier-pos', 'view-supplier-invoices', 'view-goods-receipts',
        ]);

        $finance->givePermissionTo([
            'view-admin', 'view-customer', 'view-trash',
            'view-admin-hub', 'view-supplier-pos', 'view-supplier-invoices',
            'view-payment-proofs', 'view-goods-receipts',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Tidak menghapus role/permission karena bisa sudah dipakai user.
    }
};
