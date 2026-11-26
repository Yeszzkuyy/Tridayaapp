<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogEventTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
        $this->actingAs($this->admin);
    }

    private function customer(string $name = 'PT Audit'): Customer
    {
        return Customer::create([
            'name' => $name,
            'company' => $name,
            'status' => 'deal',
            'pt_group' => 'NTI',
            'created_by' => $this->admin->id,
        ]);
    }

    private function workType(string $name = 'Instalasi Audit'): WorkType
    {
        return WorkType::create(['name' => $name]);
    }

    public function test_customer_archive_dan_unarchive_dicatat(): void
    {
        $customer = $this->customer();

        $this->patch(route('customers.archive', $customer))->assertRedirect();
        $archived = AuditLog::where('module', 'customer')->where('action', 'archived')->latest('id')->first();

        $this->assertNotNull($archived);
        $this->assertSame($this->admin->id, $archived->causer_id);
        $this->assertSame(Customer::class, $archived->subject_type);
        $this->assertSame($customer->id, $archived->subject_id);
        $this->assertNull($archived->old_values['archived_at']);
        $this->assertNotNull($archived->new_values['archived_at']);

        $this->patch(route('customers.unarchive', $customer))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'customer',
            'action' => 'unarchived',
            'subject_id' => $customer->id,
        ]);
    }

    public function test_customer_delete_dan_restore_dicatat(): void
    {
        $customer = $this->customer();

        $this->delete(route('customers.destroy', $customer))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'customer',
            'action' => 'deleted',
            'subject_id' => $customer->id,
        ]);

        $this->patch(route('trash.restore-customer', $customer->id))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'customer',
            'action' => 'restored',
            'subject_id' => $customer->id,
        ]);
    }

    public function test_customer_force_delete_dicatat(): void
    {
        $customer = $this->customer();
        $this->delete(route('customers.destroy', $customer))->assertRedirect();

        $this->delete(route('trash.destroy-customer', $customer->id))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'customer',
            'action' => 'force_deleted',
            'subject_id' => $customer->id,
        ]);
    }

    public function test_bulk_clear_customer_dicatat(): void
    {
        $customer = $this->customer();
        $this->delete(route('customers.destroy', $customer))->assertRedirect();

        $this->delete(route('trash.clear'))->assertRedirect();
        $log = AuditLog::where('module', 'customer')
            ->where('action', 'force_deleted')
            ->where('subject_id', $customer->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertTrue((bool) ($log->metadata['bulk'] ?? false));
    }

    public function test_work_type_create_update_delete_dicatat(): void
    {
        $this->post(route('admin-panel.work-types.store'), ['name' => 'Instalasi Audit'])
            ->assertRedirect();
        $workType = WorkType::where('name', 'Instalasi Audit')->firstOrFail();

        $this->put(route('admin-panel.work-types.update', $workType), ['name' => 'Instalasi Audit Baru'])
            ->assertRedirect();

        $this->delete(route('admin-panel.work-types.destroy', $workType))->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['module' => 'work_type', 'action' => 'created', 'subject_id' => $workType->id]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'work_type', 'action' => 'updated', 'subject_id' => $workType->id]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'work_type', 'action' => 'deactivated', 'subject_id' => $workType->id]);
    }

    public function test_work_type_toggle_dicatat(): void
    {
        $workType = $this->workType();

        $this->patch(route('admin-panel.work-types.toggle-active', $workType))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'work_type',
            'action' => 'deactivated',
            'subject_id' => $workType->id,
        ]);

        $this->patch(route('admin-panel.work-types.toggle-active', $workType))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'work_type',
            'action' => 'activated',
            'subject_id' => $workType->id,
        ]);
    }
}
