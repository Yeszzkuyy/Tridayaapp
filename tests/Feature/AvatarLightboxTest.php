<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AvatarLightboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_list_shows_clickable_avatars()
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $admin = User::factory()->create(['avatar' => 'avatars/test.jpg']);
        $other = User::factory()->create();
        $admin->assignRole('super-admin');
        $other->assignRole('sales');

        $res = $this->actingAs($admin)->get('/admin-panel');
        $res->assertOk();

        $html = $res->getContent();
        $this->assertStringContainsString('view-avatar', $html);          // tombol klik avatar
        $this->assertStringContainsString('storage/avatars/test.jpg', $html);
        $this->assertStringContainsString('view-avatar.window', $html);  // lightbox terpasang
        $this->assertStringContainsString($other->name, $html);          // foto orang lain tampil di daftar
    }
}
