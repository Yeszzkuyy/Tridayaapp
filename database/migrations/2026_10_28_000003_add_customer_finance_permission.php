<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission khusus data finance customer (billing address, finance PIC,
 * NPWP). Sales tetap boleh melihat customer dan quotation miliknya, tetapi
 * tidak lagi melihat data penagihan sensitif ini.
 */
return new class extends Migration
{
    private array $roles = [
        'admin',
        'purchasing',
        'finance',
        'management',
        'ceo',
        'super-admin',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'view-customer-finance',
            'guard_name' => 'web',
        ]);

        foreach ($this->roles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', 'view-customer-finance')
            ->where('guard_name', 'web')
            ->first()
            ?->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
