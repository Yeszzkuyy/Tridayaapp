<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Lead;
use App\Models\User;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Ingat pemegang super-admin sebelum assignment dihapus
        // (guard: di DB fresh, role belum ada — lewati tanpa throw)
        $prevSuperAdmins = Role::where('name', 'super-admin')->exists()
            ? User::role('super-admin')->pluck('id')->all()
            : [];

        // Bersihkan data lama
        DB::table('model_has_roles')->delete();
        DB::table('model_has_permissions')->delete();
        DB::table('role_has_permissions')->delete();
        DB::table('roles')->delete();
        DB::table('permissions')->delete();

        // === 1. Create Permissions (pola manage-{divisi} & view-{divisi}) ===
        $divisi = ['marketing', 'sales', 'admin', 'technician', 'monitoring'];
        $permissions = [];

        foreach ($divisi as $d) {
            $permissions[] = "manage-{$d}";
            $permissions[] = "view-{$d}";
        }

        // Izin lintas divisi: semua role boleh lihat Customer & Trash tanpa membuka menu divisi lain
        $permissions[] = 'view-customer';
        $permissions[] = 'view-trash';

        // Monitoring anggota divisi (lead & manage tiap divisi)
        $permissions[] = 'monitor-marketing';
        $permissions[] = 'monitor-technical';

        // Hub Management: melihat & meng-assign lead + membuka placeholder Manage
        $permissions[] = 'manage-sales-leads';

        // Inside Sales: mengerjakan task permintaan dari sales (bukan manage divisi)
        $permissions[] = 'manage-inside-sales';

        foreach ($permissions as $name) {
            Permission::create(['name' => $name, 'guard_name' => 'web']);
        }

        // === 2. Create Roles & Assign Permissions ===
        $common = ['view-customer', 'view-trash'];

        $mk = fn (string $name, array $perms) => tap(
            Role::create(['name' => $name, 'guard_name' => 'web']),
            fn ($r) => $r->givePermissionTo($perms)
        );

        // Divisi
        $mk('marketing', ['manage-marketing', 'view-marketing', ...$common]);
        $mk('sales', ['manage-sales', 'view-sales', 'view-admin', ...$common]);

        // Sales per PT: izin sama dengan sales, tulis dibatasi PT-nya (PtAccess).
        // Satu user boleh pegang banyak PT. Tanpa role PT = tanpa batas.
        $salesPerms = ['manage-sales', 'view-sales', 'view-admin', ...$common];
        foreach (Lead::PT_GROUPS as $pt) {
            $mk('sales-'.strtolower($pt), $salesPerms);
        }
        $mk('admin', ['manage-admin', 'view-admin', 'view-sales', ...$common]);
        $mk('technician', ['manage-technician', 'view-technician', ...$common]);

        // Lead teknisi: koordinasi teknisi + read-only sales & admin
        $mk('lead-technician', ['manage-technician', 'view-technician', 'monitor-technical', 'view-sales', 'view-admin', ...$common]);

        // Management tunggal: kelola menu management + view-only recap semua divisi
        $mk('management', ['manage-sales-leads', 'view-marketing', 'view-sales', 'view-technician', 'view-admin', 'view-monitoring', 'monitor-marketing', ...$common]);

        // Lead marketing: kerja marketing + read-only teknisi (dok. instalasi)
        $mk('lead-marketing', ['manage-marketing', 'view-marketing', 'monitor-marketing', 'view-technician', ...$common]);

        // Inside Sales: hanya task yang di-assign kepadanya (lead terlihat bila ada task)
        $mk('inside-sales', ['manage-inside-sales', ...$common]);

        // Prakerin: read-only di divisinya masing-masing
        $mk('prakerin-technician', ['view-technician', ...$common]);
        $mk('prakerin-admin', ['view-admin', ...$common]);

        // CEO: semua view, tanpa manage (read-only; enforcement menyusul)
        $mk('ceo', ['view-marketing', 'view-sales', 'view-admin', 'view-technician', 'view-monitoring', ...$common]);

        $mk('super-admin', Permission::pluck('name')->all());

        // === 3. Migrate Existing Users (jangan hapus user, hanya ganti role) ===
        $map = [
            'teknisi' => 'technician',
            'engineer' => 'technician',
            'marketing-lead' => 'lead-marketing',
            'manager' => 'ceo',
            // Role manage-*/lama dilebur ke management tunggal
            'manage-marketing' => 'management',
            'manage-technical' => 'management',
            'manage-admin' => 'management',
        ];

        // Override per orang (disepakati): Syifa Lead Marketing, Hanifah Admin.
        $personMap = [
            'syifa@tridayaapp.com' => 'lead-marketing',
            'hanifah@tridayaapp.com' => 'admin',
        ];

        foreach (User::withTrashed()->get() as $user) {
            $user->syncRoles([]);

            // Yeski selalu jadi super-admin
            if ($user->email === 'yehezkielmayogi.ptnti@gmail.com' || $user->id === 1) {
                $user->assignRole('super-admin');
                continue;
            }

            $oldRole = $user->getOriginal('role') ?? $user->role;
            $target = $personMap[$user->email] ?? $map[$oldRole] ?? $oldRole;

            if ($target && Role::where('name', $target)->exists()) {
                $user->assignRole($target);
                // Selaraskan kolom role legacy agar query where('role', ...) tetap akurat
                if ($user->role !== $target) {
                    $user->forceFill(['role' => $target])->save();
                }
            }
        }

        // === 4. Kembalikan super-admin ke user yang punya kolom role=super-admin ===
        User::where('role', 'super-admin')->get()
            ->each(fn (User $u) => $u->assignRole('super-admin'));

        // === 5. Kembalikan super-admin yang hilang akibat wipe (kolom role=guest tapi tadinya super-admin) ===
        User::whereIn('id', $prevSuperAdmins)->get()
            ->each(fn (User $u) => $u->hasRole('super-admin') ?: $u->assignRole('super-admin'));

        // Forget di AWAL saja tidak cukup: seeder tidak transaksional — request web
        // yang datang di tengah (saat roles/permissions ke-reset) bisa meng-cache
        // kondisi setengah jadi selama 24 jam → user kehilangan menu/403 padahal DB benar.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
