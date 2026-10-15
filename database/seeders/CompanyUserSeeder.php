<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun karyawan riil (sumber kebenaran versi-git).
 *
 * Dijalankan TERAKHIR di DatabaseSeeder agar assignment role di sini
 * menang atas migrasi RoleAndPermissionSeeder. Idempoten: aman di-rerun,
 * me-restore user yang terhapus/ter-soft-delete secara tidak sengaja.
 * Password hanya diset saat pembuatan — rerun TIDAK me-reset password.
 */
class CompanyUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            // [nama, email, [roles...]] — role pertama = kolom role legacy
            ['Victor GM', 'victor@tridayaapp.com', ['ceo']],
            ['Ardian Widhi Prabowo', 'ardian@tridayaapp.com', ['management', 'lead-technician', 'sales']],
            ['Christina Yoan', 'christina@tridayaapp.com', ['management', 'sales']],
            ['Yanita', 'yanita@tridayaapp.com', ['management', 'sales']],
            ['Ayu', 'ayu@tridayaapp.com', ['management', 'sales']],
            ['Syifa', 'syifa@tridayaapp.com', ['lead-marketing']],
            ['Amir', 'amir@tridayaapp.com', ['lead-technician']],
            ['Anggie', 'anggie@tridayaapp.com', ['marketing']],
            ['Khairil', 'khairil@tridayaapp.com', ['technician']],
            ['Gilar', 'gilar@tridayaapp.com', ['technician']],
            ['Ardi', 'ardi@tridayaapp.com', ['technician']],
            ['Fanuel', 'fanuel@tridayaapp.com', ['technician']],
            // Deka: technician yang sementara pegang super-admin
            ['Deka', 'deka@tridayaapp.com', ['technician', 'super-admin']],
            ['Zero', 'zero@tridayaapp.com', ['technician']],
            ['Naufal', 'naufal@tridayaapp.com', ['technician']],
            ['Hanifah', 'hanifah@tridayaapp.com', ['admin']],
            ['Vanesha', 'vanesha@tridayaapp.com', ['admin']],
            ['Adi Santosa', 'adi.santosa@tridayaapp.com', ['sales']],
            ['Hendry', 'hendry@tridayaapp.com', ['sales']],
            ['Irfan', 'irfan@tridayaapp.com', ['sales']],
            ['Deby', 'deby@tridayaapp.com', ['sales']],
            ['April', 'april@tridayaapp.com', ['admin']],
            ['Audi', 'audi@tridayaapp.com', ['sales']],
            ['Dini', 'dini@tridayaapp.com', ['sales']],
            ['Ryan', 'ryan@tridayaapp.com', ['technician']],
        ];

        foreach ($users as [$name, $email, $roles]) {
            $user = User::withTrashed()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('3dy@11830'),
                    'role' => $roles[0],
                    'email_verified_at' => now(),
                ]
            );

            if ($user->trashed()) {
                $user->restore();
            }

            $user->syncRoles($roles);

            if ($user->role !== $roles[0]) {
                $user->forceFill(['role' => $roles[0]])->save();
            }
        }
    }
}
