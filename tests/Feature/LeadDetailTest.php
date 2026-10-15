<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadDetailTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeLead(?User $assignee = null): Lead
    {
        $customer = Customer::create([
            'name' => 'PT Detail',
            'company' => 'PT Detail Tbk',
            'contact_person' => 'Budi',
            'phone' => '021-111',
            'email' => 'budi@detail.id',
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'cool',
            'source' => 'website',
            'kebutuhan' => 'Butuh fiber optik',
            'assigned_to' => $assignee?->id,
            'incoming_date' => '2026-09-01',
        ]);
    }

    public function test_marketing_can_view_lead_detail(): void
    {
        $user = $this->userWithRole('marketing');
        $lead = $this->makeLead($user);

        $res = $this->actingAs($user)->get(route('leads.show', $lead));

        $res->assertOk();
        $res->assertSee('PT Detail');
        $res->assertSee('Budi');
        $res->assertSee('Butuh fiber optik');
        $res->assertSee($user->name); // sales penanganan
        $res->assertDontSee(route('leads.pipeline')); // marketing tak lagi pegang pipeline
    }

    public function test_detail_renders_fallback_for_empty_fields(): void
    {
        $user = $this->userWithRole('marketing');
        $customer = Customer::create(['name' => 'PT Kosong']);
        $lead = Lead::create(['customer_id' => $customer->id, 'pt_group' => 'NTI', 'segment' => 'vendor']);

        $this->actingAs($user)->get(route('leads.show', $lead))->assertOk();
    }

    public function test_detail_survives_soft_deleted_customer(): void
    {
        $user = $this->userWithRole('marketing');
        $lead = $this->makeLead();
        $lead->customer->delete(); // soft-delete: relasi withTrashed tetap tampil

        $res = $this->actingAs($user)->get(route('leads.show', $lead));

        $res->assertOk();
        $res->assertSee('PT Detail');
    }

    public function test_invalid_lead_id_returns_404(): void
    {
        $user = $this->userWithRole('marketing');

        $this->actingAs($user)->get(route('leads.show', 999999))->assertNotFound();
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $user = $this->userWithRole('marketing');
        $lead = $this->makeLead();

        $other = User::factory()->create();
        $other->assignRole('sales'); // sales lain, bukan assignee

        $this->actingAs($other)->get(route('leads.show', $lead))->assertForbidden();
    }

    public function test_pipeline_still_works(): void
    {
        $user = $this->userWithRole('sales');
        $this->makeLead($user);

        $res = $this->actingAs($user)->get(route('leads.pipeline'));

        $res->assertOk();
        $res->assertSee('PT Detail');
    }
}
