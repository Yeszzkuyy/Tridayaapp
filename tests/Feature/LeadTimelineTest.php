<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTimelineTest extends TestCase
{
    use RefreshDatabase;

    private function marketingUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('marketing');

        return $user;
    }

    private function salesUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('sales');

        return $user;
    }

    private function makeLead(?User $assignee = null): Lead
    {
        $customer = Customer::create(['name' => 'PT Timeline']);
        LeadActivity::query()->delete();

        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'cool',
            'assigned_to' => $assignee?->id,
            'incoming_date' => '2026-09-01',
        ]);
    }

    public function test_status_change_is_logged_and_rendered(): void
    {
        $user = $this->marketingUser();
        $lead = $this->makeLead();

        $this->actingAs($user)->patch(route('leads.update-status', $lead), ['status' => 'warm'])
            ->assertNoContent();

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'action' => 'status_changed',
        ]);

        $res = $this->actingAs($user)->get(route('leads.show', $lead));
        $res->assertOk();
        $res->assertSee(__('Riwayat Lead'));
        // locale-proof: label diterjemahkan sesuai locale aktif
        $res->assertSee($lead->activities->first()->actionLabel());
    }

    public function test_followup_with_lead_logs_activity(): void
    {
        $sales = $this->salesUser();
        $lead = $this->makeLead($sales);

        $this->actingAs($sales)->postJson(route('sales.follow-ups.store'), [
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'description' => 'Hubungi PIC',
        ])->assertOk();

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'action' => 'followup_created',
        ]);
    }

    public function test_followup_without_lead_logs_nothing(): void
    {
        $sales = $this->salesUser();
        $customer = Customer::create(['name' => 'PT NoLead']);

        $this->actingAs($sales)->postJson(route('sales.follow-ups.store'), [
            'customer_id' => $customer->id,
            'description' => 'Follow up tanpa lead',
        ])->assertOk();

        $this->assertDatabaseCount('lead_activities', 0);
    }

    public function test_empty_history_shows_empty_state(): void
    {
        $user = $this->marketingUser();
        $lead = $this->makeLead();

        $res = $this->actingAs($user)->get(route('leads.show', $lead));
        $res->assertOk();
        $res->assertSee(__('Riwayat Lead'));
        $res->assertSee(__('Belum ada riwayat untuk lead ini.'));
    }
}
