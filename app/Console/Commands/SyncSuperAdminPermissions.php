<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncSuperAdminPermissions extends Command
{
    protected $signature = 'app:sync-super-admin-permissions';

    protected $description = 'Samakan seluruh izin terbaru ke role super-admin tanpa mengubah role user lain.';

    public function handle(): int
    {
        $role = Role::findByName('super-admin', 'web');
        $role->syncPermissions(Permission::all());
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info('Super admin disinkronkan dengan '.Permission::count().' izin.');

        return self::SUCCESS;
    }
}
