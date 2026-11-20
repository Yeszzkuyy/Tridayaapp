<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="password"[^>]*\btype="password"/s',
            $response->getContent()
        );
    }

    public function test_login_throttle_locks_account_across_rotating_ips(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.$i])
                ->post('/login', [
                    'email' => $user->email,
                    'password' => 'wrong-password',
                ]);
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_change_revokes_other_sessions_in_database(): void
    {
        config(['session.driver' => 'database', 'session.table' => 'sessions']);

        $user = User::factory()->create([
            'password' => bcrypt('old-password-123'),
            'remember_token' => 'old-remember-token',
        ]);

        DB::table('sessions')->insert([
            [
                'id' => 'other-device-session',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'OtherBrowser',
                'payload' => 'test',
                'last_activity' => time(),
            ],
        ]);

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'old-password-123',
            'password' => 'NewSecurePass#2026',
            'password_confirmation' => 'NewSecurePass#2026',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', [
            'id' => 'other-device-session',
            'user_id' => $user->id,
        ]);
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
    }

    public function test_users_with_role_can_authenticate_and_redirect_to_dashboard(): void
    {
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('technician');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_without_role_redirect_to_pending_approval(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('pending.approval', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_expired_session_logout_redirects_to_login(): void
    {
        // Token CSRF basi (sesi kedaluwarsa/hilang): bukan 419, melainkan ke login.
        $response = $this->call('POST', '/logout', ['_token' => 'token-basi']);

        $response->assertRedirect(route('login', absolute: false));
        $this->assertGuest();
    }

}
