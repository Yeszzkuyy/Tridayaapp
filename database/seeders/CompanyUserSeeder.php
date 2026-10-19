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
            // Role sales-<PT> mengunci tulis customer/lead ke PT itu (lihat App\Support\PtAccess).
            // User management tetap tanpa batas walau punya role sales-*.
            ['Victor GM', 'victor@tridayaapp.com', ['ceo']],
            ['Ardian Widhi Prabowo', 'ardian@tridayaapp.com', ['management', 'lead-technician', 'sales', 'sales-nti']],
            ['Christina Yoan', 'christina@tridayaapp.com', ['management', 'sales', 'sales-mgk']],
            ['Yanita', 'yanita@tridayaapp.com', ['management', 'sales', 'sales-nti']],
            ['Ayu', 'ayu@tridayaapp.com', ['management', 'sales', 'sales-tps']],
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
            ['Adi Santosa', 'adi.santosa@tridayaapp.com', ['sales', 'sales-mgk']],
            ['Hendry', 'hendry@tridayaapp.com', ['sales', 'sales-mgk']],
            ['Irfan', 'irfan@tridayaapp.com', ['sales', 'sales-nti']],
            ['Deby', 'deby@tridayaapp.com', ['sales', 'sales-tps']],
            ['April', 'april@tridayaapp.com', ['admin']],
            ['Audi', 'audi@tridayaapp.com', ['inside-sales', 'sales', 'sales-nti']],
            ['Dini', 'dini@tridayaapp.com', ['inside-sales', 'sales', 'sales-nti']],
            ['Felisia', 'felisia@tridayaapp.com', ['sales', 'sales-wani']],
            ['Jeremy', 'jeremy@tridayaapp.com', ['sales', 'sales-nti']],
            ['Tsamara', 'tsamara@tridayaapp.com', ['sales', 'sales-nti']],
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
