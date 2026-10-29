<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    private ?string $testStoragePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Storage test diarahkan ke folder sementara milik proses test.
        // Folder storage/framework/testing bisa tertinggal milik user lain
        // (mis. setelah test dijalankan user berbeda), dan Storage::fake()
        // akan gagal membaca folder itu. Isolasi ini juga mencegah test
        // saling meninggalkan file.
        $this->testStoragePath = sys_get_temp_dir().'/3dy-test-'.uniqid('', true);
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath.'/framework/testing/disks');

        // Test tidak bergantung pada hasil build Vite: di alur kerja multi-
        // worktree, `public/build` tiap worktree di-symlink ke build `main`,
        // sehingga entry baru (mis. island notification-center) belum tentu
        // ada di manifest saat test dijalankan.
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->testStoragePath && is_dir($this->testStoragePath)) {
            File::deleteDirectory($this->testStoragePath);
        }

        $this->testStoragePath = null;
    }

    /**
     * User untuk test fitur umum (profil, settings, asisten, push).
     * Sejak pending-approval middleware aktif, user tanpa role akan
     * diarahkan ke halaman pending; test fitur perlu role nyata.
     */
    protected function makeUserWithRole(array $attributes = [], string $role = 'admin'): User
    {
        if (! Role::where('name', $role)->exists()) {
            $this->seed(RoleAndPermissionSeeder::class);
        }

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
