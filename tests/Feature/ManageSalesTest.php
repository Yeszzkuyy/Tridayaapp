<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ManageSalesTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(string $role): User
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    private function makeLead(?User $assignee = null, string $customerName = 'PT Uji Sales'): Lead
    {
        $customer = Customer::create(['name' => $customerName]);
        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
            'assigned_to' => $assignee?->id,
        ]);
    }

    public function test_management_can_open_manage_sales(): void
    {
        $this->actingAs($this->loginAs('management'))
            ->get(route('manage-sales.index'))
            ->assertOk()
            ->assertSee('Manage Sales');
    }

    public function test_marketing_and_sales_cannot_open_manage_sales(): void
    {
        foreach (['marketing', 'sales'] as $role) {
            $this->actingAs($this->loginAs($role))
                ->get(route('manage-sales.index'))
                ->assertForbidden();
        }
    }

    public function test_management_assigns_lead_to_sales(): void
    {
        $management = $this->loginAs('management');
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $lead = $this->makeLead();

        $this->actingAs($management)
            ->post(route('manage-sales.assign', $lead), ['assigned_to' => $sales->id])
            ->assertRedirect(route('manage-sales.index'));

        $lead->refresh();
        $this->assertSame($sales->id, $lead->assigned_to);
        $this->assertSame($management->id, $lead->assigned_by);
        $this->assertNotNull($lead->assigned_at);
        $this->assertNotNull(LeadActivity::where('lead_id', $lead->id)->where('action', 'assigned')->first());

        $this->actingAs($management)->get(route('manage-sales.index'))->assertOk();
    }

    public function test_management_cannot_assign_to_non_sales_user(): void
    {
        $this->actingAs($this->loginAs('management'));
        $teknisi = User::factory()->create();
        $teknisi->assignRole('technician');
        $lead = $this->makeLead();

        $this->actingAs($this->loginAs('management'))
            ->post(route('manage-sales.assign', $lead), ['assigned_to' => $teknisi->id])
            ->assertStatus(422);

        $this->assertNull($lead->fresh()->assigned_to);
    }

    public function test_management_updates_moved_fields(): void
    {
        $management = $this->loginAs('management');
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $lead = $this->makeLead();

        $this->actingAs($management)
            ->put(route('manage-sales.update', $lead), [
                'solusi' => 'Solusi A',
                'progress_notes' => 'Sudah dihubungi',
                'notes' => 'Catatan internal',
                'assigned_to' => $sales->id,
            ])
            ->assertRedirect(route('manage-sales.index'));

        $lead->refresh();
        $this->assertSame('Solusi A', $lead->solusi);
        $this->assertSame('Sudah dihubungi', $lead->progress_notes);
        $this->assertSame('Catatan internal', $lead->notes);
        $this->assertSame($sales->id, $lead->assigned_to);
        $this->assertNotNull($lead->assigned_at);
    }

    public function test_management_can_change_lead_pt_group(): void
    {
        $management = $this->loginAs('management');
        $lead = $this->makeLead();

        $this->actingAs($management)
            ->put(route('manage-sales.update', $lead), ['pt_group' => 'MGK'])
            ->assertRedirect(route('manage-sales.index'));

        $this->assertSame('MGK', $lead->fresh()->pt_group);
        $this->assertNotNull(LeadActivity::where('lead_id', $lead->id)->where('action', 'updated')->first());
    }

    public function test_management_changing_pt_keeps_assignment(): void
    {
        $this->actingAs($this->loginAs('management'));
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $lead = $this->makeLead($sales);

        $this->actingAs($this->loginAs('management'))
            ->put(route('manage-sales.update', $lead), ['pt_group' => 'WANI'])
            ->assertRedirect(route('manage-sales.index'));

        $lead->refresh();
        $this->assertSame('WANI', $lead->pt_group);
        $this->assertSame($sales->id, $lead->assigned_to);
    }

    public function test_sales_sees_only_own_assigned_leads(): void
    {
        $sales = $this->loginAs('sales');
        $otherSales = User::factory()->create();
        $otherSales->assignRole('sales');

        $mine = $this->makeLead($sales, 'PT Milik Saya');
        $otherLead = $this->makeLead($otherSales, 'PT Milik Sales Lain');

        $res = $this->actingAs($sales)->get(route('sales.my-leads'))->assertOk();

        $this->assertStringContainsString($mine->customer->name, $res->getContent());
        $this->assertStringNotContainsString($otherLead->customer->name, $res->getContent());
        $this->assertSame(1, Lead::where('assigned_to', $sales->id)->count());
    }

    public function test_sales_dashboard_renders_scoped_to_own_account(): void
    {
        $sales = $this->loginAs('sales');
        $otherSales = User::factory()->create();
        $otherSales->assignRole('sales');

        $mine = $this->makeLead($sales, 'PT Dasbor Saya');
        $otherLead = $this->makeLead($otherSales, 'PT Dasbor Lain');

        FollowUp::create([
            'customer_id' => $mine->customer_id,
            'lead_id' => $mine->id,
            'follow_up_date' => now()->subDay()->toDateString(),
            'description' => 'FU Mendesak Saya',
            'created_by' => $sales->id,
        ]);
        FollowUp::create([
            'customer_id' => $otherLead->customer_id,
            'lead_id' => $otherLead->id,
            'follow_up_date' => now()->subDay()->toDateString(),
            'description' => 'FU Sales Lain',
            'created_by' => $otherSales->id,
        ]);

        $res = $this->actingAs($sales)->get(route('sales.dashboard'))->assertOk();
        $content = $res->getContent();

        $this->assertStringContainsString('Sales Dashboard', $content);
        $this->assertStringContainsString($mine->customer->name, $content);
        $this->assertStringNotContainsString($otherLead->customer->name, $content);
    }

    public function test_my_leads_keeps_table_without_dashboard_sections(): void
    {
        $sales = $this->loginAs('sales');
        $this->makeLead($sales, 'PT Tabel Saya');

        $res = $this->actingAs($sales)->get(route('sales.my-leads'))->assertOk();
        $content = $res->getContent();

        $this->assertStringContainsString('PT Tabel Saya', $content);
        $this->assertStringContainsString('My Leads', $content);
        $this->assertStringContainsString('Search customer', $content);
        $this->assertStringNotContainsString('My Lead Status', $content);
    }

    public function test_export_neutralizes_formula_cells(): void
    {
        $sales = $this->loginAs('sales');
        $lead = $this->makeLead($sales, '=HYPERLINK("http://evil", "Klik")');
        $lead->update(['kebutuhan' => '+cmd|calc']);

        $content = $this->actingAs($sales)->get(route('sales.my-leads.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString("'+cmd|calc", $content);
    }

    public function test_marketing_can_store_lead_without_assignment(): void
    {
        $user = $this->loginAs('marketing');
        $customer = Customer::create(['name' => 'PT Tanpa Sales']);

        $this->actingAs($user)->post(route('leads.store'), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'pt_group' => 'MGK',
            'segment' => 'end_user',
            'kebutuhan' => 'Jaringan kantor',
            'incoming_date' => now()->toDateString(),
        ])->assertRedirect(route('leads.index'));

        $lead = Lead::where('customer_id', $customer->id)->first();
        $this->assertNotNull($lead);
        $this->assertNull($lead->assigned_to);
    }

    public function test_create_form_does_not_offer_sales_assignment(): void
    {
        $res = $this->actingAs($this->loginAs('marketing'))
            ->get(route('leads.create'))
            ->assertOk();

        $this->assertStringNotContainsString('assigned_to', $res->getContent());
        $this->assertStringContainsString(__('Lead dari PT'), $res->getContent());
    }

    public function test_management_activity_log_shows_management_actions(): void
    {
        $management = $this->loginAs('management');
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $lead = $this->makeLead();

        $this->actingAs($management)
            ->post(route('manage-sales.assign', $lead), ['assigned_to' => $sales->id])
            ->assertRedirect(route('manage-sales.index'));

        $this->actingAs($management)
            ->get(route('manage-sales.activity-log'))
            ->assertOk()
            ->assertSee('Activity Log')
            ->assertSee($management->name);
    }

    public function test_activity_log_tolerates_legacy_scalar_changes(): void
    {
        $management = $this->loginAs('management');
        $lead = $this->makeLead();

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $management->id,
            'action' => 'created',
            'changes' => ['source' => 'whatsapp', 'sender' => '6281998880921'],
        ]);

        $this->actingAs($management)
            ->get(route('manage-sales.activity-log'))
            ->assertOk()
            ->assertSee('6281998880921');
    }

    public function test_sales_cannot_open_activity_log(): void
    {
        $this->actingAs($this->loginAs('sales'))
            ->get(route('manage-sales.activity-log'))
            ->assertForbidden();
    }
}