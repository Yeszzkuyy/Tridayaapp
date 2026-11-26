<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuditLogAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_audit_log_dibaca_super_admin_management_dan_ceo(): void
    {
        foreach (['super-admin', 'management', 'ceo'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('admin-panel.audit-log'))
                ->assertOk();
        }
    }

    public function test_audit_log_ditolak_untuk_role_lain(): void
    {
        foreach (['sales', 'technician', 'admin'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('admin-panel.audit-log'))
                ->assertForbidden();
        }
    }

    public function test_filter_module_dan_action(): void
    {
        $admin = $this->user('super-admin');
        $this->actingAs($admin);

        AuditLogger::log('customer', 'archived', null, [], ['archived_at' => now()->toDateTimeString()], 'Customer archive filter test');
        AuditLogger::log('work_type', 'deactivated', null, ['is_active' => true], ['is_active' => false], 'Work Type filter test');

        $this->get(route('admin-panel.audit-log', ['module' => 'customer', 'action' => 'archived']))
            ->assertOk()
            ->assertSee('Customer archive filter test')
            ->assertDontSee('Work Type filter test');
    }

    public function test_data_sensitif_diredaksi(): void
    {
        $this->actingAs($this->user('super-admin'));

        $log = AuditLogger::log(
            'test',
            'updated',
            null,
            ['password' => 'secret', 'token' => 'abc', 'name' => 'Old'],
            ['password' => 'new-secret', 'name' => 'New'],
        );

        $this->assertSame('[REDACTED]', $log->old_values['password']);
        $this->assertSame('[REDACTED]', $log->old_values['token']);
        $this->assertSame('[REDACTED]', $log->new_values['password']);
        $this->assertSame('Old', $log->old_values['name']);
        $this->assertSame('New', $log->new_values['name']);
    }

    public function test_audit_log_model_tidak_bisa_diubah(): void
    {
        $this->actingAs($this->user('super-admin'));
        $log = AuditLogger::log('customer', 'archived', null, [], ['archived_at' => now()->toDateTimeString()]);

        $this->expectException(RuntimeException::class);
        $log->update(['description' => 'changed']);
    }

    public function test_audit_log_model_tidak_bisa_dihapus(): void
    {
        $this->actingAs($this->user('super-admin'));
        $log = AuditLogger::log('customer', 'archived', null, [], ['archived_at' => now()->toDateTimeString()]);

        $this->expectException(RuntimeException::class);
        $log->delete();
    }
}
