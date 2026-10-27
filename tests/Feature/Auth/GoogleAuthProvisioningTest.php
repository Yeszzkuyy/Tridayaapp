<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        config(['services.google.client_id' => 'dummy-google-client-id']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_new_google_user_is_automatically_provisioned_without_roles_and_redirected_to_pending(): void
    {
        $mockGoogleUser = new SocialiteUser();
        $mockGoogleUser->id = 'google-id-12345';
        $mockGoogleUser->name = 'Karyawan Baru';
        $mockGoogleUser->email = 'karyawan.baru@gmail.com';
        $mockGoogleUser->avatar = 'https://lh3.googleusercontent.com/avatar-test.jpg';

        $mockDriver = Mockery::mock('stdClass');
        $mockDriver->shouldReceive('user')->once()->andReturn($mockGoogleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($mockDriver);

        $response = $this->get(route('auth.google.callback'));

        // Harus login dan diarahkan ke pending approval
        $this->assertAuthenticated();
        $response->assertRedirect(route('pending.approval'));

        // User harus tersimpan di database
        $this->assertDatabaseHas('users', [
            'email' => 'karyawan.baru@gmail.com',
            'name' => 'Karyawan Baru',
            'google_id' => 'google-id-12345',
            'google_avatar' => 'https://lh3.googleusercontent.com/avatar-test.jpg',
        ]);

        $createdUser = User::where('email', 'karyawan.baru@gmail.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->roles->isEmpty(), 'User baru dari Google harus tanpa Spatie role (pending)');
    }

    public function test_existing_user_with_role_authenticates_via_google_and_redirects_to_dashboard(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'karyawan.lama@gmail.com',
            'name' => 'Karyawan Lama',
            'google_id' => null,
        ]);
        $existingUser->assignRole('technician');

        $mockGoogleUser = new SocialiteUser();
        $mockGoogleUser->id = 'google-id-99999';
        $mockGoogleUser->name = 'Karyawan Lama';
        $mockGoogleUser->email = 'karyawan.lama@gmail.com';
        $mockGoogleUser->avatar = 'https://lh3.googleusercontent.com/avatar-lama.jpg';

        $mockDriver = Mockery::mock('stdClass');
        $mockDriver->shouldReceive('user')->once()->andReturn($mockGoogleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($mockDriver);

        $response = $this->get(route('auth.google.callback'));

        $this->assertAuthenticatedAs($existingUser);
        $response->assertRedirect(route('dashboard'));

        $existingUser->refresh();
        $this->assertEquals('google-id-99999', $existingUser->google_id);
        $this->assertEquals('https://lh3.googleusercontent.com/avatar-lama.jpg', $existingUser->google_avatar);
    }

    public function test_user_without_roles_is_guarded_from_dashboard_and_redirected_to_pending(): void
    {
        $userWithoutRole = User::factory()->create();

        $response = $this->actingAs($userWithoutRole)->get('/dashboard');

        $response->assertRedirect(route('pending.approval'));
    }

    public function test_user_without_roles_can_render_pending_approval_screen(): void
    {
        $userWithoutRole = User::factory()->create([
            'name' => 'Calon Karyawan',
            'email' => 'calon@gmail.com',
        ]);

        $response = $this->actingAs($userWithoutRole)->get(route('pending.approval'));

        $response->assertStatus(200);
        $response->assertSee('Calon Karyawan');
        $response->assertSee('calon@gmail.com');
        $response->assertSee(__('Menunggu Persetujuan Admin'));
        $response->assertDontSee('PT Tridaya Sinergi Berkelanjutan');
    }

    public function test_approved_user_visiting_pending_page_is_redirected_to_dashboard(): void
    {
        $approvedUser = User::factory()->create();
        $approvedUser->assignRole('sales');

        $response = $this->actingAs($approvedUser)->get(route('pending.approval'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_new_google_user_triggers_notification_to_super_admins(): void
    {
        $superAdmin = User::factory()->create(['name' => 'Boss Admin']);
        $superAdmin->assignRole('super-admin');

        $mockGoogleUser = new SocialiteUser();
        $mockGoogleUser->id = 'google-id-notif';
        $mockGoogleUser->name = 'Budi Baru';
        $mockGoogleUser->email = 'budi.baru@gmail.com';
        $mockGoogleUser->avatar = 'https://lh3.googleusercontent.com/avatar.jpg';

        $mockDriver = Mockery::mock('stdClass');
        $mockDriver->shouldReceive('user')->once()->andReturn($mockGoogleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($mockDriver);

        $this->get(route('auth.google.callback'));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $superAdmin->id,
            'type' => \App\Notifications\NewUserPendingApprovalNotification::class,
        ]);

        $notification = $superAdmin->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertEquals('budi.baru@gmail.com', $notification->data['email']);
        $this->assertStringContainsString('/admin-panel/users/', $notification->data['url']);
        $this->assertStringContainsString('/edit', $notification->data['url']);
    }
}
