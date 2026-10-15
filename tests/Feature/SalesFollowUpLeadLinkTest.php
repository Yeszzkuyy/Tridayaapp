<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesFollowUpLeadLinkTest extends TestCase
{
    use RefreshDatabase;

    private function salesUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('sales');

        return $user;
    }

    private function makeLead(User $assignee, Customer $customer, string $status = 'cool'): Lead
    {
        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => $status,
            'incoming_date' => now()->toDateString(),
            'assigned_to' => $assignee->id,
        ]);
    }

    private function followUpPayload(Customer $customer, ?int $leadId = null): array
    {
        $payload = [
            'customer_id' => $customer->id,
            'description' => 'Follow up penawaran.',
            'type' => 'whatsapp',
            'follow_up_date' => today()->toDateString(),
        ];
        if ($leadId) {
            $payload['lead_id'] = $leadId;
        }

        return $payload;
    }

    public function test_auto_links_single_active_own_lead_and_logs_activity(): void
    {
        $sales = $this->salesUser();
        $customer = Customer::create(['name' => 'PT Satu Lead']);
        $lead = $this->makeLead($sales, $customer);

        $this->actingAs($sales)
            ->post(route('sales.follow-ups.store'), $this->followUpPayload($customer))
            ->assertRedirect(route('sales.follow-ups.index'));

        $fu = FollowUp::first();
        $this->assertSame($lead->id, $fu->lead_id);
        $this->assertTrue(
            LeadActivity::where('lead_id', $lead->id)->where('action', 'followup_created')->exists(),
            'Lead tercatat otomatis sudah di-follow-up.'
        );
    }

    public function test_rejects_when_customer_has_multiple_active_own_leads(): void
    {
        $sales = $this->salesUser();
        $customer = Customer::create(['name' => 'PT Banyak Lead']);
        $this->makeLead($sales, $customer, 'warm');
        $this->makeLead($sales, $customer, 'hot');

        $this->actingAs($sales)
            ->post(route('sales.follow-ups.store'), $this->followUpPayload($customer))
            ->assertStatus(422);

        // Bila lead dipilih eksplisit, tetap lolos.
        $chosen = Lead::where('customer_id', $customer->id)->first();
        $this->actingAs($sales)
            ->post(route('sales.follow-ups.store'), $this->followUpPayload($customer, $chosen->id))
            ->assertRedirect(route('sales.follow-ups.index'));
        $this->assertSame($chosen->id, FollowUp::first()->lead_id);
    }

    public function test_forbids_linking_other_sales_lead(): void
    {
        $sales = $this->salesUser();
        $other = User::factory()->create();
        $other->assignRole('sales');
        $customer = Customer::create(['name' => 'PT Orang Lain']);
        $foreignLead = $this->makeLead($other, $customer);

        $this->actingAs($sales)
            ->post(route('sales.follow-ups.store'), $this->followUpPayload($customer, $foreignLead->id))
            ->assertForbidden();
    }

    public function test_closed_and_foreign_leads_do_not_force_choice(): void
    {
        $sales = $this->salesUser();
        $customer = Customer::create(['name' => 'PT Bebas']);
        // Lead won milik sendiri + lead aktif milik sales lain: bukan hitungan.
        $this->makeLead($sales, $customer, 'won');
        $other = User::factory()->create();
        $other->assignRole('sales');
        $this->makeLead($other, $customer, 'cool');

        $this->actingAs($sales)
            ->post(route('sales.follow-ups.store'), $this->followUpPayload($customer))
            ->assertRedirect(route('sales.follow-ups.index'));
        $this->assertNull(FollowUp::first()->lead_id);
    }

    public function test_non_sales_stays_flexible(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $customer = Customer::create(['name' => 'PT Admin']);

        $this->actingAs($admin)
            ->post(route('sales.follow-ups.store'), $this->followUpPayload($customer))
            ->assertRedirect(route('sales.follow-ups.index'));
        $this->assertNull(FollowUp::first()->lead_id);
    }
}
