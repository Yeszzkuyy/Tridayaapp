<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\RequestHarga;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AdminHubTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(string $role): User
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    public function test_admin_and_management_can_access_admin_hub(): void
    {
        // Admin role (manage-admin, view-admin)
        $admin = $this->loginAs('admin');
        $this->actingAs($admin)
            ->get(route('manage.admin.index'))
            ->assertOk()
            ->assertSee(__('Admin Command Center'))
            ->assertSee(__('1. Purchasing & Pengadaan'))
            ->assertSee(__('2. Finance & Penagihan'))
            ->assertSee(__('3. Kelengkapan Dokumen Proyek'));

        // Management role (manage-sales-leads, view-admin)
        $management = $this->loginAs('management');
        $this->actingAs($management)
            ->get(route('manage.admin.index'))
            ->assertOk()
            ->assertSee(__('Admin Command Center'));
    }

    public function test_unauthorized_roles_cannot_access_admin_hub(): void
    {
        // marketing and technician do not have view-admin or manage-admin
        foreach (['marketing', 'technician'] as $role) {
            $user = $this->loginAs($role);
            $this->actingAs($user)
                ->get(route('manage.admin.index'))
                ->assertForbidden();
        }
    }

    public function test_admin_hub_pt_group_filter(): void
    {
        $admin = $this->loginAs('admin');
        $this->actingAs($admin);

        $workType = WorkType::create(['name' => 'Instalasi']);

        $custNti = Customer::create(['name' => 'PT NTI Client', 'pt_group' => 'NTI']);
        $custMgk = Customer::create(['name' => 'PT MGK Client', 'pt_group' => 'MGK']);

        $projNti = Project::create([
            'customer_id' => $custNti->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project NTI Alpha',
        ]);
        $projMgk = Project::create([
            'customer_id' => $custMgk->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project MGK Beta',
        ]);

        // Request harga for NTI
        RequestHarga::create([
            'project_id' => $projNti->id,
            'items' => [['device' => 'Switch 24 Port', 'quantity' => 2]],
            'status' => 'submitted',
        ]);

        // Filter NTI
        $response = $this->get(route('manage.admin.index', ['pt_group' => 'NTI']))
            ->assertOk();

        $response->assertSee('Project NTI Alpha');
        $response->assertDontSee('Project MGK Beta');
    }

    public function test_prefill_create_invoice_from_project(): void
    {
        $admin = $this->loginAs('admin');
        $this->actingAs($admin);

        $workType = WorkType::create(['name' => 'Instalasi']);

        $customer = Customer::create(['name' => 'Customer XYZ']);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'won',
        ]);

        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'PROP-2026-001',
            'created_by' => $admin->id,
            'proposal_date' => now(),
            'valid_until' => now()->addDays(14),
            'customer' => $customer->name,
            'subtotal' => 10000000,
            'grand_total' => 11100000,
            'status' => 'accepted',
        ]);

        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'proposal_id' => $proposal->id,
            'project_name' => 'Proyek Pengadaan Server',
        ]);

        $response = $this->get(route('admin.invoices.create', ['project_id' => $project->id]))
            ->assertOk();

        $response->assertSee((string) $customer->id);
        $response->assertSee('11100000');
        $response->assertSee('PROP-2026-001');
    }

    public function test_prefill_create_po_from_request_harga(): void
    {
        $admin = $this->loginAs('admin');
        $this->actingAs($admin);

        $workType = WorkType::create(['name' => 'Instalasi']);

        $customer = Customer::create(['name' => 'Vendor Test Client']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Proyek CCTV Bandara',
        ]);

        $rh = RequestHarga::create([
            'project_id' => $project->id,
            'items' => [
                ['device' => 'Camera PTZ 4K', 'quantity' => 4, 'specification' => 'Hikvision Darkfighter'],
                ['device' => 'NVR 32 Channel', 'quantity' => 1, 'specification' => '4 HDD Bay'],
            ],
            'status' => 'submitted',
        ]);

        $response = $this->get(route('admin.pos.create', ['request_harga_id' => $rh->id]))
            ->assertOk();

        $response->assertSee('4x Camera PTZ 4K (Hikvision Darkfighter)');
        $response->assertSee('1x NVR 32 Channel (4 HDD Bay)');
    }

    public function test_prefill_create_payment_from_invoice(): void
    {
        $admin = $this->loginAs('admin');
        $this->actingAs($admin);

        $customer = Customer::create(['name' => 'Client Pay Test']);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-0099',
            'customer_id' => $customer->id,
            'amount' => 5000000,
            'status' => 'unpaid',
            'issue_date' => now(),
            'created_by' => $admin->id,
        ]);

        $response = $this->get(route('admin.payments.create', ['invoice_id' => $invoice->id]))
            ->assertOk();

        $response->assertSee('5000000');
        $response->assertSee('INV-2026-0099');
    }
}
