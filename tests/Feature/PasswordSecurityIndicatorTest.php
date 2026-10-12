<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordSecurityIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_shows_red_for_default_password(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('marketing');

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(__('Password not changed yet'));
    }

    public function test_changing_own_password_marks_account_secure(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('marketing');

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ])
            ->assertRedirect();

        $this->assertNotNull($user->fresh()->password_changed_at);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee(__('Password not changed yet'));
    }

    public function test_admin_reset_does_not_mark_account_secure(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $user = User::factory()->create();
        $user->assignRole('marketing');

        $this->actingAs($admin)
            ->put(route('admin-panel.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'reset-oleh-admin-123',
                'password_confirmation' => 'reset-oleh-admin-123',
            ])
            ->assertRedirect();

        $this->assertNull($user->fresh()->password_changed_at);
    }

    public function test_yeski_gets_running_border(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $yeski = User::factory()->create(['email' => 'yehezkielmayogi.ptnti@gmail.com']);
        $yeski->assignRole('super-admin');

        $this->actingAs($yeski)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('conic-gradient', false);
    }

    public function test_running_border_visible_outside_profile(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $yeski = User::factory()->create(['email' => 'yehezkielmayogi.ptnti@gmail.com']);
        $yeski->assignRole('super-admin');
        $biasa = User::factory()->create();
        $biasa->assignRole('marketing');

        // Sidebar (dashboard) Yeski: ada ring running
        $this->actingAs($yeski)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('conic-gradient', false);

        // Sidebar user biasa: tidak ada
        $this->actingAs($biasa)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('conic-gradient', false);
    }

    public function test_avatar_wrapper_stays_square_in_flex_contexts(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $yeski = User::factory()->create(['email' => 'yehezkielmayogi.ptnti@gmail.com']);

        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-user-avatar :user="$user" size="w-10 h-10" />',
            ['user' => $yeski]
        );

        // Wrapper anti-melar (penyebab avatar lonjong di list flex) + ring running tetap ada
        $this->assertStringContainsString('self-center aspect-square', $html);
        $this->assertStringContainsString('conic-gradient', $html);
        // Chasing ganda mengikuti variabel tema aktif
        $this->assertStringContainsString('var(--accent-500)', $html);
        $this->assertStringContainsString('var(--accent-300)', $html);
    }

    public function test_avatar_shows_google_style_decoration_by_default(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();

        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-user-avatar :user="$user" size="w-10 h-10" />',
            ['user' => $user]
        );

        // Cukup warna ring: hijau meski password masih bawaan, tanpa dot
        $this->assertStringContainsString('ring-accent-500', $html);
        $this->assertStringNotContainsString('ring-red-500', $html);
        $this->assertStringNotContainsString('-right-0.5 -top-0.5', $html);
    }

    public function test_avatar_hover_is_glow_not_static_ring(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create(['avatar' => 'avatars/coba.png']);

        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-user-avatar :user="$user" size="w-10 h-10" />',
            ['user' => $user]
        );

        $this->assertStringContainsString('hover:shadow-accent-500/50', $html);
        $this->assertStringNotContainsString('hover:ring-accent-300', $html);
    }

    public function test_avatar_always_follows_theme_regardless_of_password_status(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();

        $before = \Illuminate\Support\Facades\Blade::render(
            '<x-user-avatar :user="$user" size="w-10 h-10" />',
            ['user' => $user]
        );
        $this->assertStringContainsString('ring-accent-500', $before);
        $this->assertStringNotContainsString('ring-red-500', $before);

        $user->forceFill(['password_changed_at' => now()])->save();

        $after = \Illuminate\Support\Facades\Blade::render(
            '<x-user-avatar :user="$user" size="w-10 h-10" />',
            ['user' => $user->fresh()]
        );
        $this->assertStringContainsString('ring-accent-500', $after);
        $this->assertStringNotContainsString('ring-red-500', $after);
    }

    public function test_yeski_ring_is_neutral_so_running_effect_shows(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $yeski = User::factory()->create(['email' => 'yehezkielmayogi.ptnti@gmail.com']);

        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-user-avatar :user="$user" size="w-10 h-10" />',
            ['user' => $yeski]
        );

        $this->assertStringNotContainsString('ring-red-500', $html);
        $this->assertStringNotContainsString('ring-accent-500', $html);
        $this->assertStringContainsString('conic-gradient', $html);
    }
}
